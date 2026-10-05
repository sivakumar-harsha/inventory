<?php
/**
 * Release 4.9.0F: header shared by the four Expense summary reports —
 * breadcrumb, title + one-line description, sibling tabs, the From/To +
 * export row (expenses/partials/report_toolbar) and the scope chip.
 */
$erPath  = trim(uri_string(), '/');
$erPages = [
    'expense-reports/category-summary' => ['Category Summary', 'bi-tags', 'Paid expenses grouped by category'],
    'expense-reports/project-summary'  => ['Project Summary', 'bi-kanban', 'Paid expenses grouped by project'],
    'expense-reports/payment-summary'  => ['Payment Method Summary', 'bi-credit-card-2-front', 'Paid expenses grouped by payment method'],
    'expense-reports/monthly-summary'  => ['Monthly Summary', 'bi-calendar3', 'Paid expenses grouped by month'],
];
[$erTitle, $erIcon, $erDesc] = $erPages[$erPath] ?? [$title ?? 'Expense Summary', 'bi-bar-chart', ''];
$erRange  = http_build_query(array_filter(['date_from' => $f['date_from'] ?? '', 'date_to' => $f['date_to'] ?? ''], 'strlen'));
$erPeriod = ($f['date_from'] ?? '') === '' && ($f['date_to'] ?? '') === ''
    ? 'All dates'
    : (($f['date_from'] !== '' ? date('d-m-Y', strtotime($f['date_from'])) : 'Start') . ' to ' . ($f['date_to'] !== '' ? date('d-m-Y', strtotime($f['date_to'])) : 'Today'));
?>
<nav aria-label="breadcrumb" class="exp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($erTitle) ?></li>
    </ol>
</nav>

<div class="page-title mb-0">
    <span class="d-flex align-items-center"><i class="bi <?= esc($erIcon, 'attr') ?> me-2"></i><?= esc($erTitle) ?></span>
</div>
<div class="rpt-desc exp-noprint"><?= esc($erDesc) ?>. Cancelled expenses are excluded.</div>

<div class="exp-subnav exp-noprint">
    <?php foreach ($erPages as $path => $p): ?>
    <a href="<?= esc(base_url($path) . ($erRange !== '' ? '?' . $erRange : ''), 'attr') ?>" class="<?= $erPath === $path ? 'active' : '' ?>"><?= esc($p[0]) ?></a>
    <?php endforeach; ?>
</div>

<?= $this->include('expenses/partials/report_toolbar') ?>

<?php if (! empty($warning)): ?>
<div class="alert alert-warning py-2 exp-noprint" style="font-size:.8rem;"><i class="bi bi-exclamation-triangle me-1"></i><?= esc($warning) ?></div>
<?php endif; ?>

<div class="exp-noprint mb-2">
    <span class="exp-filter-chip"><i class="bi bi-calendar-range" style="font-size:.68rem;"></i><?= esc($erDesc) ?> &middot; <?= esc($erPeriod) ?></span>
</div>
