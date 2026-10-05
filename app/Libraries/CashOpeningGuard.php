<?php

namespace App\Libraries;

use App\Models\CashOpeningBalanceModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Release 4.9.0CF: warn (never block) when a CASH transaction is dated before the
 * permanent Cash Opening Date. Such a transaction is still stored in its own ledger,
 * but the Cash Book and Monthly Statement do not count it once they reach the opening.
 *
 * Every cash-capable create/update endpoint calls gate() after its own validation and
 * before it writes anything. The first submission gets HTTP 409 (JSON for AJAX forms, a
 * confirmation page for classic forms) and saves nothing. The second submission carries
 * a confirmation token which is checked here, on the server: it is an HMAC over the
 * transaction (scope + record id + a fingerprint of every submitted field), its date, its
 * method and the Cash Opening Date, keyed
 * to the logged-in user. A changed date, method, record, opening date or user
 * invalidates it, so the warning is shown again.
 *
 * Nothing here reads or writes any amount; it never changes the opening or the movement.
 */
class CashOpeningGuard
{
    /** POST field that carries the confirmation token on the second submission. */
    public const FIELD = 'cash_opening_confirm';

    /** Strict calendar date, exactly as submitted: YYYY-MM-DD, a real day, and not the zero date (callers trim first). */
    public static function isValidDate($value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || $value === '0000-00-00') {
            return false;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value && (int) substr($value, 0, 4) >= 1900;
    }

    /** The opening row when a transaction in $method on $date falls before it, else null. */
    public static function applies($method, $date): ?array
    {
        if (strtoupper(trim((string) $method)) !== 'CASH' || ! self::isValidDate($date)) {
            return null;
        }
        $opening = (new CashOpeningBalanceModel())->current();

        return ($opening && (string) $date < $opening['opening_date']) ? $opening : null;
    }

    /** Fields that are not part of the transaction itself and so never enter the fingerprint. */
    private const NOT_TRANSACTION = [self::FIELD, 'confirm_duplicate'];

    /** Order-independent fingerprint of everything the user submitted (so any edit after the warning invalidates it). */
    public static function fingerprint(array $post): string
    {
        $skip = array_merge(self::NOT_TRANSACTION, [config('Security')->tokenName ?? 'csrf_test_name', 'csrf_test_name']);
        foreach ($skip as $k) {
            unset($post[$k]);
        }
        $norm = static function ($v) use (&$norm) {
            if (is_array($v)) {
                ksort($v);

                return array_map($norm, $v);
            }

            // A textarea returns CRLF from the browser and LF from a hidden input: treat them alike.
            return str_replace("\r\n", "\n", (string) $v);
        };

        return hash('sha256', json_encode($norm($post)));
    }

    public static function token(string $scope, int $id, string $date, string $openingDate, string $fingerprint = ''): string
    {
        $db  = config('Database')->default;
        $key = hash('sha256', 'cash-opening|' . (string) (session()->get('user_id') ?? '') . '|' . ($db['hostname'] ?? '') . '|' . ($db['database'] ?? '') . '|' . ($db['password'] ?? ''));

        return hash_hmac('sha256', implode('|', ['cash-opening', $scope, $id, $date, 'CASH', $openingDate, $fingerprint]), $key);
    }

    /**
     * Returns null when the caller may go on and save. Otherwise returns the 409 response
     * to send back, and the caller must write nothing.
     *
     * @param string $scope  the record kind ("expense", "invoice-payment", ...)
     * @param int    $id     the record being edited, 0 for a new one
     * @param bool   $json   true for endpoints that only ever answer JSON
     */
    public static function gate(RequestInterface $request, string $scope, int $id, $method, $date, bool $json = false): ?ResponseInterface
    {
        $opening = self::applies($method, $date);
        if ($opening === null) {
            return null;
        }

        $date  = (string) $date;
        $token = self::token($scope, $id, $date, $opening['opening_date'], self::fingerprint($request->getPost() ?: []));
        $given = (string) $request->getPost(self::FIELD);
        if ($given !== '' && hash_equals($token, $given)) {
            return null;
        }

        $dmy     = static fn (string $d): string => date('d/m/Y', strtotime($d));
        $message = 'This transaction is dated before the Cash Opening Date. It will remain recorded in its original ledger, '
            . 'but it will not be included in Cash Book and Monthly Statement calculations after the Cash Opening Date.';
        $payload = [
            'status'               => false,
            'cash_opening_warning' => true,
            'tx_date'              => $dmy($date),
            'opening_date'         => $dmy($opening['opening_date']),
            'token'                => $token,
            'field'                => self::FIELD,
            'message'              => 'Not saved: cash transaction before the Cash Opening Date.',
            'errors'               => ['Not saved: this cash transaction is dated before the Cash Opening Date (' . $dmy($opening['opening_date']) . '). Use Continue and Save on the warning to save it.'],
            'detail'               => $message,
        ];

        $response = service('response')->setStatusCode(409);
        if ($json || $request->isAJAX() || str_contains((string) $request->getHeaderLine('Accept'), 'application/json')) {
            return $response->setJSON($payload);
        }

        // Classic form: show a confirmation page that re-posts every submitted field plus the token.
        $post = $request->getPost() ?: [];
        unset($post[self::FIELD]);

        return $response->setBody(view('cash_opening/warning', [
            'txDate'      => $payload['tx_date'],
            'openingDate' => $payload['opening_date'],
            'detail'      => $message,
            'action'      => current_url(),
            'fields'      => self::flatten($post),
            'tokenField'  => self::FIELD,
            'token'       => $token,
        ]));
    }

    /** Nested POST data -> [[name, value], ...] for hidden inputs. */
    public static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $name = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
            if (is_array($v)) {
                $out = array_merge($out, self::flatten($v, $name));
            } else {
                $out[] = [$name, (string) $v];
            }
        }

        return $out;
    }
}
