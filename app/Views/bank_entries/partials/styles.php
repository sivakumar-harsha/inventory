<?php
/**
 * Release 4.8.4I: styles shared by the manual bank ledger screens (Deposit,
 * Withdrawal, Transfer, Daybook, Statement). The breadcrumb, DataTables pager
 * and search-box rules are the same ones bank_accounts/index.php carries
 * inline; everything new is namespaced .be-* so it only touches this markup.
 */
?>
<style>
	.ba-breadcrumb { margin-bottom: 8px; }
	.ba-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ba-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ba-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ba-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.dataTables_wrapper .dataTables_paginate { margin-top: 10px; text-align: right; }
	.dataTables_wrapper .dataTables_paginate .paginate_button { background: #f1f5f9 !important; border: 1px solid #e2e8f0 !important; color: #334155 !important; padding: 4px 10px !important; margin: 2px !important; border-radius: 6px !important; font-size: 12px !important; }
	.dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #2F7E8A !important; color: #fff !important; border: none !important; }
	.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #1e293b !important; color: #fff !important; }

	.custom-search-box { max-width: 300px; }
	.custom-search-box input { border-radius: 8px; border: 1px solid #e2e8f0; padding: 6px 12px; font-size: 13px; }
	.custom-search-box input:focus { border-color: #2F7E8A; box-shadow: 0 0 0 2px rgba(47,126,138,0.15); }
	.search-icon { position: absolute; top: 34px; left: 9px; color: #94a3b8; font-size: 12px; }

	.be-table.table-custom th,
	.be-table.table-custom td { padding: 7px 10px; font-size: .78rem; }
	.be-scroll { overflow-x: auto; }
	.be-num { text-align: right; white-space: nowrap; }
	.be-num-in { color: #166534; }
	.be-num-out { color: #991b1b; }
	.be-muted { color: #94a3b8; }
	.be-sub-line { display: block; color: #94a3b8; font-size: .68rem; }
	.be-wrap { white-space: normal; min-width: 160px; }

	.be-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: .7rem; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
	.be-badge-gray   { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
	.be-badge-green  { background: #dcfce7; color: #166534; border-color: #86efac; }
	.be-badge-red    { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
	.be-badge-blue   { background: #dbeafe; color: #1e3a8a; border-color: #93c5fd; }
	.be-badge-amber  { background: #fef3c7; color: #92400e; border-color: #fde68a; }
	.be-badge-purple { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }

	.be-preview { border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px 14px; background: #f8fafc; }
	.be-preview-row { display: flex; justify-content: space-between; gap: 12px; font-size: .8rem; color: #475569; }
	.be-preview-row + .be-preview-row { margin-top: 4px; }
	.be-preview-after { font-weight: 700; }
	.be-preview-after.negative { color: #b91c1c; }
	.be-preview-warning { color: #b91c1c; font-size: .78rem; margin-top: 6px; }

	.be-filter .form-label { font-size: .72rem; margin-bottom: 3px; }
	.be-filter .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }
	.be-kpi-sub { margin-top: 1px; font-size: .68rem; color: #94a3b8; }

	@media print {
		.ba-breadcrumb, .be-noprint, .page-title a, .be-filter, .main-sidebar, .main-header { display: none !important; }
		.be-scroll { overflow: visible !important; }
	}
</style>
