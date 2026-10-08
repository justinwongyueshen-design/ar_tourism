<?php declare(strict_types=1); ?>
</main>
<footer class="site-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-md-5">
        <div class="d-flex align-items-center gap-2 mb-2"><img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="32" height="32"><strong class="fs-5"><?= e(setting('site_name', APP_NAME)) ?></strong></div>
        <p class="text-white-50 mb-0"><?= e(setting('tagline')) ?></p>
      </div>
      <div class="col-6 col-md-3">
        <h2 class="h6 text-uppercase text-white-50">Explore</h2>
        <ul class="list-unstyled mb-0">
          <li><a href="<?= e(url('destinations.php')) ?>">Destinations</a></li>
          <li><a href="<?= e(url('attractions.php')) ?>">Attractions</a></li>
          <li><a href="<?= e(url('ar.php')) ?>">AR Experience</a></li>
          <li><a href="<?= e(url('about.php')) ?>">About</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-4">
        <h2 class="h6 text-uppercase text-white-50">Contact</h2>
        <ul class="list-unstyled mb-0 text-white-50 small">
          <li><i class="fa-solid fa-envelope me-2"></i><?= e(setting('contact_email')) ?></li>
          <li><i class="fa-solid fa-phone me-2"></i><?= e(setting('contact_phone')) ?></li>
          <li><i class="fa-solid fa-location-dot me-2"></i><?= e(setting('contact_address')) ?></li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary my-4">
    <div class="d-flex flex-wrap justify-content-between small text-white-50">
      <span>&copy; <?= date('Y') ?> <?= e(setting('site_name', APP_NAME)) ?>. <?= e(setting('footer_text')) ?></span>
      <a href="<?= e(url('admin/login.php')) ?>" class="text-white-50">Admin</a>
    </div>
  </div>
</footer>
<script src="<?= e(vendor('bootstrap/js/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@' . BOOTSTRAP_VERSION . '/dist/js/bootstrap.bundle.min.js')) ?>"></script>
<script>window.SITE = { searchUrl: <?= json_encode(url('api/search.php')) ?>, attractionUrl: <?= json_encode(url('attraction.php?slug=')) ?>, destinationUrl: <?= json_encode(url('destination.php?slug=')) ?>, attractionsUrl: <?= json_encode(url('attractions.php?q=')) ?> };</script>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?php if (!empty($extraScripts)) { echo $extraScripts; } ?>
</body>
</html>
