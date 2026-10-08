<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('admin/index.php');
}
$error = '';
$identifier = '';
$next = input('next');
$needsInstall = false;
try {
    $needsInstall = (int) db_val('SELECT COUNT(*) FROM users') === 0;
} catch (Throwable $e) {
    $needsInstall = true;
}

if (is_post()) {
    csrf_require();
    $identifier = input('username');
    $result = attempt_login($identifier, (string) ($_POST['password'] ?? ''), isset($_POST['remember']));
    if ($result['ok']) {
        redirect(safe_next(input('next')));
    }
    $error = $result['message'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
  <title>Admin login · <?= e(setting('site_name', APP_NAME)) ?></title>
  <link rel="icon" href="<?= e(asset('images/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap">
  <link rel="stylesheet" href="<?= e(vendor('bootstrap/css/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@' . BOOTSTRAP_VERSION . '/dist/css/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(vendor('fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/' . FONTAWESOME_VERSION . '/css/all.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin login-page">
<main class="login-card">
  <div class="text-center mb-4"><img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="64" height="64"><h1 class="h4 mt-3 mb-1"><?= e(setting('site_name', APP_NAME)) ?></h1><p class="text-muted mb-0">Administrator login</p></div>
  <?php if ($needsInstall): ?><div class="alert alert-warning small">No administrator exists yet. Run the <a href="<?= e(url('install/')) ?>">installer</a> first.</div><?php endif; ?>
  <?php if (!is_secure_context_server()): ?><div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i>This page is not using HTTPS. Passwords are sent unencrypted. Use HTTPS on a live server.</div><?php endif; ?>
  <?= flash_render() ?>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="mb-3"><label class="form-label" for="username">Username or email</label>
      <input class="form-control form-control-lg" id="username" name="username" value="<?= e($identifier) ?>" required autofocus autocomplete="username"></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label>
      <input class="form-control form-control-lg" type="password" id="password" name="password" required autocomplete="current-password"></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="remember" name="remember"><label class="form-check-label" for="remember">Remember me for 14 days</label></div>
    <button class="btn btn-success btn-lg w-100" type="submit"><i class="fa-solid fa-right-to-bracket me-2"></i>Login</button>
  </form>
  <p class="text-center mt-3 mb-0"><a href="<?= e(url('index.php')) ?>" class="small">&larr; Back to website</a></p>
</main>
</body>
</html>
