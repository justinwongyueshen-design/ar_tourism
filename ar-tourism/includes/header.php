<?php
/**
 * Public site header.
 * Set before including: $pageTitle (string), $metaDescription (string), $navActive (string)
 */
declare(strict_types=1);
$pageTitle       = $pageTitle ?? '';
$metaDescription = $metaDescription ?? setting('tagline', 'Explore tourism with augmented reality');
$navActive       = $navActive ?? '';
$siteName        = setting('site_name', APP_NAME);
$nav = [
    'home'         => ['Home', 'index.php'],
    'destinations' => ['Destinations', 'destinations.php'],
    'attractions'  => ['Attractions', 'attractions.php'],
    'ar'           => ['AR Experience', 'ar.php'],
    'about'        => ['About', 'about.php'],
    'contact'      => ['Contact', 'contact.php'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle !== '' ? $pageTitle . ' · ' . $siteName : $siteName) ?></title>
  <meta name="description" content="<?= e($metaDescription) ?>">
  <meta name="theme-color" content="#0b6e4f">
  <link rel="icon" href="<?= e(asset('images/logo.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(vendor('bootstrap/css/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@' . BOOTSTRAP_VERSION . '/dist/css/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(vendor('fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/' . FONTAWESOME_VERSION . '/css/all.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="site">
<a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>
<nav class="navbar navbar-expand-lg site-nav sticky-top" aria-label="Main navigation">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('index.php')) ?>">
      <img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="36" height="36"><span><?= e($siteName) ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php foreach ($nav as $key => [$label, $href]): ?>
          <li class="nav-item">
            <a class="nav-link <?= $navActive === $key ? 'active' : '' ?><?= $key === 'ar' ? ' nav-ar' : '' ?>" <?= $navActive === $key ? 'aria-current="page"' : '' ?> href="<?= e(url($href)) ?>">
              <?php if ($key === 'ar'): ?><i class="fa-solid fa-vr-cardboard me-1"></i><?php endif; ?><?= e($label) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</nav>
<main id="main">
