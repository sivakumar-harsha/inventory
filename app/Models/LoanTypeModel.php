<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Loan Type master (loan_types). `code` is the stable identifier stored in
 * loans.loan_type (BANK, PERSONAL, VEHICLE, OD, OTHER and any added later);
 * `label` is the user-facing name. Business rules test the code, never the label.
 *
 * The static readers fall back to the five original types while the loan_types
 * table does not exist yet, so screens keep working until the migration is applied.
 */
class LoanTypeModel extends Model
{
    protected $table         = 'loan_types';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['code', 'label', 'status'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const DEFAULTS = [
        'BANK'     => 'Bank Loan',
        'PERSONAL' => 'Personal',
        'VEHICLE'  => 'Vehicle',
        'OD'       => 'Overdraft',
        'OTHER'    => 'Other',
    ];

    public static function ready(): bool
    {
        return \Config\Database::connect()->tableExists('loan_types');
    }

    /** code => label for every type (including INACTIVE, so old loans still render). */
    public static function labels(): array
    {
        return self::read(false);
    }

    /** code => label for ACTIVE types only (dropdown choices). */
    public static function activeLabels(): array
    {
        return self::read(true);
    }

    /** Every code, or only ACTIVE ones. */
    public static function codes(bool $activeOnly = false): array
    {
        return array_keys(self::read($activeOnly));
    }

    private static function read(bool $activeOnly): array
    {
        if (! self::ready()) {
            return self::DEFAULTS;
        }

        $b = \Config\Database::connect()->table('loan_types')->select('code, label')->orderBy('id', 'ASC');
        if ($activeOnly) {
            $b->where('status', 'ACTIVE');
        }

        $out = [];
        foreach ($b->get()->getResultArray() as $r) {
            $out[$r['code']] = $r['label'];
        }

        return $out;
    }

    /** Stable code for a new label: A-Z0-9_ only, max 30 chars, unique in the master. */
    public function newCode(string $label): string
    {
        $base = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $label), '_'));
        $base = $base === '' ? 'TYPE' : substr($base, 0, 30);

        $code = $base;
        for ($i = 2; $this->where('code', $code)->first(); $i++) {
            $suffix = '_' . $i;
            $code   = substr($base, 0, 30 - strlen($suffix)) . $suffix;
        }

        return $code;
    }
}
