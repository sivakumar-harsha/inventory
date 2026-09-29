<?php
/**
 * Release 4.8.5E: the header toolbar shared by the five Loan Reports.
 *
 *   [ Filter ] [ Reset ]                     [ Print ] [ Export PDF ] [ Export Excel ]
 *
 * Filter submits the report's GET form, so the report page must give that form
 * id="lnFilterForm". Reset is the same report with no query string. Print is the
 * browser's print (every row, not just the DataTables page, and with the applied
 * filters spelled out). Export PDF and Export Excel both point at real download
 * routes with the current filters preserved.
 */
$lnResetUrl = base_url(uri_string());

// Release 4.8.7A/4.8.7B: PDF/Excel export point at the matching
// loan-reports/export/pdf|excel[...] route, with the report's own GET
// filters preserved as-is (page reload).
$lnUri      = trim((string) uri_string(), '/');
$lnBase     = 'loan-reports';
$lnSuffix   = trim(substr($lnUri, strlen($lnBase)), '/');
$lnPdfUrl   = base_url($lnBase . '/export/pdf' . ($lnSuffix !== '' ? '/' . $lnSuffix : ''));
$lnExcelUrl = base_url($lnBase . '/export/excel' . ($lnSuffix !== '' ? '/' . $lnSuffix : ''));
$lnQs       = $_SERVER['QUERY_STRING'] ?? '';
if ($lnQs !== '') {
    $lnPdfUrl   .= '?' . $lnQs;
    $lnExcelUrl .= '?' . $lnQs;
}
?>
<style>
	/* printing a Loan Report (this partial is only on the five report pages) */
	@media print {
		.main-content { margin-left: 0 !important; }   /* the shared print rule for this loses to the later sidebar-offset rule */
		.sr-breadcrumb, .dataTables_paginate { display: none !important; }
		.card-custom, .kpi-card { box-shadow: none; animation: none; }
		.page-content .row { margin-left: 0; margin-right: 0; }
		.row > [class*="col-"]:has(> .kpi-card) { flex: 0 0 auto; width: 25%; }
		.kpi-card { padding: 8px 10px; gap: 8px; }
		.kpi-icon { font-size: 1.2rem; }
	}
</style>
<div class="ln-toolbar ln-noprint">
    <div class="ln-toolbar-group">
        <button type="submit" form="lnFilterForm" class="btn-save"><i class="bi bi-funnel"></i> Filter</button>
        <a href="<?= esc($lnResetUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
    </div>
    <div class="ln-toolbar-group">
        <button type="button" class="btn-save" style="background:#6c757d;" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a href="<?= esc($lnPdfUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= esc($lnExcelUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
    </div>
</div>
<div id="lnNoticeHost" class="ln-noprint"></div>

<div class="ln-print-only" style="margin-bottom:10px;">
    <div style="font-size:.75rem;color:#64748b;">A&amp;A Inventory &middot; Printed <?= esc(date('d-m-Y H:i')) ?></div>
    <div id="lnPrintFilters" style="font-size:.75rem;margin-top:2px;"></div>
</div>

<script>
(function () {
	var host = document.getElementById('lnNoticeHost');

	// Export PDF / Export Excel are placeholders.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-ln-soon]') : null;
		if (!btn || !host) return;
		host.innerHTML = '<div class="alert alert-info alert-dismissible fade show py-2" role="alert" style="font-size:.82rem;">'
			+ '<i class="bi bi-info-circle me-1"></i><span></span>'
			+ '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
		host.querySelector('span').textContent = btn.getAttribute('data-ln-soon') + ' is not available yet. It will come in a future release.';
	});

	// The filters as the server applied them (the form's default state), for the printout.
	function filterSummary() {
		var form = document.getElementById('lnFilterForm'), parts = [];
		if (!form) return '';
		form.querySelectorAll('select, input').forEach(function (el) {
			var val = '';
			if (el.type === 'hidden' || el.type === 'submit') return;
			if (el.type === 'checkbox') {
				if (!el.defaultChecked) return;
				val = 'Yes';
			} else if (el.tagName === 'SELECT') {
				for (var i = 0; i < el.options.length; i++) {
					if (el.options[i].defaultSelected && el.options[i].value !== '') val = el.options[i].textContent.trim();
				}
			} else {
				val = (el.defaultValue || '').trim();
			}
			if (val === '') return;
			var box = el.closest('.form-section, .form-check'), lab = box ? box.querySelector('label') : null;
			parts.push((lab ? lab.textContent.replace(/\s+/g, ' ').trim() : el.name) + ': ' + val);
		});
		return parts.length ? 'Filters: ' + parts.join(' · ') : 'Filters: none (all records)';
	}

	// Print every row of a paged DataTable, then put the paging back.
	var widened = [];
	window.addEventListener('beforeprint', function () {
		var out = document.getElementById('lnPrintFilters');
		if (out) out.textContent = filterSummary();

		var $ = window.jQuery;
		if (!$ || !$.fn || !$.fn.dataTable) return;
		$('table.dataTable').each(function () {
			var t = $(this).DataTable();
			widened.push([t, t.page.len()]);
			t.page.len(-1).draw(false);
		});
	});
	window.addEventListener('afterprint', function () {
		widened.forEach(function (w) { w[0].page.len(w[1]).draw(false); });
		widened = [];
	});
})();
</script>
