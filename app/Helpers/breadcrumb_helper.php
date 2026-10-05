<?php

/**
 * Release 4.9.0DG: global breadcrumb for the top header.
 *
 * The layout renders the page content first, then calls topbar_breadcrumb_split() to lift the page's own
 * breadcrumb out of the content (so it is shown once, in the top header, and never inside the page).
 * Pages that carry no breadcrumb get one generated from the request path by topbar_breadcrumb_fallback().
 * View-only helper: no data, no business rules.
 */

if (! function_exists('topbar_breadcrumb_split')) {
    /**
     * Removes every breadcrumb <nav> (or bare breadcrumb <ol>) from the page HTML and returns the first one found.
     *
     * @return array{0:string,1:string} [breadcrumb html or '', page html without any breadcrumb]
     */
    function topbar_breadcrumb_split(string $html): array
    {
        $found = '';
        $out   = preg_replace_callback(
            '#<nav\b[^>]*aria-label=["\']breadcrumb["\'][^>]*>.*?</nav>#is',
            static function (array $m) use (&$found): string {
                if ($found === '') {
                    $found = $m[0];
                }
                return '';
            },
            $html
        );
        $out = $out ?? $html;

        $out2 = preg_replace_callback(
            '#<ol\b[^>]*class=["\'][^"\']*\bbreadcrumb\b[^"\']*["\'][^>]*>.*?</ol>#is',
            static function (array $m) use (&$found): string {
                if ($found === '') {
                    $found = '<nav aria-label="breadcrumb">' . $m[0] . '</nav>';
                }
                return '';
            },
            $out
        );

        return [$found, $out2 ?? $out];
    }
}

if (! function_exists('topbar_breadcrumb_fallback')) {
    /**
     * Home / Section / Page breadcrumb for a page that has no breadcrumb of its own, derived from the request path
     * and the sidebar's own grouping. Returns '' for the dashboard and for unknown paths.
     */
    function topbar_breadcrumb_fallback(string $path): string
    {
        $path = trim($path, '/');
        if ($path === 'reports') {
            return '<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="' . esc(base_url('dashboard')) . '">Home</a></li><li class="breadcrumb-item active" aria-current="page">Reports</li></ol></nav>';
        }
        // prefix => [section group, label, link of the section's own page when shown as a parent]
        $map = [
            'projects/statements'  => ['CRM', 'Project Statements', 'projects/statements'],
            'projects/statement'   => ['CRM', 'Project Statements', 'projects/statements'],
            'projects'             => ['CRM', 'Projects', 'projects'],
            'customers'            => ['CRM', 'Customers', 'customers'],
            'customer-payments'    => ['Transactions', 'Customer Payments', 'customer-payments'],
            'products'             => ['Inventory', 'Products', 'products'],
            'suppliers'            => ['Inventory', 'Suppliers', 'suppliers'],
            'expense-categories'   => ['Masters', 'Expense Categories', 'expense-categories'],
            'purchases'            => ['Transactions', 'Purchases', 'purchases'],
            'stock-entry'          => ['Transactions', 'Stock Entry', 'stock-entry'],
            'sales'                => ['Transactions', 'Sales', 'sales'],
            'payments'             => ['Transactions', 'Payments', 'payments'],
            'reports/profit-loss'  => ['Reports', 'Profit & Loss', null],
            'reports/balance-sheet' => ['Reports', 'Balance Sheet', null],
            'reports/sales'        => ['Reports', 'Sales Report', null],
            'reports/purchases'    => ['Reports', 'Purchase Report', null],
            'reports/stock'        => ['Reports', 'Stock Summary Report', null],
            'reports/ledger'       => ['Reports', 'Stock Ledger', null],
        ];

        $hit = null;
        $rest = '';
        foreach ($map as $prefix => $def) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                if ($hit === null || strlen($prefix) > strlen($hit[0])) {
                    $hit  = [$prefix, $def];
                    $rest = trim(substr($path, strlen($prefix)), '/');
                }
            }
        }
        if ($hit === null) {
            return '';
        }

        [$prefix, [$section, $label, $link]] = $hit;
        $actions = ['create' => 'New', 'edit' => 'Edit', 'view' => 'View', 'new' => 'New'];
        $first   = $rest === '' ? '' : explode('/', $rest)[0];
        $action  = $actions[$first] ?? null;

        $items = ['<li class="breadcrumb-item"><a href="' . esc(base_url('dashboard')) . '">Home</a></li>'];
        if ($section === 'Reports') {
            $items[] = '<li class="breadcrumb-item"><a href="' . esc(base_url('reports')) . '">Reports</a></li>';
        } else {
            $items[] = '<li class="breadcrumb-item">' . esc($section) . '</li>';
        }
        if ($action !== null && $link !== null) {
            $items[] = '<li class="breadcrumb-item"><a href="' . esc(base_url($link)) . '">' . esc($label) . '</a></li>';
            $items[] = '<li class="breadcrumb-item active" aria-current="page">' . esc($action) . '</li>';
        } else {
            $items[] = '<li class="breadcrumb-item active" aria-current="page">' . esc($label) . '</li>';
        }

        return '<nav aria-label="breadcrumb"><ol class="breadcrumb">' . implode('', $items) . '</ol></nav>';
    }
}
