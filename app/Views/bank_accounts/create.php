<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.ba-breadcrumb { margin-bottom: 8px; }
	.ba-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ba-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ba-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ba-breadcrumb .breadcrumb-item.active { color: #64748b; }
</style>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item active" aria-current="page">New Bank Account</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-bank me-2"></i>New Bank Account</span>
</div>

<div class="card-custom">
    <div class="card-custom-body">
        <form id="bankAccountForm">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Bank Name *</label>
                    <input type="text" name="bank_name" id="bankName" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account Holder Name *</label>
                    <input type="text" name="account_name" id="accountName" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account Number *</label>
                    <input type="text" name="account_number" id="accountNumber" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">IFSC Code</label>
                    <input type="text" name="ifsc_code" id="ifscCode" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Branch</label>
                    <input type="text" name="branch_name" id="branchName" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Opening Balance *</label>
                    <input type="number" step="0.01" min="0" name="opening_balance" id="openingBalance" class="form-control" value="0" required>
                    <div class="form-text">A new account is always created as Active.</div>
                </div>
            </div>

            <div id="formErrors" class="alert alert-danger mt-3" style="display:none;"></div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Save</button>
                <a href="<?= base_url('bank-accounts') ?>" class="btn-cancel"><i class="bi bi-x-lg"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$('#bankAccountForm').on('submit', function (e) {
		e.preventDefault();

		var openingBalance = parseFloat($('#openingBalance').val() || 0);
		if (openingBalance < 0) {
			$('#formErrors').text('Opening balance cannot be negative.').show();
			return;
		}

		$.ajax({
			url: "<?= base_url('bank-accounts/store') ?>",
			type: 'POST',
			dataType: 'json',
			data: $('#bankAccountForm').serialize()
		}).done(function (resp) {
			if (resp.status) {
				window.location.href = "<?= base_url('bank-accounts') ?>";
			} else {
				$('#formErrors').html((resp.errors || ['Failed to save bank account.']).join('<br>')).show();
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			$('#formErrors').html(((data && data.errors) || ['A network error occurred.']).join('<br>')).show();
		});
	});
</script>
<?= $this->endSection() ?>
