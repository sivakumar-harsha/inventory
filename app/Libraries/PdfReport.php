<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Release 4.8.7A: shared PDF export engine for the read-only report/ledger
 * PDFs. Thin wrapper around the Composer-installed dompdf/dompdf (the same
 * package already used by Reports.php / Projects.php's ad-hoc PDF methods —
 * this class just gives every new PDF endpoint one place to build the HTML
 * (shared chrome + a per-report body view) instead of repeating it.
 *
 * Nothing here writes to the database; it only renders views to HTML and
 * streams a PDF response.
 */
class PdfReport
{
    private Dompdf $dompdf;

    public function __construct()
    {
        // Loaded here (not via Config\Autoload, which is out of scope for this
        // release) so every pdf_* formatting helper is available both to the
        // pdf/*.php templates this class renders and to any controller that
        // builds a `filters` array (e.g. with pdf_date()) before calling
        // render() — PHP evaluates `new PdfReport()` before render()'s own
        // argument list, so the helper is guaranteed loaded by then.
        helper('pdf');

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        // Needed for the <script type="text/php"> page-number footer in
        // app/Views/pdf/layout.php — dompdf's documented way to print
        // "Page X of Y" (there is no CSS3 paged-media support for it).
        // The script is static and ships with this codebase; nothing here
        // ever embeds request/user input into it.
        $options->set('isPhpEnabled', true);

        $this->dompdf = new Dompdf($options);
    }

    /**
     * Renders $bodyView (a app/Views/pdf/*.php template) inside the shared
     * app/Views/pdf/layout.php chrome, feeds the combined HTML to dompdf, and
     * streams the finished PDF as a download.
     *
     * $data is passed to the body view. Recognised $opts keys:
     *   - orientation: 'portrait' (default) | 'landscape'
     *   - title:       report title shown in the header (default 'Report')
     *   - filters:     label => value pairs printed under the title
     *   - filename:    download filename (default derived from title)
     *   - paper:       paper size, default 'A4'
     */
    public function render(string $bodyView, array $data, array $opts = []): void
    {
        $orientation = $opts['orientation'] ?? 'portrait';
        $paper       = $opts['paper'] ?? 'A4';
        $title       = $opts['title'] ?? 'Report';
        $filters     = $opts['filters'] ?? [];

        $content = view($bodyView, $data);

        $html = view('pdf/layout', [
            'title'       => $title,
            'filters'     => $filters,
            'orientation' => $orientation,
            'content'     => $content,
            'generatedAt' => date('d-m-Y H:i A'),
        ]);

        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper($paper, $orientation);
        $this->dompdf->render();

        $filename = $opts['filename'] ?? (preg_replace('/[^a-z0-9]+/i', '_', strtolower($title)) . '_' . date('Ymd_His') . '.pdf');

        $this->dompdf->stream($filename, ['Attachment' => $opts['attachment'] ?? true]);
    }
}
