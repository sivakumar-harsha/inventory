<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - A&A System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h4 class="login-title">Verify Code</h4>
        <p class="login-subtitle">
            Enter the 6-digit code sent to<br>
            <strong><?= esc($email) ?></strong>
        </p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= session()->getFlashdata('error') ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= session()->getFlashdata('success') ?>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('forgot-password/verify') ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Verification Code</label>
                <input type="text" name="otp" class="form-control text-center" style="letter-spacing:6px;font-size:1.25rem;" placeholder="------" maxlength="6" inputmode="numeric" pattern="\d{6}" required autofocus>
            </div>
            <button type="submit" class="btn-save w-100 mt-2">
                <i class="bi bi-check2-circle me-2"></i> Verify OTP
            </button>
        </form>

        <form action="<?= base_url('forgot-password/resend') ?>" method="POST" id="resendForm" class="mt-2">
            <button type="submit" class="btn-save w-100" id="resendBtn" style="background:#64748b;" disabled>
                Resend OTP (<span id="countdown"><?= (int) $resendCooldown ?></span>s)
            </button>
        </form>

        <p class="login-hint">
            <a href="<?= base_url('forgot-password') ?>">Use a different email</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var cooldown = <?= (int) $resendCooldown ?>;
    var lastSent = <?= (int) $lastSent ?>;
    var elapsed = Math.floor(Date.now() / 1000) - lastSent;
    var remaining = Math.max(cooldown - elapsed, 0);

    var btn = document.getElementById('resendBtn');
    var countdownEl = document.getElementById('countdown');

    var timer = setInterval(function () {
        remaining--;
        if (remaining <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.innerHTML = 'Resend OTP';
        } else {
            countdownEl.textContent = remaining;
        }
    }, 1000);

    if (remaining <= 0) {
        btn.disabled = false;
        btn.innerHTML = 'Resend OTP';
    } else {
        countdownEl.textContent = remaining;
    }
})();
</script>
</body>
</html>
