<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
/** Release 4.9.0CC. @var array $input @var array $errors @var array|null $saved */
$err = static fn (string $k): string => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . esc($errors[$k]) . '</div>' : '';
$dmy = static fn (string $d): string => date('d-m-Y', strtotime($d));
?>
<style>
	.cob-crumb { margin-bottom: 8px; }
	.cob-crumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.cob-crumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.cob-crumb .breadcrumb-item.active { color: #64748b; }
	.cob-note { font-size: .82rem; color: #475569; background: #f1f5f9; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; }
	.cob-note ul { margin: 6px 0 0 18px; padding: 0; }
	.cob-current { font-size: .85rem; margin-bottom: 14px; }
	.cob-form { max-width: 640px; }
	.cob-locked { max-width: 640px; }
	.cob-locked dt { font-size: .75rem; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: .02em; }
	.cob-locked dd { font-size: 1.1rem; font-weight: 600; margin-bottom: 14px; }
</style>

<nav aria-label="breadcrumb" class="cob-crumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('cash-book') ?>">Cash Book</a></li>
        <li class="breadcrumb-item active" aria-current="page">Opening Balance</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-cash-stack me-2"></i>Cash Opening Balance</span>
</div>

<div class="card-custom">
    <div class="card-custom-body">
        <?php if (! empty($errors['_'])): ?><div class="alert alert-danger"><?= esc($errors['_']) ?></div><?php endif; ?>
        <?php if (! empty($errors['_lock'])): ?><div class="alert alert-danger cob-lock-error" role="alert"><?= esc($errors['_lock']) ?></div><?php endif; ?>

        <?php if ($saved): ?>
        <div class="cob-locked" id="cobLocked">
            <div class="fw-semibold text-uppercase mb-3"><i class="bi bi-lock-fill me-1"></i>Cash Opening Balance</div>
            <dl class="mb-0">
                <dt>Opening Date</dt><dd id="cobLockedDate"><?= esc(date('d/m/Y', strtotime($saved['opening_date']))) ?></dd>
                <dt>Opening Cash</dt><dd id="cobLockedAmount">₹<?= number_format($saved['amount'], 2) ?></dd>
                <dt>Remarks</dt><dd id="cobLockedRemarks"><?= trim((string) $saved['remarks']) !== '' ? esc($saved['remarks']) : '<span class="text-muted fw-normal">—</span>' ?></dd>
            </dl>
            <div class="cob-note mb-0">
                <strong>Opening Cash has already been set and is locked.</strong><br>
                It cannot be edited or removed.
            </div>
        </div>
        <?php if (! empty($hidden['count'])): ?>
        <div class="alert alert-warning py-2 mt-3 mb-0 cob-hidden" role="alert">
            <?= (int) $hidden['count'] ?> recorded cash transaction(s) are dated before <?= esc($dmy($saved['opening_date'])) ?> and are excluded from the Cash Book and Monthly Statement (earliest: <?= esc($dmy($hidden['earliest']['date'])) ?>). They stay recorded in their own ledgers.
        </div>
        <?php endif; ?>
        <div class="mt-3"><a href="<?= base_url('cash-book') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back to Cash Book</a></div>
        <?php else: ?>
        <div class="cob-note">
            <strong>Set Opening Cash</strong>
            <ul>
                <li>The physical cash held when the system starts. It is a balance brought forward, not income, a receipt or a bank deposit, and it creates no transaction.</li>
                <li><strong>It can be saved only once.</strong> After that it is locked permanently: it cannot be edited, replaced or removed.</li>
                <li>The Cash Book and Monthly Statement start from this amount on the opening date; cash dated before it is not counted.</li>
            </ul>
        </div>
        <form method="post" action="<?= base_url('cash-book/opening-balance') ?>" class="cob-form" id="cobForm" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="cobDate">Opening Date *</label>
                    <input type="date" name="opening_date" id="cobDate" class="form-control<?= isset($errors['opening_date']) ? ' is-invalid' : '' ?>" value="<?= esc($input['opening_date'] ?? '', 'attr') ?>" required>
                    <?= $err('opening_date') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cobAmount">Opening Cash (₹) *</label>
                    <input type="text" inputmode="decimal" name="amount" id="cobAmount" class="form-control<?= isset($errors['amount']) ? ' is-invalid' : '' ?>" value="<?= esc($input['amount'] ?? '', 'attr') ?>" placeholder="0.00" required>
                    <?= $err('amount') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="cobRemarks">Remarks</label>
                    <input type="text" name="remarks" id="cobRemarks" maxlength="255" class="form-control<?= isset($errors['remarks']) ? ' is-invalid' : '' ?>" value="<?= esc($input['remarks'] ?? '', 'attr') ?>" placeholder="Physical cash available at system initialization">
                    <?= $err('remarks') ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4 flex-wrap">
                <button type="submit" class="btn-save" id="cobSave"><i class="bi bi-lock"></i> Save Opening Cash</button>
                <a href="<?= base_url('cash-book') ?>" class="btn-cancel"><i class="bi bi-x-lg"></i> Back to Cash Book</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
