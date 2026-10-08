<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$counts = [
    ['Destinations', 'fa-map-location-dot', (int) db_val('SELECT COUNT(*) FROM destinations'), 'admin/destinations/index.php', 'primary'],
    ['Attractions',  'fa-landmark',          (int) db_val('SELECT COUNT(*) FROM attractions'),  'admin/attractions/index.php',  'success'],
    ['AR Posters',   'fa-image',             (int) db_val('SELECT COUNT(*) FROM ar_posters'),   'admin/ar-posters/index.php',   'warning'],
    ['AR Hotspots',  'fa-bullseye',          (int) db_val('SELECT COUNT(*) FROM ar_hotspots'),  'admin/ar-posters/index.php',   'danger'],
    ['Categories',   'fa-tags',              (int) db_val('SELECT COUNT(*) FROM categories'),   'admin/categories/index.php',   'info'],
];
$recentAttractions = db_all('SELECT a.id, a.name, a.status, a.updated_at, d.name AS destination_name FROM attractions a JOIN destinations d ON d.id = a.destination_id ORDER BY a.created_at DESC, a.id DESC LIMIT 5');
$recentPosters = db_all('SELECT id, poster_name, target_status, status, created_at FROM ar_posters ORDER BY created_at DESC, id DESC LIMIT 5');
$recentUpdates = db_all("(SELECT 'Destination' AS kind, name, updated_at FROM destinations) UNION ALL (SELECT 'Attraction', name, updated_at FROM attractions) UNION ALL (SELECT 'AR Poster', poster_name, updated_at FROM ar_posters) ORDER BY updated_at DESC LIMIT 6");
$activity = db_all('SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8');
$notCompiled = (int) db_val("SELECT COUNT(*) FROM ar_posters WHERE target_status <> 'READY'");
$installerPresent = is_dir(APP_ROOT . '/install') && is_file(APP_ROOT . '/install/index.php');

$adminTitle = 'Dashboard';
$adminActive = 'dashboard';
require __DIR__ . '/partials/layout_start.php';
?>
<?php if (!is_secure_context_server()): ?>
  <div class="alert alert-warning"><i class="fa-solid fa-lock-open me-2"></i><strong>Camera access requires HTTPS.</strong> Please access this website using an HTTPS URL. Phones will refuse to open the camera on plain HTTP (only <code>localhost</code> is exempt).</div>
<?php endif; ?>
<?php if ($installerPresent): ?>
  <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>The <code>install/</code> folder is still on the server. Delete it now that setup is finished.</div>
<?php endif; ?>
<?php if ($notCompiled > 0): ?>
  <div class="alert alert-info"><i class="fa-solid fa-microchip me-2"></i><?= $notCompiled ?> AR poster(s) are not compiled yet and cannot be scanned. <a href="<?= e(url('admin/ar-posters/index.php?goto=compile')) ?>">Compile now</a></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ($counts as [$label, $icon, $n, $href, $color]): ?>
    <div class="col-6 col-md-4 col-xl"><a class="stat-card text-decoration-none" href="<?= e(url($href)) ?>"><span class="stat-icon text-bg-<?= e($color) ?>"><i class="fa-solid <?= e($icon) ?>"></i></span><div><div class="stat-n"><?= $n ?></div><div class="stat-l"><?= e($label) ?></div></div></a></div>
  <?php endforeach; ?>
</div>

<div class="card mb-4"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
  <strong class="me-2">Quick actions</strong>
  <a class="btn btn-sm btn-success" href="<?= e(url('admin/destinations/form.php')) ?>"><i class="fa-solid fa-plus me-1"></i>Destination</a>
  <a class="btn btn-sm btn-success" href="<?= e(url('admin/attractions/form.php')) ?>"><i class="fa-solid fa-plus me-1"></i>Attraction</a>
  <a class="btn btn-sm btn-success" href="<?= e(url('admin/ar-posters/form.php')) ?>"><i class="fa-solid fa-plus me-1"></i>AR Poster</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('ar.php')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-vr-cardboard me-1"></i>Open AR page</a>
</div></div>

<div class="row g-4">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Recent attractions</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($recentAttractions as $a): ?><li class="list-group-item d-flex justify-content-between align-items-center"><span><a href="<?= e(url('admin/attractions/form.php?id=' . (int) $a['id'])) ?>"><?= e($a['name']) ?></a><br><small class="text-muted"><?= e($a['destination_name']) ?></small></span><?= status_badge($a['status']) ?></li><?php endforeach; ?>
      <?php if (!$recentAttractions): ?><li class="list-group-item text-muted">Nothing yet.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Recent AR posters</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($recentPosters as $p): ?><li class="list-group-item d-flex justify-content-between align-items-center"><a href="<?= e(url('admin/ar-posters/editor.php?id=' . (int) $p['id'])) ?>"><?= e($p['poster_name']) ?></a><span><?= status_badge($p['target_status']) ?> <?= status_badge($p['status']) ?></span></li><?php endforeach; ?>
      <?php if (!$recentPosters): ?><li class="list-group-item text-muted">Nothing yet.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Recently updated content</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($recentUpdates as $u): ?><li class="list-group-item d-flex justify-content-between"><span><span class="badge text-bg-light me-2"><?= e($u['kind']) ?></span><?= e($u['name']) ?></span><small class="text-muted"><?= e(date('d M H:i', strtotime((string) $u['updated_at']))) ?></small></li><?php endforeach; ?>
    </ul></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex justify-content-between">System activity<?php if (is_super_admin()): ?><a href="<?= e(url('admin/logs/index.php')) ?>" class="small">All logs</a><?php endif; ?></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($activity as $l): ?><li class="list-group-item"><div class="small"><strong><?= e($l['username'] ?? 'system') ?></strong> · <?= e($l['description'] ?: $l['action']) ?></div><small class="text-muted"><?= e(date('d M H:i', strtotime((string) $l['created_at']))) ?></small></li><?php endforeach; ?>
      <?php if (!$activity): ?><li class="list-group-item text-muted">No activity yet.</li><?php endif; ?>
    </ul></div></div>
</div>
<?php require __DIR__ . '/partials/layout_end.php';
