<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.7C: Print/Export Center.
 *
 * Every link/button on this page targets a PDF/Excel export route or an
 * on-screen report "View" page that already exists from 4.8.7A/4.8.7B — this
 * view runs no report queries and computes no totals. The six filter modals
 * (Expense Register, Service Register, Loan Register, EMI Due, Collections,
 * Outstanding) submit a plain GET to the same routes; the target controllers
 * already read those exact GET keys via their own filter helpers.
 *
 * "Quick Print" judgment call: the report pages already have their own
 * window.print()-wired Print button (report_toolbar.php, from 4.8.6E/4.8.7A).
 * Scripting another page's print from here would mean reaching into a
 * cross-origin/cross-page window, which is fragile and out of scope, so
 * "Quick Print" here simply opens the on-screen report page in a new tab;
 * a short note tells the user to use that page's own Print button.
 */
$ecUrl = static function (string $path, array $qs = []): string {
    $url = base_url($path);
    $qs  = array_filter($qs, static fn ($v) => $v !== null && $v !== '');
    return $qs ? $url . '?' . http_build_query($qs) : $url;
};
?>
<style>
.ecp-section { margin-bottom: 22px; }
.ecp-section h6 { font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; margin-bottom:10px; }
.ecp-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:9px 12px; border:1px solid #eef1f5; border-radius:8px; margin-bottom:8px; flex-wrap:wrap; }
.ecp-row-name { font-size:.85rem; font-weight:600; color:#1e293b; flex:1 1 180px; min-width:160px; }
.ecp-row-btns { display:flex; gap:6px; flex-wrap:wrap; }
.ecp-row-btns a, .ecp-row-btns button { padding:5px 10px; font-size:.75rem; }
.ecp-row select { font-size:.78rem; padding:5px 8px; min-width:180px; }
.ecp-note { font-size:.72rem; color:#94a3b8; margin-top:-2px; margin-bottom:12px; }
.ecp-row-btns a.disabled { pointer-events: none; opacity: .45; }
</style>

<nav aria-label="breadcrumb" class="exp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Print/Export Center</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-file-earmark-arrow-down me-2"></i>Print / Export Center</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('dashboard') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
</div>

<div class="row g-3">
<div class="col-12">
<div class="card-custom">
<div class="card-custom-body">

    <!-- QUICK PRINT -->
    <div class="ecp-section">
        <h6><i class="bi bi-printer me-1"></i>Quick Print</h6>
        <div class="ecp-note">Opens the report's on-screen page in a new tab — use that page's own Print button (top-right) for the browser print preview.</div>

        <div class="ecp-row">
            <div class="ecp-row-name">Expense Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('expense-reports') ?>" target="_blank" rel="noopener" class="btn-save"><i class="bi bi-printer"></i> Open to Print</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Service Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('service-reports/register') ?>" target="_blank" rel="noopener" class="btn-save"><i class="bi bi-printer"></i> Open to Print</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('loan-reports') ?>" target="_blank" rel="noopener" class="btn-save"><i class="bi bi-printer"></i> Open to Print</a></div>
        </div>

        <div class="ecp-row">
            <div class="ecp-row-name">Customer Ledger</div>
            <div class="ecp-row-btns">
                <select id="qpCustomerSel" onchange="ecpLedgerPick('qp','customer', this.value)">
                    <option value="">Select customer...</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qpCustomerLink" href="#" target="_blank" rel="noopener" class="btn-save disabled" aria-disabled="true"><i class="bi bi-printer"></i> Open to Print</a>
            </div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Supplier Ledger</div>
            <div class="ecp-row-btns">
                <select id="qpSupplierSel" onchange="ecpLedgerPick('qp','supplier', this.value)">
                    <option value="">Select supplier...</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= esc($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qpSupplierLink" href="#" target="_blank" rel="noopener" class="btn-save disabled" aria-disabled="true"><i class="bi bi-printer"></i> Open to Print</a>
            </div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan Ledger</div>
            <div class="ecp-row-btns">
                <select id="qpLoanSel" onchange="ecpLedgerPick('qp','loan', this.value)">
                    <option value="">Select loan...</option>
                    <?php foreach ($loans as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"><?= esc($l['loan_no']) ?> — <?= esc($l['lender_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qpLoanLink" href="#" target="_blank" rel="noopener" class="btn-save disabled" aria-disabled="true"><i class="bi bi-printer"></i> Open to Print</a>
            </div>
        </div>
    </div>

    <!-- QUICK PDF -->
    <div class="ecp-section">
        <h6><i class="bi bi-file-earmark-pdf me-1"></i>Quick PDF</h6>
        <div class="ecp-row">
            <div class="ecp-row-name">Expense Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('expense-reports/export/pdf') ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Service Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('service-reports/export/pdf') ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('loan-reports/export/pdf') ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Customer Ledger</div>
            <div class="ecp-row-btns">
                <select id="qpdfCustomerSel" onchange="ecpLedgerPick('qpdf','customer', this.value)">
                    <option value="">Select customer...</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qpdfCustomerLink" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
            </div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Supplier Ledger</div>
            <div class="ecp-row-btns">
                <select id="qpdfSupplierSel" onchange="ecpLedgerPick('qpdf','supplier', this.value)">
                    <option value="">Select supplier...</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= esc($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qpdfSupplierLink" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
            </div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan Ledger</div>
            <div class="ecp-row-btns">
                <select id="qpdfLoanSel" onchange="ecpLedgerPick('qpdf','loan', this.value)">
                    <option value="">Select loan...</option>
                    <?php foreach ($loans as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"><?= esc($l['loan_no']) ?> — <?= esc($l['lender_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qpdfLoanLink" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
            </div>
        </div>
    </div>

    <!-- QUICK EXCEL -->
    <div class="ecp-section">
        <h6><i class="bi bi-file-earmark-excel me-1"></i>Quick Excel</h6>
        <div class="ecp-row">
            <div class="ecp-row-name">Expense Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('expense-reports/export/excel') ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Download Excel</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Service Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('service-reports/export/excel') ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Download Excel</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan Register</div>
            <div class="ecp-row-btns"><a href="<?= base_url('loan-reports/export/excel') ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Download Excel</a></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Customer Ledger</div>
            <div class="ecp-row-btns">
                <select id="qxlCustomerSel" onchange="ecpLedgerPick('qxl','customer', this.value)">
                    <option value="">Select customer...</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qxlCustomerLink" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-excel"></i> Download Excel</a>
            </div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Supplier Ledger</div>
            <div class="ecp-row-btns">
                <select id="qxlSupplierSel" onchange="ecpLedgerPick('qxl','supplier', this.value)">
                    <option value="">Select supplier...</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= esc($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qxlSupplierLink" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-excel"></i> Download Excel</a>
            </div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan Ledger</div>
            <div class="ecp-row-btns">
                <select id="qxlLoanSel" onchange="ecpLedgerPick('qxl','loan', this.value)">
                    <option value="">Select loan...</option>
                    <?php foreach ($loans as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"><?= esc($l['loan_no']) ?> — <?= esc($l['lender_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="qxlLoanLink" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-excel"></i> Download Excel</a>
            </div>
        </div>
    </div>

    <!-- FILTERED EXPORTS (Phase C) -->
    <div class="ecp-section">
        <h6><i class="bi bi-funnel me-1"></i>Filtered Exports</h6>
        <div class="ecp-note">Pick filters, then export — the download carries the chosen filters in its own querystring, same as the live report pages.</div>

        <div class="ecp-row">
            <div class="ecp-row-name">Expense Register</div>
            <div class="ecp-row-btns"><button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#efExpenseRegister"><i class="bi bi-funnel"></i> Filter &amp; Export</button></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Service Register</div>
            <div class="ecp-row-btns"><button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#efServiceRegister"><i class="bi bi-funnel"></i> Filter &amp; Export</button></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan Register</div>
            <div class="ecp-row-btns"><button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#efLoanRegister"><i class="bi bi-funnel"></i> Filter &amp; Export</button></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Loan EMI Due</div>
            <div class="ecp-row-btns"><button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#efEmiDue"><i class="bi bi-funnel"></i> Filter &amp; Export</button></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Service Collections</div>
            <div class="ecp-row-btns"><button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#efCollections"><i class="bi bi-funnel"></i> Filter &amp; Export</button></div>
        </div>
        <div class="ecp-row">
            <div class="ecp-row-name">Service Outstanding</div>
            <div class="ecp-row-btns"><button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#efOutstanding"><i class="bi bi-funnel"></i> Filter &amp; Export</button></div>
        </div>
    </div>

</div>
</div>
</div>
</div>

<?php
// ---- Filter modal instantiations (Phase C) -----------------------------
$categoryOpts = array_map(static fn ($c) => ['value' => $c['id'], 'label' => $c['category_name']], $categories);
$projectOpts  = array_map(static fn ($p) => ['value' => $p['id'], 'label' => $p['name']], $projects);
$customerOpts = array_map(static fn ($c) => ['value' => $c['id'], 'label' => $c['name']], $customers);
$lenderOpts   = array_map(static fn ($l) => ['value' => $l, 'label' => $l], $lenders);
$bankOpts     = array_map(static fn ($b) => ['value' => $b['id'], 'label' => $b['bank_name'] . ' - ' . $b['account_name']], $banks);
$methodOpts   = array_map(static fn ($m) => ['value' => $m, 'label' => ucfirst(strtolower($m))], $expensePaymentMethods);
$statusOpts   = array_map(static fn ($s) => ['value' => $s, 'label' => ucfirst(strtolower($s))], $expenseStatuses);
$loanTypeOpts = array_map(static fn ($t) => ['value' => $t, 'label' => ucfirst(strtolower($t))], $loanTypes);
$loanStatusOpts = array_map(static fn ($s) => ['value' => $s, 'label' => ucfirst(strtolower($s))], $loanStatuses);
$receiptTypeOpts = array_map(static fn ($t) => ['value' => $t, 'label' => ucfirst(strtolower($t))], $serviceReceiptTypes);
$svcStatusOpts   = array_map(static fn ($s) => ['value' => $s, 'label' => ucfirst(strtolower($s))], $serviceStatuses);
$svcModeOpts     = array_map(static fn ($m) => ['value' => $m, 'label' => ucfirst(strtolower($m))], $servicePaymentModes);

// NOTE: $this->include($view, $options) treats its 2nd argument as RENDER
// OPTIONS (cache/debug), not view data — CodeIgniter4's View::render() never
// merges it into the template's variables. To hand each modal instance its
// own modalId/title/pdfUrl/excelUrl/fields, we use $this->setData() (which
// this codebase's shared View instance keeps across nested includes) right
// before each include, overwriting the previous modal's values every time.
$modals = [
    [
        'modalId' => 'efExpenseRegister',
        'title'   => 'Expense Register — Filter & Export',
        'pdfUrl'  => base_url('expense-reports/export/pdf'),
        'excelUrl'=> base_url('expense-reports/export/excel'),
        'fields'  => [
            ['type' => 'select', 'name' => 'category_id', 'label' => 'Category', 'options' => $categoryOpts],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'options' => $projectOpts],
            ['type' => 'select', 'name' => 'payment_method', 'label' => 'Payment Method', 'options' => $methodOpts],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => $statusOpts],
            ['type' => 'date', 'name' => 'date_from', 'label' => 'Date From'],
            ['type' => 'date', 'name' => 'date_to', 'label' => 'Date To'],
        ],
    ],
    [
        'modalId' => 'efServiceRegister',
        'title'   => 'Service Register — Filter & Export',
        'pdfUrl'  => base_url('service-reports/export/pdf'),
        'excelUrl'=> base_url('service-reports/export/excel'),
        'fields'  => [
            ['type' => 'select', 'name' => 'receipt_type', 'label' => 'Receipt Type', 'options' => $receiptTypeOpts],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => $svcStatusOpts],
            ['type' => 'select', 'name' => 'customer_id', 'label' => 'Customer', 'options' => $customerOpts],
            ['type' => 'select', 'name' => 'payment_mode', 'label' => 'Payment Mode', 'options' => $svcModeOpts],
            ['type' => 'date', 'name' => 'date_from', 'label' => 'Date From'],
            ['type' => 'date', 'name' => 'date_to', 'label' => 'Date To'],
        ],
    ],
    [
        'modalId' => 'efLoanRegister',
        'title'   => 'Loan Register — Filter & Export',
        'pdfUrl'  => base_url('loan-reports/export/pdf'),
        'excelUrl'=> base_url('loan-reports/export/excel'),
        'fields'  => [
            ['type' => 'select', 'name' => 'lender', 'label' => 'Lender', 'options' => $lenderOpts],
            ['type' => 'select', 'name' => 'loan_type', 'label' => 'Loan Type', 'options' => $loanTypeOpts],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => $loanStatusOpts],
            ['type' => 'date', 'name' => 'date_from', 'label' => 'Date From'],
            ['type' => 'date', 'name' => 'date_to', 'label' => 'Date To'],
        ],
    ],
    [
        'modalId' => 'efEmiDue',
        'title'   => 'Loan EMI Due — Filter & Export',
        'pdfUrl'  => base_url('loan-reports/export/pdf/emi-due'),
        'excelUrl'=> base_url('loan-reports/export/excel/emi-due'),
        'fields'  => [
            ['type' => 'select', 'name' => 'lender', 'label' => 'Lender', 'options' => $lenderOpts],
            ['type' => 'select', 'name' => 'loan_type', 'label' => 'Loan Type', 'options' => $loanTypeOpts],
            ['type' => 'date', 'name' => 'date_from', 'label' => 'Date From'],
            ['type' => 'date', 'name' => 'date_to', 'label' => 'Date To'],
        ],
    ],
    [
        'modalId' => 'efCollections',
        'title'   => 'Service Collections — Filter & Export',
        'pdfUrl'  => base_url('service-reports/export/pdf/collections'),
        'excelUrl'=> base_url('service-reports/export/excel/collections'),
        'fields'  => [
            ['type' => 'select', 'name' => 'customer_id', 'label' => 'Customer', 'options' => $customerOpts],
            ['type' => 'select', 'name' => 'payment_mode', 'label' => 'Payment Mode', 'options' => $svcModeOpts],
            ['type' => 'select', 'name' => 'bank_account_id', 'label' => 'Bank Account', 'options' => $bankOpts],
            ['type' => 'date', 'name' => 'date_from', 'label' => 'Date From'],
            ['type' => 'date', 'name' => 'date_to', 'label' => 'Date To'],
        ],
    ],
    [
        'modalId' => 'efOutstanding',
        'title'   => 'Service Outstanding — Filter & Export',
        'pdfUrl'  => base_url('service-reports/export/pdf/outstanding'),
        'excelUrl'=> base_url('service-reports/export/excel/outstanding'),
        'fields'  => [
            ['type' => 'select', 'name' => 'customer_id', 'label' => 'Customer', 'options' => $customerOpts],
            ['type' => 'number', 'name' => 'min_amount', 'label' => 'Min Amount'],
            ['type' => 'number', 'name' => 'max_amount', 'label' => 'Max Amount'],
            ['type' => 'date', 'name' => 'date_from', 'label' => 'Date From'],
            ['type' => 'date', 'name' => 'date_to', 'label' => 'Date To'],
        ],
    ],
];

foreach ($modals as $m) {
    $this->setData($m, 'raw');
    echo $this->include('export_center/partials/filter_modal');
}
?>

<script>
// Ledger dropdown -> View/Print/PDF/Excel links (Release 4.8.7C). Disabled
// until a valid option from the dropdown's own list is chosen; the export
// route's own 404 guard (already live from 4.8.7A/4.8.7B) is the real
// protection against a crafted id.
var ecpBase = <?= json_encode(rtrim(base_url(), '/')) ?>;
var ecpRoutes = {
    customer: { qp: '/customer-ledger/view/', qpdf: '/customer-ledger/export/pdf/', qxl: '/customer-ledger/export/excel/' },
    supplier: { qp: '/supplier-ledger/view/', qpdf: '/supplier-ledger/export/pdf/', qxl: '/supplier-ledger/export/excel/' },
    loan:     { qp: '/loans/ledger/', qpdf: '/loans/export/pdf/', qxl: '/loans/ledger/export/excel/' }
};
function ecpSetLink(id, href, enabled) {
    var el = document.getElementById(id);
    if (!el) return;
    if (enabled) {
        el.href = href;
        el.classList.remove('disabled');
        el.removeAttribute('aria-disabled');
    } else {
        el.href = '#';
        el.classList.add('disabled');
        el.setAttribute('aria-disabled', 'true');
    }
}
function ecpLedgerPick(group, kind, id) {
    var route = ecpRoutes[kind][group];
    var cap = kind.charAt(0).toUpperCase() + kind.slice(1);
    ecpSetLink(group + cap + 'Link', ecpBase + route + id, !!id);
}
</script>

<?= $this->endSection() ?>
