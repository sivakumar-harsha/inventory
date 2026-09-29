<?php

/**
 * Release 4.8.5E: presentation helpers for the Loan module screens (views only).
 *
 * Everything here turns a value the Loan engine / reports already produced into
 * markup; nothing queries, calculates money or writes. One place decides how a
 * status looks, so the Loan list, Loan view, Payments, Ledger, Loan Reports and
 * the dashboard widgets all show the same colours:
 *
 *   Loan status  ACTIVE green · CLOSED gray
 *   EMI status   PENDING orange · PARTIAL blue · PAID green
 *   Due status   Due Today red · Upcoming blue · Overdue 1–30 orange ·
 *                Overdue 31–60 dark orange · Overdue 60+ red
 *
 * The .ln-badge* rules live in views/loans/partials/ui_styles.php.
 * Load with helper('loan_ui').
 */

if (! function_exists('ln_badge')) {
    /** A pill badge. $variant is one of the ln-badge-* suffixes. */
    function ln_badge(string $label, string $variant, string $title = ''): string
    {
        return '<span class="ln-badge ln-badge-' . $variant . '"'
            . ($title !== '' ? ' title="' . esc($title, 'attr') . '"' : '')
            . '>' . esc($label) . '</span>';
    }
}

if (! function_exists('ln_loan_status_badge')) {
    /** Loan status: ACTIVE green, CLOSED (or anything else) gray. */
    function ln_loan_status_badge(string $status): string
    {
        return $status === 'ACTIVE'
            ? ln_badge('ACTIVE', 'green')
            : ln_badge($status !== '' ? $status : 'CLOSED', 'gray');
    }
}

if (! function_exists('ln_emi_status_badge')) {
    /** EMI status: PENDING orange, PARTIAL blue, PAID green. */
    function ln_emi_status_badge(string $status): string
    {
        $variant = ['PENDING' => 'orange', 'PARTIAL' => 'blue', 'PAID' => 'green'][$status] ?? 'gray';

        return ln_badge($status, $variant);
    }
}

if (! function_exists('ln_due_band')) {
    /**
     * Band of an unpaid EMI from its days past due (negative = not due yet).
     * Same thresholds as LoanReports::_dueBand(), which the EMI Due report uses.
     */
    function ln_due_band(int $days): string
    {
        if ($days < 0) {
            return 'upcoming';
        }
        if ($days === 0) {
            return 'today';
        }
        if ($days <= 30) {
            return 'overdue-1-30';
        }
        if ($days <= 60) {
            return 'overdue-31-60';
        }

        return 'overdue-60-plus';
    }
}

if (! function_exists('ln_due_band_label')) {
    function ln_due_band_label(string $band): string
    {
        return [
            'today'           => 'Due Today',
            'upcoming'        => 'Upcoming',
            'overdue-1-30'    => 'Overdue 1–30',
            'overdue-31-60'   => 'Overdue 31–60',
            'overdue-60-plus' => 'Overdue 60+',
        ][$band] ?? $band;
    }
}

if (! function_exists('ln_due_variant')) {
    /** Due Today red · Upcoming blue · 1–30 orange · 31–60 dark orange · 60+ red. */
    function ln_due_variant(string $band): string
    {
        return [
            'today'           => 'red',
            'upcoming'        => 'blue',
            'overdue-1-30'    => 'orange',
            'overdue-31-60'   => 'darkorange',
            'overdue-60-plus' => 'red',
        ][$band] ?? 'gray';
    }
}

if (! function_exists('ln_due_badge')) {
    /** Due-status badge from a band the EMI Due report already worked out. */
    function ln_due_badge(string $band): string
    {
        return ln_badge(ln_due_band_label($band), ln_due_variant($band));
    }
}

if (! function_exists('ln_days_diff')) {
    /** Whole days $today is past $dueDate (Y-m-d): positive = overdue, 0 = today, negative = still to come. */
    function ln_days_diff(string $dueDate, ?string $today = null): int
    {
        $today = new \DateTime($today ?? 'today');

        return (int) (new \DateTime($dueDate))->diff($today)->format('%r%a');
    }
}

if (! function_exists('ln_days_badge')) {
    /** "12 days left" / "Due today" / "5 days overdue", coloured by the same due bands. */
    function ln_days_badge(string $dueDate, ?string $today = null): string
    {
        $days = ln_days_diff($dueDate, $today);
        $band = ln_due_band($days);
        $n    = abs($days);

        if ($days < 0) {
            $label = $n . ($n === 1 ? ' day left' : ' days left');
        } elseif ($days === 0) {
            $label = 'Due today';
        } else {
            $label = $n . ($n === 1 ? ' day overdue' : ' days overdue');
        }

        return ln_badge($label, ln_due_variant($band), ln_due_band_label($band));
    }
}

if (! function_exists('ln_method_badge')) {
    /** Payment method: CASH · BANK · CHEQUE · UPI · OTHER. */
    function ln_method_badge(string $method): string
    {
        $method = strtoupper(trim($method));
        $known  = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];

        return ln_badge($method !== '' ? $method : 'OTHER', 'm-' . strtolower(in_array($method, $known, true) ? $method : 'OTHER'));
    }
}

if (! function_exists('ln_has_filters')) {
    /** True when a report's (already validated) filter set narrows anything down. */
    function ln_has_filters(array $f): bool
    {
        foreach ($f as $key => $value) {
            if (in_array($key, ['dates_valid', 'date_error'], true)) {
                continue;
            }
            if ($value !== '' && $value !== null && $value !== false && $value !== 0) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('ln_empty_message')) {
    /** DataTables emptyTable HTML: says whether the filters or the data are why nothing shows. */
    function ln_empty_message(string $noun, bool $filtered): string
    {
        return '<div class="ln-empty"><i class="bi bi-inbox"></i>'
            . ($filtered
                ? 'No ' . esc($noun) . ' match the selected filters.<br><small>Use Reset to clear them.</small>'
                : 'No ' . esc($noun) . ' found.')
            . '</div>';
    }
}

if (! function_exists('ln_towards_badge')) {
    /** What a payment went towards: "EMI #n", or the Prepayment badge when it has no instalment. */
    function ln_towards_badge($emiId, $emiNo): string
    {
        return $emiId === null || (int) $emiId === 0
            ? ln_badge('Prepayment', 'prepay')
            : ln_badge('EMI #' . (int) $emiNo, 'emi');
    }
}
