<?php
/**
 * Release 4.9.0F: filter + export row shared by the four Expense summary
 * reports (included from expense_reports/partials/summary_head):
 *
 *   [ From ] [ To ] [ Apply ] [ Reset ]          [ Print ] [ Export PDF ] [ Export Excel ]
 *
 * Plain GET form (page reload). Print is the browser's print (every row, not
 * just the DataTables page, with the applied range spelled out). Export PDF /
 * Excel point at the matching expense-reports/export/pdf|excel/<summary>
 * route with the current range preserved.
 */
$expUri      = trim((string) uri_string(), '/');
$expBase     = 'expense-reports';
$expSuffix   = trim(substr($expUri, strlen($expBase)), '/');
$expPdfUrl   = base_url($expBase . '/export/pdf' . ($expSuffix !== '' ? '/' . $expSuffix : ''));
$expExcelUrl = base_url($expBase . '/export/excel' . ($expSuffix !== '' ? '/' . $expSuffix : ''));
$expQs       = http_build_query(array_filter(['date_from' => $f['date_from'] ?? '', 'date_to' => $f['date_to'] ?? ''], 'strlen'));
if ($expQs !== '') {
    $expPdfUrl   .= '?' . $expQs;
    $expExcelUrl .= '?' . $expQs;
}
?>
<form method="get" data-auto-filter action="<?= base_url($expUri) ?>" id="erFilterForm" class="exp-toolbar rpt-filter exp-noprint">
    <div class="exp-toolbar-group align-items-end">
        <div class="form-section rpt-field">
            <label for="erFrom">From</label>
            <input type="date" name="date_from" id="erFrom" class="form-control form-control-sm" value="<?= esc($f['date_from'] ?? '', 'attr') ?>">
        </div>
        <div class="form-section rpt-field">
            <label for="erTo">To</label>
            <input type="date" name="date_to" id="erTo" class="form-control form-control-sm" value="<?= esc($f['date_to'] ?? '', 'attr') ?>">
        </div>
        <a href="<?= base_url($expUri) ?>" class="btn-cancel"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
    </div>
    <div class="exp-toolbar-group">
        <button type="button" class="btn-cancel" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a href="<?= esc($expPdfUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= esc($expExcelUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
    </div>
</form>

<div class="exp-print-only" style="margin-bottom:10px;">
    <div style="font-size:.75rem;color:#64748b;">A&amp;A Inventory &middot; Printed <?= esc(date('d-m-Y H:i')) ?></div>
    <div id="expPrintFilters" style="font-size:.75rem;margin-top:2px;"></div>
</div>

<script>
(function () {
	// The range as the server applied it (the form's default state), for the printout.
	function filterSummary() {
		var form = document.getElementById('erFilterForm'), parts = [];
		if (!form) return 'Filters: none (all records)';
		form.querySelectorAll('input').forEach(function (el) {
			var val = (el.defaultValue || '').trim();
			if (el.type === 'hidden' || val === '') return;
			var box = el.closest('.form-section'), lab = box ? box.querySelector('label') : null;
			parts.push((lab ? lab.textContent.replace(/\s+/g, ' ').trim() : el.name) + ': ' + val);
		});
		return parts.length ? 'Filters: ' + parts.join(' · ') : 'Filters: none (all dates)';
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
