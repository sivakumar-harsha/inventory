<?php
/**
 * Release 4.8.7C: one reusable Bootstrap modal for the six Export Center
 * reports that already have real GET filter forms on their own pages
 * (Expense Register, Loan Register, Loan EMI Due, Service Register, Service
 * Collections, Service Outstanding).
 *
 * This is view-layer only: the form is method="get" and the two submit
 * buttons use HTML formaction to send the exact same GET keys the target
 * report controller already reads via its own _registerFilters()/
 * _emiDueFilters()/_getRegisterFilters()/_getCollectionFilters()/
 * _getOutstandingFilters() helpers — no new filter-parsing logic anywhere.
 * A plain page reload (GET navigation) downloads the PDF/Excel with the
 * chosen filters already in the querystring, exactly like report_toolbar.php
 * already does for the single-page reports.
 *
 * Expected variables (passed explicitly via $this->include(..., [...])):
 *   string $modalId   unique DOM id, e.g. "efExpenseRegister"
 *   string $title     modal heading
 *   string $pdfUrl    export/pdf route (no querystring)
 *   string $excelUrl  export/excel route (no querystring)
 *   array  $fields    each: ['type'=>'select'|'date'|'number', 'name'=>..., 'label'=>...,
 *                             'options'=>[['value'=>..,'label'=>..], ...]] (select only)
 */
?>
<div class="modal fade" id="<?= esc($modalId, 'attr') ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="get" class="ec-filter-form">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title"><i class="bi bi-funnel me-2"></i><?= esc($title) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <?php foreach ($fields as $field): ?>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:.78rem;"><?= esc($field['label']) ?></label>
                            <?php if ($field['type'] === 'select'): ?>
                            <select name="<?= esc($field['name'], 'attr') ?>" class="form-control">
                                <option value="">All</option>
                                <?php foreach ($field['options'] as $opt): ?>
                                <option value="<?= esc((string) $opt['value'], 'attr') ?>"><?= esc($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php elseif ($field['type'] === 'date'): ?>
                            <input type="date" name="<?= esc($field['name'], 'attr') ?>" class="form-control">
                            <?php elseif ($field['type'] === 'number'): ?>
                            <input type="number" step="0.01" name="<?= esc($field['name'], 'attr') ?>" class="form-control">
                            <?php else: ?>
                            <input type="text" name="<?= esc($field['name'], 'attr') ?>" class="form-control">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" formaction="<?= esc($pdfUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
                    <button type="submit" formaction="<?= esc($excelUrl, 'attr') ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>
