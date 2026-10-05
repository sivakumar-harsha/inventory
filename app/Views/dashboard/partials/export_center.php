<?php
/**
 * Release 4.8.7C: Dashboard "Export Center" section (Phase A) + the
 * "Today's/This Month/Master" bulk export cards (Phase D).
 *
 * Pure UI: every button below is a plain link to a PDF/Excel export route
 * (or an on-screen "View" page) that already exists from 4.8.7A/4.8.7B —
 * this partial computes no totals and runs no report queries. The only
 * database reads here are cheap id/name lookups for the three Ledger
 * dropdowns (customers, suppliers, loans), the same trivial pattern already
 * used by LoanReports::_lenders()/ServiceReports::_customers(). Dashboard.php
 * (the controller) is untouched by this release, so this partial fetches its
 * own tiny dropdown data directly, the same way a view-layer lookup is done
 * nowhere else on this page but is explicitly allowed by the release brief
 * for populating <select> options.
 *
 * "Last exported" is a static placeholder — no export-history table exists,
 * and building one is out of scope for this release.
 */
$ecCustomers = (new \App\Models\CustomerModel())->orderBy('name', 'ASC')->findAll();
$ecSuppliers = (new \App\Models\SupplierModel())->orderBy('name', 'ASC')->findAll();
$ecLoans     = \Config\Database::connect()->table('loans')->select('id, loan_no, lender_name')->orderBy('loan_no', 'ASC')->get()->getResultArray();

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

