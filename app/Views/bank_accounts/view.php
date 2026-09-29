<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.ba-breadcrumb { margin-bottom: 8px; }
	.ba-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ba-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ba-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ba-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.badge-status {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 6px;
		font-size: .7rem;
		font-weight: 700;
	}
	.badge-status.status-active { background:#dcfce7; color:#166534; }
	.badge-status.status-inactive { background:#f1f5f9; color:#64748b; }
</style>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($account['bank_name']) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi bi-bank me-2"></i><?= esc($account['bank_name']) ?></span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('bank-accounts/transactions/' . $account['id']) ?>" class="btn-save"><i class="bi bi-journal-text"></i> Transactions</a>
        <a href="<?= base_url('bank-accounts/edit/' . $account['id']) ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-pencil"></i> Edit</a>
        <a href="<?= base_url('bank-accounts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header">Account Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Bank Name</strong></td><td><?= esc($account['bank_name']) ?></td></tr>
                    <tr><td><strong>Account Holder Name</strong></td><td><?= esc($account['account_name']) ?></td></tr>
                    <tr><td><strong>Account Number</strong></td><td><?= esc($account['account_number']) ?></td></tr>
                    <tr><td><strong>IFSC Code</strong></td><td><?= esc($account['ifsc_code'] ?: '-') ?></td></tr>
                    <tr><td><strong>Branch</strong></td><td><?= esc($account['branch_name'] ?: '-') ?></td></tr>
                    <tr><td><strong>Status</strong></td><td><span class="badge-status <?= $account['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $account['is_active'] ? 'Active' : 'Inactive' ?></span></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header">Balance</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td>Opening Balance</td><td style="text-align:right"><?= number_format((float) $account['opening_balance'], 2) ?></td></tr>
                    <tr><td><strong>Current Balance</strong></td><td style="text-align:right"><strong><?= number_format((float) $account['current_balance'], 2) ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
