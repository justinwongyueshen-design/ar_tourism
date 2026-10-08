<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/partials/cards.php';

$dest = destination_by_slug(input('slug'));
if (!$dest) {
    http_response_code(404);
    include __DIR__ . '/errors/404.php';
    exit;
}
$attractions = attractions_query(['destination_id' => (int) $dest['id']], 1, 60, true)['rows'];
$mapUrl = map_link($dest);
$pageTitle = $dest['name'];
$metaDescription = short_text($dest['short_description'], 150);
$navActive = 'destinations';
require __DIR__ . '/includes/header.php';
?>
<header class="detail-hero" style="background-image:url('<?= e(image_url($dest['cover_image'])) ?>')">
  <div class="container"><nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('destinations.php')) ?>">Destinations</a></li><li class="breadcrumb-item active"><?= e($dest['name']) ?></li></ol></nav>
    <h1><?= e($dest['name']) ?></h1>
    <p class="lead mb-0"><i class="fa-solid fa-location-dot me-2"></i><?= e($dest['location']) ?></p></div>
</header>
<div class="container section">
  <div class="row g-4">
    <div class="col-lg-8"><?= paragraphs($dest['full_description'] ?: $dest['short_description']) ?></div>
    <div class="col-lg-4">
      <div class="info-box">
        <?php if ($dest['category_name']): ?><p><i class="fa-solid fa-tag me-2"></i><?= e($dest['category_name']) ?></p><?php endif; ?>
        <p><i class="fa-solid fa-landmark me-2"></i><?= count($attractions) ?> attractions</p>
        <?php if ($mapUrl): ?><a class="btn btn-outline-success w-100 mb-2" target="_blank" rel="noopener noreferrer" href="<?= e($mapUrl) ?>"><i class="fa-solid fa-map me-2"></i>View on Google Maps</a><?php endif; ?>
        <a class="btn btn-success w-100" href="<?= e(url('ar.php')) ?>"><i class="fa-solid fa-vr-cardboard me-2"></i>Start AR Experience</a>
      </div>
    </div>
  </div>
  <h2 class="section-title mt-5">Attractions in <?= e($dest['name']) ?></h2>
  <?php if (!$attractions): ?><div class="alert alert-info">No attractions have been added yet.</div><?php endif; ?>
  <div class="row g-4"><?php foreach ($attractions as $a): ?><div class="col-sm-6 col-lg-4"><?= render_attraction_card($a) ?></div><?php endforeach; ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