/** Builds a base_url() export link, optionally with a querystring. */
$ecUrl = static function (string $path, array $qs = []): string {
    $url = base_url($path);
    $qs  = array_filter($qs, static fn ($v) => $v !== null && $v !== '');
    return $qs ? $url . '?' . http_build_query($qs) : $url;
};
?>
<style>
.ec-group-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; margin:14px 0 8px; }
.ec-group-title:first-child { margin-top:0; }
.ec-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border:1px solid #eef1f5; border-radius:8px; margin-bottom:6px; flex-wrap:wrap; }
.ec-row-name { font-size:.82rem; font-weight:600; color:#1e293b; flex:1 1 160px; min-width:140px; }
.ec-row-btns { display:flex; gap:6px; flex-wrap:wrap; }
.ec-row-btns a, .ec-row-btns button { padding:4px 9px; font-size:.72rem; }
.ec-row select { font-size:.75rem; padding:4px 6px; min-width:160px; }
.ec-bulk-card { border:1px solid #eef1f5; border-radius:8px; padding:10px; height:100%; }
.ec-bulk-card h6 { font-size:.76rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.03em; margin-bottom:8px; }
.ec-bulk-link { display:block; font-size:.8rem; padding:5px 0; text-decoration:none; color:#1e293b; border-bottom:1px dashed #eef1f5; }
.ec-bulk-link:last-child { border-bottom:none; }
.ec-bulk-link i { margin-right:6px; color:#94a3b8; }
.ec-placeholder { font-size:.68rem; color:#94a3b8; }
</style>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-arrow-down me-2"></i>Export Center</span>
            </div>
            <div class="card-custom-body">

                <!-- EXPENSES -->
                <div class="ec-group-title">Expenses</div>
                <?php
                $expenseRows = [
                    ['Expense Register', 'expense-reports', ''],
                    ['Category Summary', 'expense-reports/category-summary', 'category-summary'],
                    ['Project Summary', 'expense-reports/project-summary', 'project-summary'],
                    ['Payment Method Summary', 'expense-reports/payment-summary', 'payment-summary'],
                    ['Monthly Summary', 'expense-reports/monthly-summary', 'monthly-summary'],
                ];
                foreach ($expenseRows as [$label, $viewPath, $suffix]):
                    $suf = $suffix !== '' ? '/' . $suffix : '';
                ?>
                <div class="ec-row">
                    <div class="ec-row-name"><?= esc($label) ?></div>
                    <div class="ec-row-btns">
                        <a href="<?= base_url($viewPath) ?>" class="btn-save"><i class="bi bi-eye"></i> View</a>
                        <a href="<?= base_url('expense-reports/export/pdf' . $suf) ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a href="<?= base_url('expense-reports/export/excel' . $suf) ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- SERVICES -->
                <div class="ec-group-title">Services</div>
                <?php
                $serviceRows = [
                    ['Service Register', 'service-reports/register', ''],
                    ['Outstanding', 'service-reports/outstanding', 'outstanding'],
                    ['Collections', 'service-reports/collections', 'collections'],
                    ['Customer Summary', 'service-reports/customer-summary', 'customer-summary'],
                ];
                foreach ($serviceRows as [$label, $viewPath, $suffix]):
                    $suf = $suffix !== '' ? '/' . $suffix : '';
                ?>
                <div class="ec-row">
                    <div class="ec-row-name"><?= esc($label) ?></div>
                    <div class="ec-row-btns">
                        <a href="<?= base_url($viewPath) ?>" class="btn-save"><i class="bi bi-eye"></i> View</a>
                        <a href="<?= base_url('service-reports/export/pdf' . $suf) ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a href="<?= base_url('service-reports/export/excel' . $suf) ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- LOANS -->
                <div class="ec-group-title">Loans</div>
                <?php
                $loanRows = [
                    ['Loan Register', 'loan-reports', ''],
                    ['EMI Due', 'loan-reports/emi-due', 'emi-due'],
                    ['Payment Register', 'loan-reports/payments', 'payments'],
                    ['Outstanding', 'loan-reports/outstanding', 'outstanding'],
                    ['Lender Summary', 'loan-reports/lender-summary', 'lender-summary'],
                ];
                foreach ($loanRows as [$label, $viewPath, $suffix]):
                    $suf = $suffix !== '' ? '/' . $suffix : '';
                ?>
                <div class="ec-row">
                    <div class="ec-row-name"><?= esc($label) ?></div>
                    <div class="ec-row-btns">
                        <a href="<?= base_url($viewPath) ?>" class="btn-save"><i class="bi bi-eye"></i> View</a>
                        <a href="<?= base_url('loan-reports/export/pdf' . $suf) ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a href="<?= base_url('loan-reports/export/excel' . $suf) ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- LEDGERS (need an id first) -->
                <div class="ec-group-title">Ledgers</div>

                <div class="ec-row">
                    <div class="ec-row-name"><i class="bi bi-person-lines-fill me-1"></i>Customer Ledger</div>
                    <div class="ec-row-btns">
                        <select id="ecCustomerLedgerSel" onchange="ecLedgerPick('customer', this.value)">
                            <option value="">Select customer...</option>
                            <?php foreach ($ecCustomers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a id="ecCustomerLedgerView" href="#" class="btn-save disabled" aria-disabled="true"><i class="bi bi-eye"></i> View</a>
                        <a id="ecCustomerLedgerPdf" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a id="ecCustomerLedgerExcel" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </div>
                </div>

                <div class="ec-row">
                    <div class="ec-row-name"><i class="bi bi-truck me-1"></i>Supplier Ledger</div>
                    <div class="ec-row-btns">
                        <select id="ecSupplierLedgerSel" onchange="ecLedgerPick('supplier', this.value)">
                            <option value="">Select supplier...</option>
                            <?php foreach ($ecSuppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= esc($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a id="ecSupplierLedgerView" href="#" class="btn-save disabled" aria-disabled="true"><i class="bi bi-eye"></i> View</a>
                        <a id="ecSupplierLedgerPdf" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a id="ecSupplierLedgerExcel" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </div>
                </div>

                <div class="ec-row">
                    <div class="ec-row-name"><i class="bi bi-bank me-1"></i>Loan Ledger</div>
                    <div class="ec-row-btns">
                        <select id="ecLoanLedgerSel" onchange="ecLedgerPick('loan', this.value)">
                            <option value="">Select loan...</option>
                            <?php foreach ($ecLoans as $l): ?>
                            <option value="<?= (int) $l['id'] ?>"><?= esc($l['loan_no']) ?> — <?= esc($l['lender_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a id="ecLoanLedgerView" href="#" class="btn-save disabled" aria-disabled="true"><i class="bi bi-eye"></i> View</a>
                        <a id="ecLoanLedgerPdf" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a id="ecLoanLedgerExcel" href="#" class="btn-cancel disabled" aria-disabled="true"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </div>
                </div>
                <div class="ec-placeholder mb-1">Last exported: Not tracked yet</div>

                <!-- BULK EXPORT CARDS (Phase D) -->
                <div class="ec-group-title">Bulk Exports</div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="ec-bulk-card">
                            <h6>Today's Business Reports</h6>
                            <a class="ec-bulk-link" href="<?= esc($ecUrl('expense-reports/export/pdf', ['date_from' => $today, 'date_to' => $today]), 'attr') ?>"><i class="bi bi-file-earmark-pdf"></i>Today's Expenses (PDF)</a>
                            <a class="ec-bulk-link" href="<?= esc($ecUrl('service-reports/export/pdf/collections', ['date_from' => $today, 'date_to' => $today]), 'attr') ?>"><i class="bi bi-file-earmark-pdf"></i>Today's Collections (PDF)</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ec-bulk-card">
                            <h6>This Month Reports</h6>
                            <a class="ec-bulk-link" href="<?= esc($ecUrl('expense-reports/export/pdf', ['date_from' => $monthStart, 'date_to' => $monthEnd]), 'attr') ?>"><i class="bi bi-file-earmark-pdf"></i>This Month Expenses (PDF)</a>
                            <a class="ec-bulk-link" href="<?= esc($ecUrl('service-reports/export/pdf/collections', ['date_from' => $monthStart, 'date_to' => $monthEnd]), 'attr') ?>"><i class="bi bi-file-earmark-pdf"></i>This Month Collections (PDF)</a>
                            <a class="ec-bulk-link" href="<?= esc($ecUrl('loan-reports/export/pdf/emi-due', ['date_from' => $monthStart, 'date_to' => $monthEnd]), 'attr') ?>"><i class="bi bi-file-earmark-pdf"></i>This Month EMI Due (PDF)</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ec-bulk-card">
                            <h6>Master Reports (unfiltered)</h6>
                            <a class="ec-bulk-link" href="<?= base_url('expense-reports/export/pdf') ?>"><i class="bi bi-file-earmark-pdf"></i>Expense Register (PDF)</a>
                            <a class="ec-bulk-link" href="<?= base_url('loan-reports/export/pdf') ?>"><i class="bi bi-file-earmark-pdf"></i>Loan Register (PDF)</a>
                            <a class="ec-bulk-link" href="<?= base_url('service-reports/export/pdf') ?>"><i class="bi bi-file-earmark-pdf"></i>Service Register (PDF)</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Ledger dropdown -> View/PDF/Excel links (Release 4.8.7C). The buttons stay
// disabled (real <a> tags with href="#" and the .disabled class + pointer-events
// blocked) until a valid option from the dropdown's own list is chosen, so a
// crafted id can't be submitted from this UI (the export route's own 404 guard,
// already live from 4.8.7A/4.8.7B, is still the real protection).
var ecBase = <?= json_encode(rtrim(base_url(), '/')) ?>;
var ecRoutes = {
    customer: { view: '/customer-ledger/view/', pdf: '/customer-ledger/export/pdf/', excel: '/customer-ledger/export/excel/' },
    supplier: { view: '/supplier-ledger/view/', pdf: '/supplier-ledger/export/pdf/', excel: '/supplier-ledger/export/excel/' },
    loan:     { view: '/loans/ledger/', pdf: '/loans/export/pdf/', excel: '/loans/ledger/export/excel/' }
};
function ecSetLink(id, href, enabled) {
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
function ecLedgerPick(kind, id) {
    var r = ecRoutes[kind];
    var cap = kind.charAt(0).toUpperCase() + kind.slice(1);
    var enabled = !!id;
    ecSetLink('ec' + cap + 'LedgerView', ecBase + r.view + id, enabled);
    ecSetLink('ec' + cap + 'LedgerPdf', ecBase + r.pdf + id, enabled);
    ecSetLink('ec' + cap + 'LedgerExcel', ecBase + r.excel + id, enabled);
}
</script>
<style>
.ec-row-btns a.disabled { pointer-events: none; opacity: .45; }
</style>
