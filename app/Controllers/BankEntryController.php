<?php

namespace App\Controllers;

use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.4I: shared CRUD skeleton for the manual bank ledger screens —
 * Bank Deposit, Bank Withdrawal, Bank Transfer and Bank Daybook. Not routed
 * itself; each concrete controller supplies its own fields, validation and
 * row mapping, and this class supplies the endpoints, the page rendering and
 * the transaction wrapper.
 *
 * Endpoint shape follows BankAccounts exactly: the pages are server-rendered
 * and their forms/deletes go through AJAX, returning {status, message|errors[,
 * id]} with a 422 for validation and business-rule failures (including an
 * overdraft) and a 500 for anything unexpected.
 *
 * No balance arithmetic lives here or in the concrete controllers: every
 * posting, reversal and repost is a call into BankTransactionModel, inside the
 * transStart()/transComplete() opened by store()/update()/delete() below, so a
 * failure part-way through rolls the rows and the balances back together.
 */
abstract class BankEntryController extends Controller
{
    protected const MODES = ['CASH', 'CHEQUE', 'UPI', 'NEFT', 'IMPS', 'RTGS', 'OTHER'];

    /** Page config: slug, title, plural, singular, icon, kpi_class, fields[], columns[], detail[], preview[]. */
    abstract protected function cfg(): array;

    /** The bank_transactions.reference_type this screen owns. */
    abstract protected function referenceType(): string;

    /** Raw POST -> trimmed/typed input array. */
    abstract protected function extractInput(): array;

    /** @return string[] validation messages, empty when the input is acceptable. */
    abstract protected function validateInput(array $input): array;

    /** Validated input -> createBankTransaction()-style array for the single row this screen writes. */
    abstract protected function rowData(array $input): array;

    /** One joined bank_transactions row -> the entry array the views read (form field names + display extras). */
    abstract protected function presentRow(array $row): array;

    // =========================================================
    // PAGES
    // =========================================================

    public function index()
    {
        $entries = $this->fetchEntries();

        return view('bank_entries/index', [
            'cfg'     => $this->cfg(),
            'entries' => $entries,
            'kpis'    => $this->kpis($entries),
        ]);
    }

    public function create()
    {
        return view('bank_entries/form', $this->formData(null));
    }

    public function view($id)
    {
        $entry = $this->findEntry((int) $id);

        if (! $entry) {
            return $this->_notFound();
        }

        return view('bank_entries/view', ['cfg' => $this->cfg(), 'entry' => $entry]);
    }

    public function edit($id)
    {
        $entry = $this->findEntry((int) $id);

        if (! $entry) {
            return $this->_notFound();
        }

        return view('bank_entries/form', $this->formData($entry));
    }

    // =========================================================
    // WRITES (JSON)
    // =========================================================

