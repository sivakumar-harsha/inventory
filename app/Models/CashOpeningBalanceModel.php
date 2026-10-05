<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.9.0CC: the single stored opening cash balance (see the migration).
 * Release 4.9.0CF: permanent and one-time. The application can create it once; it can never
 * update, replace or remove it.
 * Read-tolerant: current() returns null when the table has not been migrated yet,
 * so the Cash Book and Monthly Statement keep their old "starts at zero" behaviour.
 */
class CashOpeningBalanceModel extends Model
{
    protected $table         = 'cash_opening_balance';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['opening_date', 'amount', 'remarks'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** @return array{id:int,opening_date:string,amount:float,remarks:?string}|null */
    public function current(): ?array
    {
        if (! $this->db->tableExists($this->table)) {
            return null;
        }

        $row = $this->orderBy('id', 'ASC')->first();
        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row['id'], 'opening_date' => (string) $row['opening_date'],
            'amount' => round((float) $row['amount'], 2), 'remarks' => $row['remarks'],
        ];
    }

    /**
     * Release 4.9.0CF: the opening is a ONE-TIME, permanent record. Inserts the one row and
     * returns true, or returns false (writing nothing) when an opening already exists.
     * There is no update and no remove: the model refuses both (see the callbacks below).
     */
    public function createOnce(string $date, float $amount, ?string $remarks): bool
    {
        if ($this->current() !== null) {
            return false;
        }

        try {
            $this->insert(['opening_date' => $date, 'amount' => round($amount, 2), 'remarks' => $remarks]);
        } catch (\Throwable $e) {
            // The UNIQUE lock_key rejects a second row that slipped in between the check and the insert.
            return false;
        }

        return true;
    }

    protected $beforeInsert = ['refuseSecondRow'];
    protected $beforeUpdate = ['refuseChange'];
    protected $beforeDelete = ['refuseChange'];

    protected function refuseSecondRow(array $data): array
    {
        if ($this->db->table($this->table)->countAllResults() > 0) {
            throw new \RuntimeException('Cash Opening Balance has already been set and is locked.');
        }

        return $data;
    }

    protected function refuseChange(array $data): array
    {
        throw new \RuntimeException('Cash Opening Balance has already been set and is locked.');
    }

    /**
     * Release 4.9.0CD: what a given opening date would exclude. Reads the existing
     * CashMovements derivation (unchanged) and reports the earliest movement and the
     * movements dated strictly before $date.
     *
     * @return array{earliest:?array,count:int,in:float,out:float,suggested:?string}
     */
    public static function excludedBy(string $date): array
    {
        $rows = (new \App\Libraries\CashMovements())->all();
        usort($rows, static fn ($a, $b) => [$a['date'], $a['seq']] <=> [$b['date'], $b['seq']]);

        $res = ['earliest' => $rows[0] ?? null, 'count' => 0, 'in' => 0.0, 'out' => 0.0, 'suggested' => null];
        foreach ($rows as $r) {
            if ($r['date'] < $date) {
                $res['count']++;
                $res['in']  += $r['in'];
                $res['out'] += $r['out'];
            }
        }
        if ($res['count'] > 0) {
            // First day of the earliest movement's month: a suggestion only, never applied automatically.
            $res['suggested'] = substr($rows[0]['date'], 0, 8) . '01';
        }

        return $res;
    }

    /**
     * The Cash Book / Monthly Statement rule, in one place. The opening applies to a
     * report that reaches its date: movements dated BEFORE the opening date are assumed
     * to be inside the declared figure and are ignored (counting them would double count).
     * A report that ends before the opening date is unaffected (the old walk from zero).
     */
    public static function effective(?array $opening, ?string $to): ?array
    {
        if ($opening === null || ($to !== null && $opening['opening_date'] > $to)) {
            return null;
        }

        return $opening;
    }
}
