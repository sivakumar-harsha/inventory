<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Models\CashOpeningBalanceModel;
use CodeIgniter\Controller;

/**
 * Release 4.9.0CC: the one screen where the opening cash balance is entered.
 * Release 4.9.0CF: it is entered ONCE and then locked for good. The server refuses every
 * later POST (no edit, no replace, no second row, no remove); hiding the form is not the
 * lock. It stores a balance brought forward in cash_opening_balance and creates no
 * transaction of any kind (no receipt, income, bank row or customer movement).
 */
class CashOpeningBalance extends Controller
{
    private const LOCKED = 'Cash Opening Balance has already been set and is locked.';

    public function index()
    {
        return $this->_render(new CashOpeningBalanceModel(), ['opening_date' => '', 'amount' => '', 'remarks' => ''], []);
    }

    public function save()
    {
        $model = new CashOpeningBalanceModel();

        // The lock is enforced here, on the server, before anything is read from the form.
        if ($model->current() !== null) {
            return $this->_render($model, [], ['_lock' => self::LOCKED])->setStatusCode(409);
        }

        $date    = trim((string) $this->request->getPost('opening_date'));
        $amount  = trim((string) $this->request->getPost('amount'));
        $remarks = trim((string) $this->request->getPost('remarks'));
        $input   = ['opening_date' => $date, 'amount' => $amount, 'remarks' => $remarks];
        $errors  = [];

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (! $d || $d->format('Y-m-d') !== $date) {
            $errors['opening_date'] = 'Enter a valid opening date.';
        }
        if ($amount === '' || ! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $amount)) {
            $errors['amount'] = 'Opening cash must be a number of zero or more with at most 2 decimals.';
        }
        if (mb_strlen($remarks) > 255) {
            $errors['remarks'] = 'Remarks can be at most 255 characters.';
        }
        if (! \Config\Database::connect()->tableExists('cash_opening_balance')) {
            $errors['_'] = 'The cash opening balance table has not been created yet. Run the pending migration first.';
        }

        if ($errors) {
            return $this->_render($model, $input, $errors)->setStatusCode(422);
        }

        if (! $model->createOnce($date, (float) $amount, $remarks !== '' ? $remarks : null)) {
            return $this->_render($model, $input, ['_lock' => self::LOCKED])->setStatusCode(409);
        }

        $new = $model->current();
        (new AuditLogger())->logCreate('Cash Book', 'CASH_OPENING_BALANCE', $new['id'], null,
            ['opening_date' => $new['opening_date'], 'amount' => number_format($new['amount'], 2, '.', ''), 'remarks' => (string) $new['remarks']],
            'Cash opening balance set (permanent)');

        session()->setFlashdata('success', 'Cash opening balance saved. It is now locked and cannot be edited or removed.');

        return redirect()->to(base_url('cash-book/opening-balance'));
    }

    private function _render(CashOpeningBalanceModel $model, array $input, array $errors)
    {
        $saved = $model->current();

        return $this->response->setBody(view('cash_book/opening_balance', [
            'input'  => $input,
            'errors' => $errors,
            'saved'  => $saved,
            // Standing notice when the permanent opening date already leaves recorded movements out.
            'hidden' => $saved ? CashOpeningBalanceModel::excludedBy($saved['opening_date']) : null,
        ]));
    }
}
