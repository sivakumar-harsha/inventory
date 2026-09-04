<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - A&A System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <style>
        .password-wrap { position: relative; }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #64748b;
        }
        .strength-bar {
            height: 5px;
            border-radius: 3px;
            background: #e2e8f0;
            margin-top: 8px;
            overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: width 0.2s ease, background 0.2s ease;
        }
        .strength-label {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 4px;
        }
    </style>
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-lock-fill"></i>
        </div>
        <h4 class="login-title">Reset Password</h4>
        <p class="login-subtitle">Set a new password for your account</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= session()->getFlashdata('error') ?>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('forgot-password/reset') ?>" method="POST" id="resetForm">
            <div class="form-section">
                <label class="form-label">New Password</label>
                <div class="password-wrap">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required>
                    <i class="bi bi-eye-fill password-toggle" data-target="password"></i>
                </div>
                <div class="strength-bar"><div class="strength-bar-fill" id="strengthBarFill"></div></div>
                <div class="strength-label" id="strengthLabel">Min 8 characters, 1 uppercase, 1 lowercase, 1 number</div>
            </div>
            <div class="form-section">
                <label class="form-label">Confirm Password</label>
                <div class="password-wrap">
                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Re-enter new password" required>
                    <i class="bi bi-eye-fill password-toggle" data-target="confirmPassword"></i>
                </div>
            </div>
            <button type="submit" class="btn-save w-100 mt-2">
                <i class="bi bi-check-circle-fill me-2"></i> Update Password
            </button>
        </form>

        <p class="login-hint">
            <a href="<?= base_url('login') ?>">Back to Login</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.password-toggle').forEach(function (icon) {
    icon.addEventListener('click', function () {
        var input = document.getElementById(icon.getAttribute('data-target'));
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye-fill');
            icon.classList.add('bi-eye-slash-fill');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash-fill');
            icon.classList.add('bi-eye-fill');
        }
    });
});

document.getElementById('password').addEventListener('input', function () {
    var val = this.value;
    var score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var fill = document.getElementById('strengthBarFill');
    var label = document.getElementById('strengthLabel');
    var colors = ['#dc2626', '#dc2626', '#f59e0b', '#2563eb', '#16a34a', '#16a34a'];
    var labels = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong', 'Strong'];
    var pct = (score / 5) * 100;

    fill.style.width = val.length ? pct + '%' : '0%';
    fill.style.background = colors[score];
    label.textContent = val.length ? labels[score] : 'Min 8 characters, 1 uppercase, 1 lowercase, 1 number';
});
</script>
</body>
</html>