    public function store()
    {
        $input  = $this->extractInput();
        $errors = $this->validateInput($input);

        if ($errors) {
            return $this->_fail($errors, 422);
        }

        $db    = \Config\Database::connect();
        $model = new BankTransactionModel();
        $db->transStart();

        try {
            $id = $this->postEntry($model, $input);
        } catch (\DomainException $e) {
            $db->transRollback();
            return $this->_fail([$e->getMessage()], 422);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_fail(['Failed to save ' . strtolower($this->cfg()['singular']) . ': ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_fail(['Failed to save ' . strtolower($this->cfg()['singular']) . ' due to a database error.'], 500);
        }

        $message = $this->cfg()['singular'] . ' recorded successfully.';
        session()->setFlashdata('success', $message);

        return $this->response->setJSON(['status' => true, 'message' => $message, 'id' => $id]);
    }

    public function update($id)
    {
        $entry = $this->findEntry((int) $id);

        if (! $entry) {
            return $this->_fail([$this->cfg()['singular'] . ' not found.'], 404);
        }

        $input  = $this->extractInput();
        $errors = $this->validateInput($input);

        if ($errors) {
            return $this->_fail($errors, 422);
        }

        $db    = \Config\Database::connect();
        $model = new BankTransactionModel();
        $db->transStart();

        try {
            $this->repostEntry($model, $entry, $input);
        } catch (\DomainException $e) {
            $db->transRollback();
            return $this->_fail([$e->getMessage()], 422);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_fail(['Failed to update ' . strtolower($this->cfg()['singular']) . ': ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_fail(['Failed to update ' . strtolower($this->cfg()['singular']) . ' due to a database error.'], 500);
        }

        $message = $this->cfg()['singular'] . ' updated successfully.';
        session()->setFlashdata('success', $message);

        return $this->response->setJSON(['status' => true, 'message' => $message]);
    }

    public function delete($id)
    {
        $entry = $this->findEntry((int) $id);

        if (! $entry) {
            return $this->_fail([$this->cfg()['singular'] . ' not found.'], 404);
        }

        $db    = \Config\Database::connect();
        $model = new BankTransactionModel();
        $db->transStart();

        try {
            $this->deleteEntry($model, $entry);
        } catch (\DomainException $e) {
            $db->transRollback();
            return $this->_fail([$e->getMessage()], 422);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_fail(['Failed to delete ' . strtolower($this->cfg()['singular']) . ': ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_fail(['Failed to delete ' . strtolower($this->cfg()['singular']) . ' due to a database error.'], 500);
        }

        $message = $this->cfg()['singular'] . ' deleted and the bank balance reversed.';
        session()->setFlashdata('success', $message);

        return $this->response->setJSON(['status' => true, 'message' => $message]);
    }

    // =========================================================
    // ENGINE HOOKS — single-row screens use these as they are;
    // BankTransfers overrides them for its two-row transfer.
    // =========================================================

    protected function postEntry(BankTransactionModel $model, array $input): int
    {
        return $model->postManualEntry($this->rowData($input) + ['created_by' => session()->get('user_id')]);
    }

    protected function repostEntry(BankTransactionModel $model, array $entry, array $input): void
    {
        $model->updateManualEntry((int) $entry['id'], $this->rowData($input));
    }

    protected function deleteEntry(BankTransactionModel $model, array $entry): void
    {
        $model->deleteManualEntry((int) $entry['id']);
    }

    /** @return array[] entries of this screen, newest first (or just the one with $id). */
    protected function fetchEntries(?int $id = null): array
    {
        $builder = \Config\Database::connect()->table('bank_transactions bt')
            ->select('bt.*, ba.bank_name, ba.account_name, ba.account_number, u.username AS created_by_name')
            ->join('bank_accounts ba', 'ba.id = bt.bank_account_id')
            ->join('users u', 'u.id = bt.created_by', 'left')
            ->where('bt.reference_type', $this->referenceType());

        if ($id !== null) {
            $builder->where('bt.id', $id);
        }

        $entries = [];
        foreach ($builder->orderBy('bt.transaction_date', 'DESC')->orderBy('bt.id', 'DESC')->get()->getResultArray() as $row) {
            $amount = (float) $row['amount'];

            $entries[] = $this->presentRow($row) + [
                'id'               => (int) $row['id'],
                'transaction_date' => $row['transaction_date'],
                'bank_account_id'  => (int) $row['bank_account_id'],
                'bank_label'       => $row['bank_name'] . ' — ' . $row['account_number'],
                'amount'           => $amount,
                'reference_no'     => $row['reference_no'],
                'remarks'          => $row['remarks'],
                'created_by_name'  => $row['created_by_name'],
                'created_at'       => $row['created_at'],
                'updated_at'       => $row['updated_at'],
                // Signed effect of this entry on each account it touches, for the form's balance preview when editing.
                'effects'          => [(int) $row['bank_account_id'] => BankTransactionModel::isCredit($row['transaction_type']) ? $amount : -$amount],
            ];
        }

        return $entries;
    }

    protected function findEntry(int $id): ?array
    {
        return $this->fetchEntries($id)[0] ?? null;
    }

    /** KPI cards above the list: [label, value, class, icon]. */
    protected function kpis(array $entries): array
    {
        $cfg   = $this->cfg();
        $total = 0.0;
        $month = 0.0;

        foreach ($entries as $entry) {
            $total += $entry['amount'];
            if (strpos($entry['transaction_date'], date('Y-m')) === 0) {
                $month += $entry['amount'];
            }
        }

        return [
            ['label' => 'Entries',                       'value' => number_format(count($entries)), 'class' => 'kpi-blue',           'icon' => 'bi-list-check'],
            ['label' => 'Total ' . $cfg['plural'],       'value' => number_format($total, 2),       'class' => $cfg['kpi_class'],    'icon' => $cfg['icon']],
            ['label' => $cfg['plural'] . ' This Month',  'value' => number_format($month, 2),       'class' => 'kpi-orange',         'icon' => 'bi-calendar-month'],
        ];
    }

    // =========================================================
    // SHARED HELPERS
    // =========================================================

    /**
     * Release 4.8.5A: the create-form data (config + active accounts) for the unified
     * Transactions entry screen, which renders this screen's fields and posts to its
     * unchanged store endpoint. Read-only; nothing is validated or posted here.
     */
    public function entryFormData(): array
    {
        return $this->formData(null);
    }

    protected function formData(?array $entry): array
    {
        $accounts = [];
        foreach ((new BankAccountModel())->activeAccounts() as $account) {
            $accounts[] = [
                'id'      => (int) $account['id'],
                'label'   => $account['bank_name'] . ' — ' . $account['account_name'] . ' (' . $account['account_number'] . ')',
                'balance' => (float) $account['current_balance'],
            ];
        }

        return ['cfg' => $this->cfg(), 'entry' => $entry, 'accounts' => $accounts];
    }

    protected function optional(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value !== '' ? $value : null;
    }

    protected function dateError(string $value): ?string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return ($value === '' || ! $date || $date->format('Y-m-d') !== $value) ? 'A valid date is required.' : null;
    }

    /** Same rule as Expenses: greater than zero, at most 2 decimals; capped at the DECIMAL(15,2) column. */
    protected function amountError(string $value): ?string
    {
        if ($value === '' || ! is_numeric($value) || (float) $value <= 0) {
            return 'Amount must be greater than zero.';
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return 'Amount cannot have more than 2 decimal places.';
        }
        if ((float) $value > 9999999999999.99) {
            return 'Amount is too large.';
        }

        return null;
    }

    /** The account must exist and be active (BankTransactionModel re-checks this at posting time). */
    protected function accountError(int $accountId, string $label = 'bank account'): ?string
    {
        if ($accountId <= 0) {
            return 'The ' . $label . ' is required.';
        }

        $account = (new BankAccountModel())->find($accountId);

        if (! $account) {
            return 'The selected ' . $label . ' does not exist.';
        }

        return (int) $account['is_active'] !== 1 ? 'The selected ' . $label . ' is not active.' : null;
    }

    protected function lengthError(?string $value, int $max, string $label): ?string
    {
        return ($value !== null && mb_strlen($value) > $max) ? $label . ' cannot exceed ' . $max . ' characters.' : null;
    }

    protected function _fail(array $errors, int $status)
    {
        return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode($status);
    }

    protected function _notFound()
    {
        return redirect()->to('/' . $this->cfg()['slug'])->with('error', $this->cfg()['singular'] . ' not found.');
    }
}
