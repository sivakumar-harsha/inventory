<?php

namespace App\Controllers;

use App\Models\LoanTypeModel;
use CodeIgniter\Controller;

/**
 * Loan Type master. Only the quick-add used by the New Loan page lives here;
 * the types themselves are read through LoanTypeModel by the Loan screens,
 * reports and exports. Same auth gate as the Loan routes (no finer-grained
 * permission system exists in the app).
 */
class LoanTypes extends Controller
{
    /**
     * POST loan-types/quick-store (JSON). Rules mirror ExpenseCategories::quickStore():
     * name required, no duplicate, new type ACTIVE. The stable `code` stored in
     * loans.loan_type is derived from the name; the user only ever types the name.
     */
    public function quickStore()
    {
        if (! LoanTypeModel::ready()) {
            return $this->_fail(503, 'The Loan Type master is not available yet. Please apply the pending database migration.');
        }

        $model = new LoanTypeModel();
        $name  = trim((string) preg_replace('/\s+/', ' ', (string) $this->request->getPost('loan_type_name')));

        if ($name === '') {
            return $this->_fail(422, 'Loan type name is required.');
        }

        if (mb_strlen($name) > 100) {
            return $this->_fail(422, 'Loan type name cannot be longer than 100 characters.');
        }

        $code = $model->newCode($name);

        // Duplicate by label, or by a name that collapses to an existing code (e.g. "Bank", "OD").
        $plain = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'));
        if ($model->where('label', $name)->first() || ($plain !== '' && $model->where('code', $plain)->first())) {
            return $this->_fail(422, 'This loan type already exists.');
        }

        try {
            $id = $model->insert(['code' => $code, 'label' => $name, 'status' => 'ACTIVE']);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            // Lost a race against the unique keys: treat as the duplicate it is.
            return $this->_fail(422, 'This loan type already exists.');
        }

        return $this->response->setJSON([
            'status'    => true,
            'loan_type' => ['id' => (int) $id, 'code' => $code, 'label' => $name],
        ]);
    }

    private function _fail(int $status, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => false, 'errors' => [$message]]);
    }
}
