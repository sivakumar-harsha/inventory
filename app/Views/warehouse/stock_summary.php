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
	.custom-search-box { max-width: 300px; }
	.custom-search-box input {
		border-radius: 8px;
		border: 1px solid #e2e8f0;
		padding: 6px 12px;
		font-size: 13px;
	}
	.custom-search-box input:focus {
		border-color: #2F7E8A;
		box-shadow: 0 0 0 2px rgba(47,126,138,0.15);
	}
	.search-icon { position: absolute; top: 8px; left: 9px; color: #94a3b8; font-size: 12px; }

	.wh-breadcrumb { margin-bottom: 8px; }
	.wh-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.wh-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.wh-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.wh-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.balance-neg { color: #dc2626; font-weight: 600; }
</style>

<nav aria-label="breadcrumb" class="wh-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Warehouse Stock Summary</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-boxes me-2"></i>Warehouse Stock Summary</span>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search products...">
	</div>
    <a href="<?= base_url('warehouse/stock-ledger') ?>" class="btn-cancel"><i class="bi bi-journal-text"></i> View Ledger</a>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_product_count) ?></div>
                <div class="kpi-label">Products in Stock</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_qty_in, 2) ?></div>
                <div class="kpi-label">Total Qty In</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_qty_out, 2) ?></div>
                <div class="kpi-label">Total Qty Out</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_balance, 2) ?></div>
                <div class="kpi-label">Available Balance</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="stockSummaryTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Product</th>
                    <th>HSN</th>
                    <th>Unit</th>
                    <th style="text-align:right">Total In</th>
                    <th style="text-align:right">Total Out</th>
                    <th style="text-align:right">Available Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $i => $p): ?>
                <tr>
                    <td class="sno-col"><?= $i + 1 ?></td>
                    <td><?= esc($p['product_name']) ?></td>
                    <td><?= esc($p['hsn_code'] ?: '-') ?></td>
                    <td><?= esc($p['unit'] ?: '-') ?></td>
                    <td style="text-align:right"><?= number_format($p['total_in'], 2) ?></td>
                    <td style="text-align:right"><?= number_format($p['total_out'], 2) ?></td>
                    <td style="text-align:right" class="<?= $p['balance'] < 0 ? 'balance-neg' : '' ?>"><?= number_format($p['balance'], 2) ?></td>
                    <td>
                        <a href="<?= base_url('warehouse/product-ledger/' . $p['product_id']) ?>" class="btn-view"><i class="bi bi-eye"></i> Ledger</a>
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
	$(document).ready(function () {
		var table = $('#stockSummaryTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true, order: [],
			pageLength: 10,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No warehouse stock movement found.'
			}
		});

		$('#customSearch').on('keyup', function () {
			table.search(this.value).draw();
		});
	});
</script>
<?= $this->endSection() ?>
