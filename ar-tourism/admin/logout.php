<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_post()) {
    csrf_require();
    logout_user();
    session_start();
    flash('success', 'You have been logged out.');
}
redirect('admin/login.php');
