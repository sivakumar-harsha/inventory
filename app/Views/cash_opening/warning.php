<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php /** Release 4.9.0CF: confirmation page for a classic (non-AJAX) form. @var string $txDate @var string $openingDate */ ?>
<style>
	.cow-card { max-width: 640px; border-left: 4px solid #f59e0b; }
	.cow-card dt { font-size: .75rem; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: .02em; }
	.cow-card dd { font-size: 1.05rem; font-weight: 600; margin-bottom: 12px; }
</style>
<div class="page-title"><span><i class="bi bi-exclamation-triangle text-warning me-2"></i>Cash Transaction Before Opening Date</span></div>
<div class="card-custom cow-card" id="cashOpeningWarning">
    <div class="card-custom-body">
        <dl class="mb-2">
            <dt>Transaction Date</dt><dd id="cowTxDate"><?= esc($txDate) ?></dd>
            <dt>Cash Opening Date</dt><dd id="cowOpenDate"><?= esc($openingDate) ?></dd>
        </dl>
        <p><?= esc($detail) ?></p>
        <form method="post" action="<?= esc($action, 'attr') ?>" class="d-flex gap-2 flex-wrap mt-3" id="cowForm">
            <?php foreach ($fields as [$n, $v]): ?>
            <input type="hidden" name="<?= esc($n, 'attr') ?>" value="<?= esc($v, 'attr') ?>">
            <?php endforeach; ?>
            <input type="hidden" name="<?= esc($tokenField, 'attr') ?>" value="<?= esc($token, 'attr') ?>">
            <a href="javascript:history.back()" class="btn-cancel" id="cowBack"><i class="bi bi-arrow-left"></i> Go Back</a>
            <button type="submit" class="btn-save" id="cowContinue"><i class="bi bi-check-lg"></i> Continue and Save</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
