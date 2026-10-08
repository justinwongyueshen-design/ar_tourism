<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/partials/cards.php';

$pageTitle = '';
$navActive = 'home';
$stats = [
    ['fa-map-location-dot', 'Destinations', (int) db_val("SELECT COUNT(*) FROM destinations WHERE status = 'active'")],
    ['fa-landmark',         'Attractions',  (int) db_val("SELECT COUNT(*) FROM attractions WHERE status = 'active'")],
    ['fa-vr-cardboard',     'AR Posters',   (int) db_val("SELECT COUNT(*) FROM ar_posters WHERE status = 'published'")],
    ['fa-tags',             'Categories',   (int) db_val("SELECT COUNT(*) FROM categories WHERE status = 'active'")],
];
$categories   = categories_list(true);
$featuredDest = destinations_query(['featured' => true], 1, 3, true)['rows'];
$featuredAttr = attractions_query(['featured' => true], 1, 6, true)['rows'];
$popular      = attractions_query(['order' => 'popular'], 1, 4, true)['rows'];
$arUrl        = base_url('ar.php');

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="hero-kicker"><i class="fa-solid fa-camera me-2"></i><?= e(setting('tagline')) ?></span>
        <h1 class="display-5 fw-bold mt-3"><?= e(setting('hero_title')) ?></h1>
        <p class="lead mb-4"><?= e(setting('hero_subtitle')) ?></p>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <a class="btn btn-gold btn-lg" href="<?= e(url('ar.php')) ?>"><i class="fa-solid fa-vr-cardboard me-2"></i>Start AR Experience</a>
          <a class="btn btn-outline-light btn-lg" href="<?= e(url('destinations.php')) ?>">Browse destinations</a>
        </div>
        <form class="hero-search position-relative" action="<?= e(url('attractions.php')) ?>" method="get" role="search" autocomplete="off">
          <label for="heroSearch" class="visually-hidden">Search destinations, attractions or categories</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="search" class="form-control" id="heroSearch" name="q" placeholder="Search destinations, attractions, categories…" data-live-search aria-controls="searchResults">
            <button class="btn btn-gold" type="submit">Search</button>
          </div>
          <div id="searchResults" class="search-results shadow" hidden role="listbox"></div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="qr-card text-center">
          <p class="fw-semibold mb-3">SCAN TO START AR EXPERIENCE</p>
          <div id="qrcode" class="qr-box" data-url="<?= e($arUrl) ?>" aria-label="QR code that opens the AR experience"></div>
          <p class="small text-muted mt-3 mb-0 text-break"><?= e($arUrl) ?></p>
          <noscript><p class="small mt-2">Open <?= e($arUrl) ?> on your phone.</p></noscript>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="container stats-strip">
  <div class="row g-3">
    <?php foreach ($stats as [$icon, $label, $n]): ?>
      <div class="col-6 col-md-3"><div class="stat-tile"><i class="fa-solid <?= e($icon) ?>"></i><div><strong><?= $n ?></strong><span><?= e($label) ?></span></div></div></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="container section">
  <h2 class="section-title">Browse by category</h2>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($categories as $c): ?>
      <a class="chip" href="<?= e(url('attractions.php?category=' . (int) $c['id'])) ?>"><i class="fa-solid <?= e($c['icon']) ?> me-2"></i><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($featuredDest): ?>
<section class="container section">
  <div class="d-flex justify-content-between align-items-end mb-3"><h2 class="section-title mb-0">Featured destinations</h2><a href="<?= e(url('destinations.php')) ?>">View all</a></div>
  <div class="row g-4"><?php foreach ($featuredDest as $d): ?><div class="col-md-6 col-lg-4"><?= render_destination_card($d) ?></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<?php if ($featuredAttr): ?>
<section class="container section">
  <div class="d-flex justify-content-between align-items-end mb-3"><h2 class="section-title mb-0">Featured attractions</h2><a href="<?= e(url('attractions.php')) ?>">View all</a></div>
  <div class="row g-4"><?php foreach ($featuredAttr as $a): ?><div class="col-sm-6 col-lg-4"><?= render_attraction_card($a) ?></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<?php if ($popular): ?>
<section class="container section">
  <h2 class="section-title">Popular attractions</h2>
  <div class="row g-4"><?php foreach ($popular as $a): ?><div class="col-sm-6 col-lg-3"><?= render_attraction_card($a) ?></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="container section">
  <div class="cta-ar">
    <div><h2 class="h3 mb-1">See it come alive</h2><p class="mb-0">Open the AR camera and point it at a tourism poster to reveal videos, maps and stories.</p></div>
    <a class="btn btn-gold btn-lg" href="<?= e(url('ar.php')) ?>"><i class="fa-solid fa-camera me-2"></i>Start AR</a>
  </div>
</section>
<?php
$extraScripts = '<script src="' . e(vendor('qrcode/qrcode.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js')) . '" defer></script>';
require __DIR__ . '/includes/footer.php';
