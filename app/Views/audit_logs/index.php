<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$fmtDateTime = static fn ($d) => $d ? date('d-m-Y h:i A', strtotime($d)) : '—';
$count       = count($rows);

$actionBadge = static function (string $action): string {
    $map = [
        'CREATE'        => 'background:#dcfce7;color:#166534;',
        'UPDATE'        => 'background:#dbeafe;color:#1e40af;',
        'DELETE'        => 'background:#fee2e2;color:#991b1b;',
        'STATUS_CHANGE' => 'background:#fef3c7;color:#92400e;',
        'LOGIN'         => 'background:#e0e7ff;color:#3730a3;',
        'LOGOUT'        => 'background:#f1f5f9;color:#475569;',
    ];
    $style = $map[$action] ?? 'background:#f1f5f9;color:#475569;';
    return '<span class="badge" style="' . $style . 'font-weight:600;font-size:.7rem;">' . esc(str_replace('_', ' ', $action)) . '</span>';
};
?>

<style>
.al-table th, .al-table td { padding: 7px 10px; font-size: .78rem; }
@media print {
    .sidebar, .topbar, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; }
}
</style>

<nav aria-label="breadcrumb" class="no-print" style="margin-bottom:8px;">
    <ol class="breadcrumb" style="font-size:.78rem;background:transparent;padding:0;margin-bottom:0;">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Audit Logs</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-clock-history me-2"></i>Audit Logs</span>
    <div class="d-flex gap-2 no-print">
        <button type="button" class="btn-save" style="background:#6c757d;" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-calendar-day"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_today_logs) ?></div>
                <div class="kpi-label">Today Logs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-journal-text"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_logs) ?></div>
                <div class="kpi-label">Total Logs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-people"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_active_users_today) ?></div>
                <div class="kpi-label">Active Users Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-diagram-3"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_modules_today) ?></div>
                <div class="kpi-label">Modules Used Today</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3 no-print">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('audit-logs') ?>" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $f['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= esc($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Module</label>
                    <select name="module" class="form-control">
                        <option value="">All Modules</option>
                        <?php foreach ($modules as $m): ?>
                        <option value="<?= esc($m) ?>" <?= $f['module'] === $m ? 'selected' : '' ?>><?= esc($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-control">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?= $a ?>" <?= $f['action'] === $a ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $a)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= esc($f['date_from']) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= esc($f['date_to']) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Description, ref no, module..." value="<?= esc($f['search']) ?>">
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn-save"><i class="bi bi-funnel"></i> Filter</button>
                <a href="<?= base_url('audit-logs') ?>" class="btn-cancel"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (! empty($f['date_error'])): ?>
<div class="alert alert-warning py-2" style="font-size:.82rem;"><i class="bi bi-exclamation-triangle me-1"></i><?= esc($f['date_error']) ?></div>
<?php endif; ?>

<div class="card-custom">
    <div class="card-custom-header d-flex justify-content-between">
        <span>Audit Trail</span>
        <span><?= number_format($count) ?> record<?= $count === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive">
        <table id="alTable" class="table-custom al-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($count === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8;"><i class="bi bi-inbox"></i> No audit log entries found for the selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-order="<?= esc($r['created_at']) ?>"><?= $fmtDateTime($r['created_at']) ?></td>
                    <td><?= esc($r['username'] ?? 'System') ?></td>
                    <td><?= esc($r['module']) ?></td>
                    <td><?= $actionBadge($r['action']) ?></td>
                    <td><?= esc($r['reference_no'] ?? ($r['reference_type'] ? $r['reference_type'] . ' #' . $r['reference_id'] : '-')) ?></td>
                    <td><?= esc($r['description'] ?? '-') ?></td>
                    <td><?= esc($r['ip_address'] ?? '-') ?></td>
                    <td><a href="<?= base_url('audit-logs/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary" style="font-size:.72rem;padding:2px 8px;"><i class="bi bi-eye"></i> View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    $('#alTable').DataTable({
        paging: true,
        searching: false,
        lengthChange: false,
        info: false,
        ordering: true,
        order: [],
        pageLength: 25,
        dom: 'tp',
        language: {
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            },
            emptyTable: 'No audit log entries found for the selected filters.'
        }
    });
});
</script>
<?= $this->endSection() ?>
