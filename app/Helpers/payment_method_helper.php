<?php

/**
 * Release 4.9.0CB: one display mapping for the payment method stored on a
 * transaction (payments.method, project_cash_receipts.payment_method,
 * service_receipts.payment_mode, supplier_payments.payment_method, ...).
 *
 * Presentation only. Stored values are never changed and never inferred: a
 * NULL / blank / unrecognised value is reported as "not recorded", it is
 * never treated as CASH or BANK. The ledger a row appears in (where the money
 * moved) is a separate question from the method (how it was paid).
 */

if (! function_exists('pm_key')) {
    /** Normalised key: 'Bank Transfer', 'bank-transfer' and 'BANK_TRANSFER' all become BANK_TRANSFER; '' when unknown. */
    function pm_key($raw): string
    {
        $k = strtoupper(trim((string) $raw));
        $k = (string) preg_replace('/[\s\-]+/', '_', $k);

        return $k;
    }
}

if (! function_exists('pm_label')) {
    /**
     * Display label for a stored method. $blank is returned when the source has
     * no method (e.g. an invoice row, or a legacy record): '—' by default, or
     * pass 'Not recorded' on payment rows where the method should exist.
     */
    function pm_label($raw, string $blank = '—'): string
    {
        $k = pm_key($raw);
        if ($k === '') {
            return $blank;
        }

        static $map = [
            'CASH'          => 'Cash',
            'BANK'          => 'Bank',
            'BANK_TRANSFER' => 'Bank Transfer',
            'UPI'           => 'UPI',
            'CHECK'         => 'Cheque',
            'CHEQUE'        => 'Cheque',
            'OTHER'         => 'Other',
        ];

        // An unrecognised stored value is shown as stored (tidied), never guessed.
        return $map[$k] ?? ucwords(strtolower(str_replace('_', ' ', $k)));
    }
}

if (! function_exists('pm_badge')) {
    /** Compact neutral badge for HTML tables; a plain dash when there is no method. */
    function pm_badge($raw, string $blank = '—'): string
    {
        $label = pm_label($raw, $blank);
        if (pm_key($raw) === '') {
            return '<span class="pm-none">' . esc($label) . '</span>';
        }

        return '<span class="pm-badge">' . esc($label) . '</span>';
    }
}
