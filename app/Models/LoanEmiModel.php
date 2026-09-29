<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.5A: one row per instalment of a loan's EMI schedule. Shape
 * validation and the two schedule reads; how a schedule is generated and how
 * payments are applied to it lives in the Loans controller.
 */
class LoanEmiModel extends Model
{
    protected $table      = 'loan_emis';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'loan_id',
        'emi_no',
        'due_date',
        'principal_amount',
        'interest_amount',
        'emi_amount',
        'paid_amount',
        'balance_amount',
        'payment_status',
        'paid_date',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'loan_id'          => 'required|integer',
        'emi_no'           => 'required|integer|greater_than[0]',
        'due_date'         => 'required|valid_date',
        'principal_amount' => 'permit_empty|numeric|greater_than_equal_to[0]',
        'interest_amount'  => 'permit_empty|numeric|greater_than_equal_to[0]',
        'emi_amount'       => 'permit_empty|numeric|greater_than_equal_to[0]',
        'paid_amount'      => 'permit_empty|numeric|greater_than_equal_to[0]',
        'balance_amount'   => 'permit_empty|numeric|greater_than_equal_to[0]',
        'payment_status'   => 'permit_empty|in_list[PENDING,PARTIAL,PAID]',
        'paid_date'        => 'permit_empty|valid_date',
    ];

    /**
     * The full schedule of one loan in EMI order, each row carrying the
     * principal opening and closing balance around it. Those two are not
     * stored: they are the sanctioned amount less the running total of the
     * principal_amount of the instalments before / up to this one.
     */
    public function emiList(int $loanId): array
    {
        $loan = $this->db->query('SELECT sanctioned_amount FROM loans WHERE id = ?', [$loanId])->getRowArray();

        if (! $loan) {
            return [];
        }

        $rows    = $this->where('loan_id', $loanId)->orderBy('emi_no', 'ASC')->findAll();
        $balance = round((float) $loan['sanctioned_amount'], 2);

        foreach ($rows as &$row) {
            $row['opening_balance'] = $balance;
            $balance                = round($balance - (float) $row['principal_amount'], 2);
            $row['closing_balance'] = $balance;
        }
        unset($row);

        return $rows;
    }

    /**
     * emiList() plus, for each instalment, how much of its principal and of its
     * interest is still to pay (rem_principal + rem_interest = balance_amount).
     * The payment screen pre-fills its Pay / Continue Payment form from these.
     * The principal share is what the schedule says minus what earlier
     * payments already put towards principal; the rest is interest.
     */
    public function emiListWithRemaining(int $loanId): array
    {
        $rows = $this->emiList($loanId);
        $paid = [];

        $sums = $this->db->query(
            'SELECT loan_emi_id, SUM(principal_paid) AS p FROM loan_payments
             WHERE loan_id = ? AND loan_emi_id IS NOT NULL GROUP BY loan_emi_id',
            [$loanId]
        )->getResultArray();

        foreach ($sums as $s) {
            $paid[(int) $s['loan_emi_id']] = (float) $s['p'];
        }

        foreach ($rows as &$row) {
            $balance                = round((float) $row['balance_amount'], 2);
            $principal              = min($balance, max(0.0, round((float) $row['principal_amount'] - ($paid[(int) $row['id']] ?? 0.0), 2)));
            $row['rem_principal']   = round($principal, 2);
            $row['rem_interest']    = round($balance - $principal, 2);
        }
        unset($row);

        return $rows;
    }

    /**
     * Unpaid instalments (PENDING or PARTIAL) of ACTIVE loans that fall due
     * within the next $days days, plus everything already overdue, soonest
     * first. days_to_due is negative for an overdue instalment.
     */
    public function upcomingEmis(int $days = 30, ?int $loanId = null): array
    {
        $today = date('Y-m-d');
        $limit = date('Y-m-d', strtotime("+{$days} days"));

        $builder = $this->db->table('loan_emis le')
            ->select('le.*, l.loan_no, l.lender_name, l.loan_type')
            ->join('loans l', 'l.id = le.loan_id')
            ->where('l.status', 'ACTIVE')
            ->where('le.payment_status <>', 'PAID')
            ->where('le.due_date <=', $limit)
            ->orderBy('le.due_date', 'ASC')
            ->orderBy('le.id', 'ASC');

        if ($loanId !== null) {
            $builder->where('le.loan_id', $loanId);
        }

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$row) {
            $diff               = (new \DateTime($today))->diff(new \DateTime($row['due_date']));
            $row['days_to_due'] = $diff->invert ? -$diff->days : $diff->days;
            $row['is_overdue']  = $row['due_date'] < $today;
        }
        unset($row);

        return $rows;
    }
}
