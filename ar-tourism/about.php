<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'About';
$navActive = 'about';
require __DIR__ . '/includes/header.php';
$steps = [
  ['fa-camera', 'Open the AR page', 'Tap Start AR on your phone and allow camera access.'],
  ['fa-image', 'Scan a poster', 'Point the camera at a tourism poster until it is recognised.'],
  ['fa-hand-pointer', 'Tap an attraction', 'Highlighted areas appear over the poster. Tap one to open its card.'],
  ['fa-play', 'Watch and explore', 'Play a video, open the map or read more details.'],
];
?>
<header class="page-head"><div class="container"><h1>About</h1></div></header>
<div class="container section">
  <div class="row g-4"><div class="col-lg-8"><?= paragraphs(setting('about_text')) ?>
    <p>It runs in the browser: there is nothing to install. For the best results use a recent version of Chrome on Android or Safari on iPhone, and make sure the page is opened over HTTPS.</p></div></div>
  <h2 class="section-title mt-4">How it works</h2>
  <div class="row g-3">
    <?php foreach ($steps as $i => [$icon, $title, $text]): ?>
      <div class="col-sm-6 col-lg-3"><div class="step-tile"><span class="step-n"><?= $i + 1 ?></span><i class="fa-solid <?= e($icon) ?>"></i><h3 class="h6 mt-2"><?= e($title) ?></h3><p class="small text-muted mb-0"><?= e($text) ?></p></div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
