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

	#productLedgerTable.table-custom th,
	#productLedgerTable.table-custom td { padding: 7px 10px; font-size: .78rem; }

	.wh-breadcrumb { margin-bottom: 8px; }
	.wh-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.wh-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.wh-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.wh-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.badge-txn { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; }
	.badge-txn.badge-in  { background:#dcfce7; color:#166534; }
	.badge-txn.badge-out { background:#fee2e2; color:#991b1b; }

	.balance-neg { color: #dc2626; font-weight: 600; }
</style>

<nav aria-label="breadcrumb" class="wh-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('warehouse/stock-summary') ?>">Warehouse Stock Summary</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($product['name']) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-box me-2"></i>Product Ledger — <?= esc($product['name']) ?></span>
    <a href="<?= base_url('warehouse/stock-summary') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back to Summary</a>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_in, 2) ?></div>
                <div class="kpi-label">Total Qty In</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_out, 2) ?></div>
                <div class="kpi-label">Total Qty Out</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_balance, 2) ?></div>
                <div class="kpi-label">Available Balance</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-custom-header">
        <?= esc($product['name']) ?>
        <?php if ($product['hsn_code']): ?> &middot; HSN <?= esc($product['hsn_code']) ?><?php endif; ?>
        &middot; Unit: <?= esc($product['unit'] ?: '-') ?>
    </div>
    <div class="table-responsive">
        <table id="productLedgerTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th style="text-align:right">Quantity</th>
                    <th style="text-align:right">Balance</th>
                    <th>Reference</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledger as $row): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
                    <td><?= esc($row['transaction_date']) ?></td>
                    <td><span class="badge-txn badge-<?= strtolower($row['transaction_type']) ?>"><?= esc($row['transaction_type']) ?></span></td>
                    <td style="text-align:right"><?= number_format($row['quantity'], 2) ?> <?= esc($product['unit'] ?: '') ?></td>
                    <td style="text-align:right" class="<?= $row['balance'] < 0 ? 'balance-neg' : '' ?>"><?= number_format($row['balance'], 2) ?></td>
                    <td>
                        <?php if ($row['reference_type'] === 'GENERAL_PURCHASE'): ?>
                            <a href="<?= base_url('general-purchases/view/' . $row['reference_id']) ?>"><?= esc($row['reference_type']) ?> #<?= (int) $row['reference_id'] ?></a>
                        <?php elseif ($row['reference_type'] === 'PURCHASE'): ?>
                            <a href="<?= base_url('purchases/view/' . $row['reference_id']) ?>"><?= esc($row['reference_type']) ?> #<?= (int) $row['reference_id'] ?></a>
                        <?php else: ?>
                            <?= esc($row['reference_type'] ?: '-') ?><?= $row['reference_id'] ? ' #' . (int) $row['reference_id'] : '' ?>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($row['notes'] ?: '-') ?></td>
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
		$('#productLedgerTable').DataTable({
			paging: true,
			searching: false,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [[1, 'asc']],
			pageLength: 15,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No movement recorded for this product.'
			}
		});
	});
</script>
<?= $this->endSection() ?>
