<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.dataTables_wrapper .dataTables_paginate {
		margin-top: 10px;
		text-align: right;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button {
		background: #f1f5f9 !important;
		border: 1px solid #e2e8f0 !important;
		color: #334155 !important;
		padding: 4px 10px !important;
		margin: 2px !important;
		border-radius: 6px !important;
		font-size: 12px !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button.current {
		background: #2F7E8A !important;
		color: #fff !important;
		border: none !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
		background: #1e293b !important;
		color: #fff !important;
	}

	#baTable.table-custom th,
	#baTable.table-custom td { padding: 7px 10px; font-size: .78rem; }

	.ba-breadcrumb { margin-bottom: 8px; }
	.ba-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ba-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ba-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ba-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.badge-status {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 6px;
		font-size: .7rem;
		font-weight: 700;
	}
	.badge-status.status-active { background:#dcfce7; color:#166534; }
	.badge-status.status-inactive { background:#f1f5f9; color:#64748b; }
</style>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item active" aria-current="page">Bank Accounts</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-bank me-2"></i>Bank Accounts</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('bank-accounts/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Bank Account</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export (coming soon)" onclick="alert('Export will be available in a future release.')"><i class="bi bi-file-earmark-excel"></i> Export</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-bank"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_accounts) ?></div>
                <div class="kpi-label">Total Bank Accounts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_balance, 2) ?></div>
                <div class="kpi-label">Total Bank Balance</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_deposits, 2) ?></div>
                <div class="kpi-label">Total Deposits</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_withdrawals, 2) ?></div>
                <div class="kpi-label">Total Withdrawals</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="baTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Bank Name</th>
                    <th>Account Name</th>
                    <th>Account Number</th>
                    <th>IFSC</th>
                    <th style="text-align:right">Opening Balance</th>
                    <th style="text-align:right">Current Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="9" style="text-align:center; color:#94a3b8;">No bank accounts found.</td></tr>
                <?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
                    <td><a href="<?= base_url('bank-accounts/view/' . $a['id']) ?>" style="color:#2F7E8A; text-decoration:none;"><?= esc($a['bank_name']) ?></a></td>
                    <td><?= esc($a['account_name']) ?></td>
                    <td><?= esc($a['account_number']) ?></td>
                    <td><?= esc($a['ifsc_code'] ?: '-') ?></td>
                    <td style="text-align:right"><?= number_format((float) $a['opening_balance'], 2) ?></td>
                    <td style="text-align:right"><?= number_format((float) $a['current_balance'], 2) ?></td>
                    <td><span class="badge-status <?= $a['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <a href="<?= base_url('bank-statement?bank_account_id=' . (int) $a['id']) ?>" class="btn-view table-action-btn" title="View Statement"><i class="bi bi-journal-text"></i></a>
                        <a href="<?= base_url('bank-accounts/transactions?bank_account_id=' . (int) $a['id']) ?>" class="btn-view table-action-btn" title="View Transactions"><i class="bi bi-list-ul"></i></a>
                        <a href="<?= base_url('bank-accounts/edit/' . $a['id']) ?>" class="btn-edit table-action-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="javascript:void(0)" class="btn-delete table-action-btn" title="Delete" onclick="confirmDeleteAccount(<?= (int) $a['id'] ?>)"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	function confirmDeleteAccount(id) {
		if (!confirm('Delete this bank account? Accounts with transaction history cannot be deleted.')) return;

		$.ajax({
			url: "<?= base_url('bank-accounts/delete/') ?>" + id,
			type: 'POST',
			dataType: 'json'
		}).done(function (resp) {
			if (resp.status) {
				window.location.reload();
			} else {
				alert((resp.errors && resp.errors.join(' ')) || 'Failed to delete bank account.');
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			alert((data && data.errors && data.errors.join(' ')) || 'A network error occurred.');
		});
	}

	$(document).ready(function () {
		$('#baTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [[1, 'asc']],
			pageLength: 10,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No bank accounts found.'
			}
		});
	});
</script>
<?= $this->endSection() ?>
