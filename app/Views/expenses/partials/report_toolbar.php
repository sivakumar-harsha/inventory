<?php
/**
 * Release 4.8.6E: the header toolbar shared by the five Expense Reports.
 *
 *   [ Filter ] [ Reset ]                     [ Print ] [ Export PDF ] [ Export Excel ]
 *
 * Filter/Reset only render on the Expense Register (the one report page with a
 * filter form, id="erFilterForm") — the four summary pages skip that group.
 * Print is the browser's print (every row, not just the DataTables page, with
 * the applied filters spelled out). Export PDF and Export Excel both point at
 * real download routes with the current filters preserved.
 */
$expShowFilterGroup = isset($f);

// Release 4.8.7A/4.8.7B: PDF/Excel export point at the matching
// expense-reports/export/pdf|excel[...] route, with the report's own GET
// filters preserved as-is (page reload, so this is a plain URL, not
// JS/AJAX). e.g. "expense-reports/category-summary" ->
// "expense-reports/export/pdf/category-summary" /
// "expense-reports/export/excel/category-summary"; the Register itself
// (bare "expense-reports") maps to "expense-reports/export/pdf" /
// "expense-reports/export/excel".
$expUri      = trim((string) uri_string(), '/');
$expBase     = 'expense-reports';
$expSuffix   = trim(substr($expUri, strlen($expBase)), '/');
$expPdfUrl   = base_url($expBase . '/export/pdf' . ($expSuffix !== '' ? '/' . $expSuffix : ''));
$expExcelUrl = base_url($expBase . '/export/excel' . ($expSuffix !== '' ? '/' . $expSuffix : ''));
$expQs       = $_SERVER['QUERY_STRING'] ?? '';
if ($expQs !== '') {
    $expPdfUrl   .= '?' . $expQs;
    $expExcelUrl .= '?' . $expQs;
}
?>
<div class="exp-toolbar exp-noprint">
    <div class="exp-toolbar-group">
        <?php if ($expShowFilterGroup): ?>
        <button type="submit" form="erFilterForm" class="btn-save"><i class="bi bi-funnel"></i> Filter</button>
        <a href="<?= base_url('expense-reports') ?>" class="btn-cancel"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
        <?php endif; ?>
    </div>
    <div class="exp-toolbar-group">
        <button type="button" class="btn-save" style="background:#6c757d;" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a href="<?= esc($expPdfUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= esc($expExcelUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
    </div>
</div>
<div id="expNoticeHost" class="exp-noprint"></div>

<div class="exp-print-only" style="margin-bottom:10px;">
    <div style="font-size:.75rem;color:#64748b;">A&amp;A Inventory &middot; Printed <?= esc(date('d-m-Y H:i')) ?></div>
    <div id="expPrintFilters" style="font-size:.75rem;margin-top:2px;"></div>
</div>

<script>
(function () {
	var host = document.getElementById('expNoticeHost');

	// Export PDF / Export Excel are placeholders.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-exp-soon]') : null;
		if (!btn || !host) return;
		host.innerHTML = '<div class="alert alert-info alert-dismissible fade show py-2" role="alert" style="font-size:.82rem;">'
			+ '<i class="bi bi-info-circle me-1"></i><span></span>'
			+ '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
		host.querySelector('span').textContent = btn.getAttribute('data-exp-soon') + ' is not available yet. It will come in a future release.';
	});

	// The filters as the server applied them (the form's default state), for the printout.
	function filterSummary() {
		var form = document.getElementById('erFilterForm'), parts = [];
		if (!form) return 'Filters: none (all records)';
		form.querySelectorAll('select, input').forEach(function (el) {
			var val = '';
			if (el.type === 'hidden' || el.type === 'submit') return;
			if (el.tagName === 'SELECT') {
				for (var i = 0; i < el.options.length; i++) {
					if (el.options[i].defaultSelected && el.options[i].value !== '') val = el.options[i].textContent.trim();
				}
			} else {
				val = (el.defaultValue || '').trim();
			}
			if (val === '') return;
			var box = el.closest('.form-section'), lab = box ? box.querySelector('label') : null;
			parts.push((lab ? lab.textContent.replace(/\s+/g, ' ').trim() : el.name) + ': ' + val);
		});
		return parts.length ? 'Filters: ' + parts.join(' · ') : 'Filters: none (all records)';
	}

	// Print every row of a paged DataTable, then put the paging back.
	var widened = [];
	window.addEventListener('beforeprint', function () {
		var out = document.getElementById('expPrintFilters');
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
