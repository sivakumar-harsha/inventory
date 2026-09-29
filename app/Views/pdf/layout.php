<?php
/**
 * Release 4.8.7A: shared PDF chrome (logo, company name, report title,
 * generated timestamp, applied filters, footer page number) wrapped around a
 * per-report body. Rendered by App\Libraries\PdfReport::render(); $content is
 * the already-rendered HTML of the specific report/ledger template.
 *
 * @var string $title
 * @var array  $filters
 * @var string $orientation
 * @var string $content
 * @var string $generatedAt
 */
$logoUrl = base_url('assets/images/aainv_logo.png');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= esc($title) ?></title>
<style>
    @page {
        size: A4 <?= $orientation === 'landscape' ? 'landscape' : 'portrait' ?>;
        margin: 90px 24px 50px 24px;

        footer: page-footer;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10.5px;
        color: #1e293b;
        margin: 0;
    }

    .pdf-header {
        position: fixed;
        top: -75px;
        left: 0;
        right: 0;
        height: 65px;
        border-bottom: 2px solid #2F7E8A;
        padding-bottom: 6px;
    }

    .pdf-header table { width: 100%; border-collapse: collapse; }
    .pdf-header td { border: none; padding: 0; vertical-align: middle; }

    .pdf-logo img { height: 42px; }

    .pdf-company { font-size: 15px; font-weight: 700; color: #2F7E8A; }
    .pdf-company-sub { font-size: 9px; color: #64748b; }

    .pdf-report-title { font-size: 13px; font-weight: 700; color: #1e293b; text-align: right; }
    .pdf-generated { font-size: 8.5px; color: #64748b; text-align: right; }

    .pdf-filters {
        margin-top: 6px;
        font-size: 8.5px;
        color: #475569;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .pdf-footer {
        position: fixed;
        bottom: -40px;
        left: 0;
        right: 0;
        height: 30px;
        border-top: 1px solid #cbd5e1;
        padding-top: 4px;
        font-size: 8.5px;
        color: #64748b;
        text-align: center;
    }

    table.pdf-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.pdf-table th {
        background: #2F7E8A;
        color: #fff;
        padding: 5px 6px;
        font-size: 9.5px;
        text-align: left;
        white-space: nowrap;
    }
    table.pdf-table td {
        padding: 4px 6px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 9.5px;
        word-wrap: break-word;
        word-break: break-word;
    }
    table.pdf-table tr:nth-child(even) td { background: #f8fafc; }
    table.pdf-table tfoot td { font-weight: 700; background: #eef2f7; border-top: 2px solid #cbd5e1; }
    .pdf-right { text-align: right; }
    .pdf-center { text-align: center; }

    .pdf-kpi-row { width: 100%; margin-bottom: 8px; }
    .pdf-kpi { display: inline-block; width: 23%; margin-right: 1.5%; padding: 6px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; vertical-align: top; }
    .pdf-kpi .l { font-size: 8px; color: #64748b; text-transform: uppercase; }
    .pdf-kpi .v { font-size: 12px; font-weight: 700; color: #1e293b; }

    .pdf-section-title { font-size: 11px; font-weight: 700; color: #2F7E8A; margin: 8px 0 4px; }
</style>
</head>
<body>

<!--
    dompdf repeats any position:fixed element on every page (it has no
    real CSS3 paged-media margin-box support), so the header/footer below
    are plain fixed-position blocks rather than special tags.
-->
<div class="pdf-header">
    <table>
        <tr>
            <td style="width:60px;" class="pdf-logo"><img src="<?= $logoUrl ?>"></td>
            <td>
                <div class="pdf-company">A&amp;A Inventory ERP</div>
                <div class="pdf-company-sub">Read-only export</div>
            </td>
            <td style="width:40%;">
                <div class="pdf-report-title"><?= esc($title) ?></div>
                <div class="pdf-generated">Generated: <?= esc($generatedAt) ?></div>
            </td>
        </tr>
    </table>
    <?php if (! empty($filters)): ?>
    <div class="pdf-filters">Applied filters: <?= esc(pdf_filter_line($filters)) ?></div>
    <?php endif; ?>
</div>

<div class="pdf-footer"></div>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont("DejaVu Sans", "normal");
    $size = 8.5;
    $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
    $width = $fontMetrics->getTextWidth($text, $font, $size);
    $x = ($pdf->get_width() - $width) / 2;
    $y = $pdf->get_height() - 26;
    $pdf->page_text($x, $y, $text, $font, $size, array(0.4, 0.46, 0.55));
}
</script>

<?= $content ?>

</body>
</html>
