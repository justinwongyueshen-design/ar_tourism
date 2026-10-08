<?php
declare(strict_types=1);

function render_destination_card(array $d): string
{
    $href = url('destination.php?slug=' . rawurlencode($d['slug']));
    ob_start(); ?>
    <article class="card h-100 tour-card">
      <a href="<?= e($href) ?>" class="tour-card-img">
        <img src="<?= e(image_url($d['cover_image'])) ?>" alt="<?= e($d['name']) ?>" loading="lazy" width="600" height="400">
        <?php if (!empty($d['category_name'])): ?><span class="badge tour-badge"><i class="fa-solid <?= e($d['category_icon'] ?? 'fa-landmark') ?> me-1"></i><?= e($d['category_name']) ?></span><?php endif; ?>
      </a>
      <div class="card-body">
        <h3 class="h5 card-title"><a href="<?= e($href) ?>" class="stretched-link text-decoration-none"><?= e($d['name']) ?></a></h3>
        <p class="card-text text-muted small mb-2"><?= e(short_text($d['short_description'], 110)) ?></p>
        <div class="small text-muted"><i class="fa-solid fa-location-dot me-1"></i><?= e($d['location']) ?>
          <?php if (isset($d['attraction_count'])): ?><span class="ms-2"><i class="fa-solid fa-landmark me-1"></i><?= (int) $d['attraction_count'] ?></span><?php endif; ?></div>
      </div>
    </article>
    <?php return (string) ob_get_clean();
}

function render_attraction_card(array $a): string
{
    $href = url('attraction.php?slug=' . rawurlencode($a['slug']));
    ob_start(); ?>
    <article class="card h-100 tour-card">
      <a href="<?= e($href) ?>" class="tour-card-img">
        <img src="<?= e(image_url($a['main_image'])) ?>" alt="<?= e($a['name']) ?>" loading="lazy" width="600" height="400">
        <?php if (!empty($a['category_name'])): ?><span class="badge tour-badge"><i class="fa-solid <?= e($a['category_icon'] ?? 'fa-landmark') ?> me-1"></i><?= e($a['category_name']) ?></span><?php endif; ?>
      </a>
      <div class="card-body">
        <h3 class="h5 card-title"><a href="<?= e($href) ?>" class="stretched-link text-decoration-none"><?= e($a['name']) ?></a></h3>
        <p class="card-text text-muted small mb-2"><?= e(short_text($a['short_description'], 100)) ?></p>
        <div class="small text-muted"><i class="fa-solid fa-map-pin me-1"></i><?= e($a['destination_name']) ?></div>
      </div>
    </article>
    <?php return (string) ob_get_clean();
}
