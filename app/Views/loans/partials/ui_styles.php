<?php
/**
 * Release 4.8.5E: Loan UI kit styles, shared by the Loan screens, the Loan
 * Reports and the dashboard loan widgets.
 *
 * Every selector is namespaced .ln-* (and only touches its own markup), so the
 * partial is safe to include anywhere and leaves the shared stylesheet and every
 * other module untouched. Badge markup comes from helpers/loan_ui_helper.php.
 */
?>
<style>
	/* ---------- Badges (see loan_ui_helper.php for what maps to what) ---------- */
	.ln-badge { display: inline-block; padding: 2px 9px; font-size: .7rem; font-weight: 600; line-height: 1.35; letter-spacing: .02em; white-space: nowrap; vertical-align: middle; border: 1px solid transparent; border-radius: 20px; }
	.ln-badge-green      { background: #dcfce7; color: #166534; border-color: #86efac; }
	.ln-badge-gray       { background: #e2e8f0; color: #475569; border-color: #cbd5e1; }
	.ln-badge-orange     { background: #ffedd5; color: #c2410c; border-color: #fdba74; }
	.ln-badge-blue       { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
	.ln-badge-darkorange { background: #c2410c; color: #fff;    border-color: #9a3412; }
	.ln-badge-red        { background: #dc2626; color: #fff;    border-color: #b91c1c; }   /* the top of the due scale: darker than orange, like dark orange */
	.ln-badge-prepay     { background: #fef9c3; color: #854d0e; border-color: #fde047; }
	.ln-badge-emi        { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }
	.ln-badge-m-cash     { background: #ecfccb; color: #3f6212; border-color: #bef264; }
	.ln-badge-m-bank     { background: #e0f2fe; color: #0369a1; border-color: #7dd3fc; }
	.ln-badge-m-cheque   { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }
	.ln-badge-m-upi      { background: #ccfbf1; color: #0f766e; border-color: #5eead4; }
	.ln-badge-m-other    { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }

	/* ---------- Header toolbar (Filter / Reset | Print / Export placeholders) ---------- */
	.ln-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; margin: 8px 0 12px; }
	.ln-toolbar-group { display: flex; flex-wrap: wrap; gap: 8px; }
	.ln-toolbar button { font-family: inherit; }

	/* ---------- Tables: record count, sticky header, horizontal scroll, empty state ---------- */
	.ln-card-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
	.ln-count { font-size: .75rem; font-weight: 600; color: #64748b; white-space: nowrap; }
	.ln-scroll { overflow: auto; max-height: 70vh; -webkit-overflow-scrolling: touch; }
	/* the second selector out-ranks DataTables' own `th.sorting { position: relative }`, which loads after this block */
	.ln-sticky thead th,
	.ln-scroll.ln-sticky table.dataTable thead > tr > th { position: sticky; top: 0; z-index: 2; background-color: #f8fafc; box-shadow: inset 0 -1px 0 #e2e8f0; }
	.ln-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.ln-empty i { display: block; margin-bottom: 6px; font-size: 1.6rem; color: #cbd5e1; }
	.ln-scroll td.dataTables_empty { padding: 0 !important; }

	/* ---------- Small pieces ---------- */
	.ln-kpi-sub { margin-top: 1px; font-size: .68rem; color: #94a3b8; }
	.ln-sub-line { display: block; color: #94a3b8; font-size: .68rem; }
	.ln-row-link { cursor: pointer; }
	.ln-row-link:focus-visible { outline: 2px solid #2F7E8A; outline-offset: -2px; }
	.ln-dash-scroll { max-height: 260px; overflow: auto; }
	.ln-print-only { display: none; }

	/* ---------- Print: only the report itself ---------- */
	/* Only .ln-* rules here, so including this on the dashboard changes nothing about how the existing cards print. */
	@media print {
		.ln-noprint, .ln-toolbar, .ln-breadcrumb { display: none !important; }
		.ln-print-only { display: block; }
		.ln-scroll { max-height: none !important; overflow: visible !important; }
		.ln-sticky thead th,
		.ln-scroll.ln-sticky table.dataTable thead > tr > th { position: static; box-shadow: none; }
		.ln-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		/* fit A4 portrait: text cells wrap instead of running off the page, dates / numbers / loan links stay whole,
		   compact type, no sort arrows, totals row once at the end */
		.ln-scroll table { width: 100% !important; min-width: 0 !important; }
		.ln-scroll table th, .ln-scroll table td { white-space: normal !important; padding: 3px 4px !important; font-size: .66rem !important; }
		.ln-scroll table td[data-order], .ln-scroll table td.num, .ln-scroll table td:first-child, .ln-scroll table td a { white-space: nowrap !important; }
		.ln-scroll table.dataTable thead th::before, .ln-scroll table.dataTable thead th::after { display: none !important; }
		.ln-scroll table tfoot { display: table-row-group; }
	}

	@media (max-width: 575.98px) {
		.ln-toolbar-group { width: 100%; }
		.ln-toolbar-group > * { flex: 1 1 auto; justify-content: center; }
	}
</style>
