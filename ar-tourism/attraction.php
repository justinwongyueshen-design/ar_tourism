<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/partials/cards.php';

$a = attraction_by_slug(input('slug'));
if (!$a) {
    http_response_code(404);
    include __DIR__ . '/errors/404.php';
    exit;
}
// Count a view once per session per attraction
if (empty($_SESSION['viewed'][$a['id']])) {
    db_exec('UPDATE attractions SET views = views + 1 WHERE id = ?', [$a['id']]);
    $_SESSION['viewed'][$a['id']] = true;
}
$gallery = attraction_images((int) $a['id']);
$mapUrl  = map_link($a);
$related = attractions_query(['destination_id' => (int) $a['destination_id']], 1, 4, true)['rows'];
$related = array_values(array_filter($related, static fn($r) => (int) $r['id'] !== (int) $a['id']));
$shareUrl = base_url('attraction.php?slug=' . rawurlencode($a['slug']));

$pageTitle = $a['name'];
$metaDescription = short_text($a['short_description'], 150);
$navActive = 'attractions';
require __DIR__ . '/includes/header.php';
?>
<header class="detail-hero" style="background-image:url('<?= e(image_url($a['main_image'])) ?>')">
  <div class="container"><nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('destination.php?slug=' . rawurlencode($a['destination_slug']))) ?>"><?= e($a['destination_name']) ?></a></li>
    <li class="breadcrumb-item active"><?= e($a['name']) ?></li></ol></nav>
    <h1><?= e($a['name']) ?></h1>
    <?php if ($a['category_name']): ?><span class="badge tour-badge static"><i class="fa-solid <?= e($a['category_icon'] ?? 'fa-landmark') ?> me-1"></i><?= e($a['category_name']) ?></span><?php endif; ?>
  </div>
</header>
<div class="container section">
  <div class="row g-4">
    <div class="col-lg-8">
      <p class="lead"><?= e($a['short_description']) ?></p>
      <?= paragraphs($a['full_description']) ?>

      <?php if ($a['youtube_id']): ?>
        <h2 class="section-title mt-4">Video</h2>
        <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-sm">
          <iframe src="https://www.youtube-nocookie.com/embed/<?= e($a['youtube_id']) ?>" title="Video: <?= e($a['name']) ?>" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
        </div>
      <?php endif; ?>

      <?php if ($gallery): ?>
        <h2 class="section-title mt-4">Gallery</h2>
        <div class="row g-2 gallery-grid">
          <?php foreach ($gallery as $img): $src = image_url($img['image_path']); ?>
            <div class="col-6 col-md-4"><a href="<?= e($src) ?>" data-lightbox data-caption="<?= e($img['caption']) ?>">
              <img src="<?= e($src) ?>" alt="<?= e($img['caption'] ?: $a['name']) ?>" class="img-fluid rounded-3" loading="lazy" width="600" height="400"></a></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="col-lg-4">
      <div class="info-box">
        <h2 class="h5">Visitor information</h2>
        <?php if ($a['opening_hours']): ?><p><i class="fa-solid fa-clock me-2"></i><strong>Hours:</strong> <?= e($a['opening_hours']) ?></p><?php endif; ?>
        <?php if ($a['entry_info']): ?><p><i class="fa-solid fa-ticket me-2"></i><strong>Entry:</strong> <?= e($a['entry_info']) ?></p><?php endif; ?>
        <p><i class="fa-solid fa-map-pin me-2"></i><a href="<?= e(url('destination.php?slug=' . rawurlencode($a['destination_slug']))) ?>"><?= e($a['destination_name']) ?></a></p>
        <div class="d-grid gap-2 mt-3">
          <?php if ($mapUrl): ?><a class="btn btn-success" target="_blank" rel="noopener noreferrer" href="<?= e($mapUrl) ?>"><i class="fa-solid fa-map me-2"></i>View on Google Maps</a><?php endif; ?>
          <?php if (clean_url($a['website_url'])): ?><a class="btn btn-outline-success" target="_blank" rel="noopener noreferrer" href="<?= e((string) clean_url($a['website_url'])) ?>"><i class="fa-solid fa-globe me-2"></i>Official website</a><?php endif; ?>
          <button class="btn btn-outline-secondary" type="button" data-share data-title="<?= e($a['name']) ?>" data-url="<?= e($shareUrl) ?>"><i class="fa-solid fa-share-nodes me-2"></i>Share</button>
        </div>
      </div>
    </div>
  </div>
  <?php if ($related): ?>
    <h2 class="section-title mt-5">More in <?= e($a['destination_name']) ?></h2>
    <div class="row g-4"><?php foreach ($related as $r): ?><div class="col-sm-6 col-lg-3"><?= render_attraction_card($r) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
</div>

<div class="modal fade" id="lightboxModal" tabindex="-1" aria-label="Image preview" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content bg-dark text-white border-0">
    <div class="modal-header border-0"><span id="lightboxCaption" class="small"></span><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body text-center pt-0"><img id="lightboxImg" src="" alt="" class="img-fluid rounded"></div>
  </div></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
