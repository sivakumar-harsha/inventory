<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - A&A System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-key-fill"></i>
        </div>
        <h4 class="login-title">Forgot Password</h4>
        <p class="login-subtitle">Enter your registered email to receive a verification code</p>

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

        <form action="<?= base_url('forgot-password/send') ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Registered Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your email address" required autofocus>
            </div>
            <button type="submit" class="btn-save w-100 mt-2">
                <i class="bi bi-send-fill me-2"></i> Send OTP
            </button>
        </form>

        <p class="login-hint">
            <a href="<?= base_url('login') ?>">Back to Login</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
