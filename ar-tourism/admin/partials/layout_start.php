<?php
/**
 * Admin shell (sidebar + topbar).
 * Before including set: $adminTitle (string), $adminActive (string, e.g. 'destinations')
 */
declare(strict_types=1);
$adminTitle  = $adminTitle ?? 'Admin';
$adminActive = $adminActive ?? '';
$me = current_user();
$menu = [
    ['dashboard', 'Dashboard', 'fa-gauge-high', 'admin/index.php'],
    ['_h', 'Tourism'],
    ['destinations', 'Destinations', 'fa-map-location-dot', 'admin/destinations/index.php'],
    ['attractions', 'Attractions', 'fa-landmark', 'admin/attractions/index.php'],
    ['categories', 'Categories', 'fa-tags', 'admin/categories/index.php'],
    ['_h', 'AR Management'],
    ['posters', 'AR Posters', 'fa-image', 'admin/ar-posters/index.php'],
    ['posters_add', 'Add AR Poster', 'fa-plus', 'admin/ar-posters/form.php'],
    ['hotspots', 'Hotspots', 'fa-bullseye', 'admin/ar-posters/index.php?goto=editor'],
    ['compile', 'Target Compilation', 'fa-microchip', 'admin/ar-posters/index.php?goto=compile'],
];
if (is_super_admin()) {
    $menu[] = ['_h', 'System'];
    $menu[] = ['users', 'Users', 'fa-users', 'admin/users/index.php'];
    $menu[] = ['logs', 'Activity Logs', 'fa-clock-rotate-left', 'admin/logs/index.php'];
    $menu[] = ['settings', 'Settings', 'fa-gear', 'admin/settings/index.php'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($adminTitle) ?> · Admin · <?= e(setting('site_name', APP_NAME)) ?></title>
  <link rel="icon" href="<?= e(asset('images/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(vendor('bootstrap/css/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@' . BOOTSTRAP_VERSION . '/dist/css/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(vendor('fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/' . FONTAWESOME_VERSION . '/css/all.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="admin">
<div class="admin-shell">
  <aside class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminSidebar" aria-label="Admin navigation">
    <div class="offcanvas-header d-lg-none"><span class="fw-bold text-white">Menu</span><button class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button></div>
    <div class="offcanvas-body d-flex flex-column p-0">
      <a class="side-brand" href="<?= e(url('admin/index.php')) ?>"><img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="34" height="34"><span>AR TOURISM<br><small>ADMIN</small></span></a>
      <nav class="side-nav flex-grow-1">
        <?php foreach ($menu as $m): ?>
          <?php if ($m[0] === '_h'): ?><div class="side-heading"><?= e($m[1]) ?></div>
          <?php else: ?><a class="side-link <?= $adminActive === $m[0] ? 'active' : '' ?>" href="<?= e(url($m[3])) ?>" <?= $adminActive === $m[0] ? 'aria-current="page"' : '' ?>><i class="fa-solid <?= e($m[2]) ?>"></i><?= e($m[1]) ?></a><?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <div class="side-foot">
        <a class="side-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i>View website</a>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="m-0"><?= csrf_field() ?>
          <button class="side-link w-100 border-0 bg-transparent text-start" type="submit"><i class="fa-solid fa-right-from-bracket"></i>Logout</button></form>
      </div>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
      <h1 class="h4 mb-0"><?= e($adminTitle) ?></h1>
      <div class="ms-auto small text-muted d-none d-sm-block"><i class="fa-solid fa-user me-1"></i><?= e($me['full_name'] ?: $me['username']) ?> <span class="badge text-bg-light"><?= e($me['role']) ?></span></div>
    </header>
    <div class="admin-content">
      <?= flash_render() ?>
