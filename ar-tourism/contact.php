<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Contact';
$navActive = 'contact';
require __DIR__ . '/includes/header.php';
?>
<header class="page-head"><div class="container"><h1>Contact</h1></div></header>
<div class="container section">
  <div class="row g-4">
    <div class="col-md-6"><div class="info-box">
      <p><i class="fa-solid fa-envelope me-2"></i><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p>
      <p><i class="fa-solid fa-phone me-2"></i><?= e(setting('contact_phone')) ?></p>
      <p class="mb-0"><i class="fa-solid fa-location-dot me-2"></i><?= e(setting('contact_address')) ?></p>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
