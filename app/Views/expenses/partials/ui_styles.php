<?php
/**
 * Release 4.8.6E: Expense UI kit styles, shared by the Expense CRUD screens,
 * the Expense Reports and the dashboard Expense widget.
 *
 * Every selector is namespaced .exp-* (and only touches its own markup), so
 * the partial is safe to include anywhere and leaves the shared stylesheet
 * and every other module untouched.
 */
?>
<style>
	/* ---------- Badges ---------- */
	.exp-badge { display: inline-block; padding: 2px 9px; font-size: .7rem; font-weight: 600; line-height: 1.35; letter-spacing: .02em; white-space: nowrap; vertical-align: middle; border: 1px solid transparent; border-radius: 20px; }
	.exp-badge-green    { background: #dcfce7; color: #166534; border-color: #86efac; }  /* PAID */
	.exp-badge-gray     { background: #e2e8f0; color: #475569; border-color: #cbd5e1; }  /* CANCELLED */
	.exp-badge-m-cash   { background: #ecfccb; color: #3f6212; border-color: #bef264; }
	.exp-badge-m-bank   { background: #e0f2fe; color: #0369a1; border-color: #7dd3fc; }
	.exp-badge-m-cheque { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }
	.exp-badge-m-upi    { background: #ccfbf1; color: #0f766e; border-color: #5eead4; }
	.exp-badge-m-other  { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
	.exp-badge-category { background: #fef3c7; color: #92400e; border-color: #fde68a; }

	/* ---------- Header toolbar (Filter / Reset | Print / Export placeholders) ---------- */
	.exp-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; margin: 8px 0 12px; }
	.exp-toolbar-group { display: flex; flex-wrap: wrap; gap: 8px; }
	.exp-toolbar button { font-family: inherit; }

	/* ---------- Tables: record count, sticky header, horizontal scroll, empty state ---------- */
	.exp-card-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
	.exp-count { font-size: .75rem; font-weight: 600; color: #64748b; white-space: nowrap; }
	.exp-scroll { overflow: auto; max-height: 70vh; -webkit-overflow-scrolling: touch; }
	/* the second selector out-ranks DataTables' own `th.sorting { position: relative }`, which loads after this block */
	.exp-sticky thead th,
	.exp-scroll.exp-sticky table.dataTable thead > tr > th { position: sticky; top: 0; z-index: 2; background-color: #f8fafc; box-shadow: inset 0 -1px 0 #e2e8f0; }
	.exp-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.exp-empty i { display: block; margin-bottom: 6px; font-size: 1.6rem; color: #cbd5e1; }
	.exp-scroll td.dataTables_empty { padding: 0 !important; }

	/* ---------- Applied-filters chip (non-print summary of the current GET filters) ---------- */
	.exp-filter-chip { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 20px; padding: 4px 12px; font-size: .74rem; color: #475569; margin: 0 6px 6px 0; }

	/* ---------- Small pieces ---------- */
	.exp-kpi-sub { margin-top: 1px; font-size: .68rem; color: #94a3b8; }
	.exp-sub-line { display: block; color: #94a3b8; font-size: .68rem; }
	.exp-row-link { cursor: pointer; }
	.exp-row-link:focus-visible { outline: 2px solid #2F7E8A; outline-offset: -2px; }
	.exp-dash-scroll { max-height: 260px; overflow: auto; }
	.exp-print-only { display: none; }

	.exp-breadcrumb { margin-bottom: 8px; }
	.exp-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.exp-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.exp-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.exp-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.exp-subnav { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
	.exp-subnav a { padding: 6px 12px; font-size: .78rem; border-radius: 20px; background: #f1f5f9; color: #334155; text-decoration: none; border: 1px solid #e2e8f0; }
	.exp-subnav a.active { background: #2F7E8A; color: #fff; border-color: #2F7E8A; }
	.exp-subnav a:hover { background: #e2e8f0; }
	.exp-subnav a.active:hover { background: #256b75; }

	/* ---------- Print: only the report itself ---------- */
	/* Only .exp-* rules here, so including this on the dashboard changes nothing about how the existing cards print. */
	@media print {
		.exp-noprint, .exp-toolbar, .exp-breadcrumb, .exp-subnav { display: none !important; }
		.exp-print-only { display: block; }
		.exp-scroll { max-height: none !important; overflow: visible !important; }
		.exp-sticky thead th,
		.exp-scroll.exp-sticky table.dataTable thead > tr > th { position: static; box-shadow: none; }
		.exp-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		/* fit A4 portrait: text cells wrap instead of running off the page, dates / numbers / links stay whole,
		   compact type, no sort arrows, totals row once at the end */
		.exp-scroll table { width: 100% !important; min-width: 0 !important; }
		.exp-scroll table th, .exp-scroll table td { white-space: normal !important; padding: 3px 4px !important; font-size: .66rem !important; }
		.exp-scroll table td[data-order], .exp-scroll table td.num, .exp-scroll table td:first-child, .exp-scroll table td a { white-space: nowrap !important; }
		.exp-scroll table.dataTable thead th::before, .exp-scroll table.dataTable thead th::after { display: none !important; }
		.exp-scroll table tfoot { display: table-row-group; }
	}

	@media (max-width: 575.98px) {
		.exp-toolbar-group { width: 100%; }
		.exp-toolbar-group > * { flex: 1 1 auto; justify-content: center; }
	}
</style>
