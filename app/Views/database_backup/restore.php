<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
.db-warning-banner { border-left: 4px solid #dc2626; background: #fef2f2; color: #7f1d1d; padding: 12px 16px; border-radius: 8px; font-size: .82rem; margin-bottom: 14px; }
.db-warning-banner i { color: #dc2626; }
.db-ini-note { font-size: .72rem; color: #94a3b8; margin-top: 4px; }
</style>

<nav aria-label="breadcrumb" style="margin-bottom:8px;">
    <ol class="breadcrumb" style="font-size:.78rem;background:transparent;padding:0;margin-bottom:0;">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('database-backup') ?>">Backup &amp; Restore Center</a></li>
        <li class="breadcrumb-item active" aria-current="page">Restore Database</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-cloud-arrow-down me-2"></i>Restore Database</span>
    <a href="<?= base_url('database-backup') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back to Backup Center</a>
</div>

<div class="db-warning-banner">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <strong>This will overwrite the current database.</strong> Restoring replaces existing data with the contents of the uploaded .sql file.
    An automatic safety backup of the current database is always taken immediately before the restore runs, so it can be manually re-imported if
    something goes wrong — but MySQL cannot automatically roll back a multi-statement SQL import once it has started (table-structure statements
    commit immediately, independent of any transaction). Any logged-in user on this system can perform a restore (this app has no admin/role
    restriction).
</div>

<div class="card-custom">
    <div class="card-custom-header">Upload a Backup File</div>
    <div class="card-custom-body">
        <form id="restoreForm" method="post" action="<?= base_url('database-backup/restore') ?>" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <label class="form-label">Backup .sql File</label>
                    <input type="file" name="sql_file" id="sqlFileInput" class="form-control" accept=".sql" required>
                    <div class="db-ini-note">
                        Maximum accepted size (application setting): <?= esc($maxUploadSizeText) ?>.
                        <?php if (isset($iniUploadMaxFilesize, $iniPostMaxSize)): ?>
                            <br>Note: this server's current PHP configuration caps a single upload at
                            <strong><?= esc($iniUploadMaxFilesize) ?></strong> (upload_max_filesize) / <strong><?= esc($iniPostMaxSize) ?></strong> (post_max_size).
                            A file larger than that will fail to upload even though the application allows up to <?= esc($maxUploadSizeText) ?> —
                            a server administrator must raise those php.ini values first.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="confirmOverwrite" name="confirm_overwrite" value="1" required>
                <label class="form-check-label" for="confirmOverwrite" style="font-size:.85rem;">
                    I understand this will overwrite existing data in the database and cannot be undone by this application.
                </label>
            </div>

            <div id="restoreFormError" class="alert alert-danger d-none mt-3" role="alert"></div>

            <div class="mt-3 d-flex gap-2">
                <button type="button" id="restoreSubmitBtn" class="btn-save"><i class="bi bi-cloud-arrow-down"></i> Restore Database</button>
                <a href="<?= base_url('database-backup') ?>" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- SECOND CONFIRMATION MODAL (JS gate, in addition to the checkbox) -->
<div class="modal fade" id="restoreConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;border-radius:50%;background:#fee2e2;color:#dc2626;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </span>
                    Confirm Database Restore
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This is your last chance to cancel. Restoring will <strong>overwrite the current database</strong> with the uploaded file's contents.
                A safety backup will be taken automatically first, but the restore itself cannot be undone automatically.</p>
                <p>The restore can take a minute or more on large databases. Please keep this page open until it finishes.</p>
                <p class="mb-0">Are you absolutely sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-delete" id="confirmRestoreBtn">
                    <span id="restoreSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-cloud-arrow-down"></i> Yes, Restore Now
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    $('#restoreSubmitBtn').on('click', function () {
        var errorBox = $('#restoreFormError');
        errorBox.addClass('d-none').text('');

        var fileInput = document.getElementById('sqlFileInput');
        if (!fileInput.files || !fileInput.files.length) {
            errorBox.text('Please choose a .sql file to upload.').removeClass('d-none');
            return;
        }

        var fileName = fileInput.files[0].name || '';
        if (!/\.sql$/i.test(fileName)) {
            errorBox.text('Only .sql files are accepted.').removeClass('d-none');
            return;
        }

        if (!$('#confirmOverwrite').is(':checked')) {
            errorBox.text('You must tick "I understand this will overwrite existing data" before restoring.').removeClass('d-none');
            return;
        }

        var modal = new bootstrap.Modal(document.getElementById('restoreConfirmModal'));
        modal.show();
    });

    $('#confirmRestoreBtn').on('click', function () {
        $(this).prop('disabled', true);
        $('#restoreSpinner').removeClass('d-none');
        document.getElementById('restoreForm').submit();
    });
});
</script>
<?= $this->endSection() ?>
