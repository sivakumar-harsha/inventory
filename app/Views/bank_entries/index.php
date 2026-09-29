<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.4I: one list page for Bank Deposits / Withdrawals / Transfers /
 * Daybook, driven by the controller's $cfg (columns, labels, slug). Server-
 * rendered like bank_accounts/index.php; DataTables adds search + paging.
 */
$slug = $cfg['slug'];

$formatLabel = static fn ($value) => ucwords(strtolower(str_replace('_', ' ', (string) $value)));
?>

<?= $this->include('bank_entries/partials/styles') ?>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($cfg['title']) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi <?= esc($cfg['icon'], 'attr') ?> me-2"></i><?= esc($cfg['title']) ?></span>
    <div class="d-flex gap-2">
        <a href="<?= base_url($slug . '/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New <?= esc($cfg['singular']) ?></a>
        <a href="<?= base_url('bank-statement') ?>" class="btn-cancel"><i class="bi bi-journal-text"></i> Bank Statement</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <?php foreach ($kpis as $kpi): ?>
    <div class="col-6 col-md-4">
        <div class="kpi-card <?= esc($kpi['class'], 'attr') ?>">
            <div class="kpi-icon"><i class="bi <?= esc($kpi['icon'], 'attr') ?>"></i></div>
            <div>
                <div class="kpi-value"><?= esc($kpi['value']) ?></div>
                <div class="kpi-label"><?= esc($kpi['label']) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card-custom mb-3 be-filter">
    <div class="card-custom-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <div class="custom-search-box position-relative">
                    <label class="form-label">Search</label>
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="customSearch" class="form-control ps-4" placeholder="Search <?= esc(strtolower($cfg['plural']), 'attr') ?>...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" id="filterFromDate" class="form-control">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" id="filterToDate" class="form-control">
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="be-scroll">
        <table id="beTable" class="table-custom be-table">
            <thead>
                <tr>
                    <?php foreach ($cfg['columns'] as $column): ?>
                    <th<?= $column['type'] === 'money' ? ' class="be-num"' : '' ?>><?= esc($column['label']) ?></th>
                    <?php endforeach; ?>
                    <th class="be-noprint">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr data-date="<?= esc($entry['transaction_date'], 'attr') ?>">
                    <?php foreach ($cfg['columns'] as $column): ?>
                    <?php $value = $entry[$column['key']] ?? null; ?>
                    <?php if ($column['type'] === 'money'): ?>
                        <?php $signClass = isset($column['signed_by']) ? ($entry[$column['signed_by']] === 'CREDIT' ? 'be-num-in' : 'be-num-out') : ''; ?>
                        <td class="be-num <?= $signClass ?>" data-order="<?= (float) $value ?>"><?= number_format((float) $value, 2) ?></td>
                    <?php elseif ($column['type'] === 'date'): ?>
                        <td data-order="<?= esc($value, 'attr') ?>"><?= esc(date('d-m-Y', strtotime((string) $value))) ?></td>
                    <?php elseif ($column['type'] === 'badge'): ?>
                        <td><?php if ($value): ?><span class="be-badge <?= esc($column['badges'][$value] ?? 'be-badge-gray', 'attr') ?>"><?= esc($formatLabel($value)) ?></span><?php else: ?><span class="be-muted">-</span><?php endif; ?></td>
                    <?php else: ?>
                        <td><?= $value !== null && $value !== '' ? esc($value) : '<span class="be-muted">-</span>' ?></td>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <td class="be-noprint" style="white-space:nowrap;">
                        <a href="<?= base_url($slug . '/view/' . $entry['id']) ?>" class="btn-view" title="View"><i class="bi bi-eye"></i></a>
                        <a href="<?= base_url($slug . '/edit/' . $entry['id']) ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="javascript:void(0)" class="btn-delete" title="Delete" onclick="confirmDeleteEntry(<?= (int) $entry['id'] ?>)"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($entries)): ?>
        <div class="be-muted" style="text-align:center; padding:26px 12px;">No <?= esc(strtolower($cfg['plural'])) ?> recorded yet.</div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	function confirmDeleteEntry(id) {
		if (!confirm('Delete this <?= esc(strtolower($cfg['singular']), 'js') ?>? The bank balance will be reversed.')) return;

		$.ajax({
			url: "<?= base_url($slug . '/delete/') ?>" + id,
			type: 'POST',
			dataType: 'json'
		}).done(function (resp) {
			if (resp.status) {
				window.location.reload();
			} else {
				alert((resp.errors && resp.errors.join(' ')) || 'Failed to delete.');
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			alert((data && data.errors && data.errors.join(' ')) || 'A network error occurred.');
		});
	}

	$(document).ready(function () {
		<?php if (! empty($entries)): ?>
		var table = $('#beTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [],
			pageLength: 15,
			dom: 'tp',
			columnDefs: [{ orderable: false, targets: -1 }],
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				}
			}
		});

		$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
			if (settings.nTable.id !== 'beTable') return true;
			var from = $('#filterFromDate').val();
			var to   = $('#filterToDate').val();
			var date = $(table.row(dataIndex).node()).attr('data-date');
			if (from && date < from) return false;
			if (to && date > to) return false;
			return true;
		});

		$('#customSearch').on('keyup', function () {
			table.search(this.value).draw();
		});
		$('#filterFromDate, #filterToDate').on('change', function () {
			table.draw();
		});
		<?php endif; ?>
	});
</script>
<?= $this->endSection() ?>
