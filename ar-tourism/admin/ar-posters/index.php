<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

/** Remove every compiled-target file that belongs to a poster. */
function delete_poster_targets(int $posterId, ?string $current): void
{
    $files = array_column(db_all('SELECT file_path FROM ar_targets WHERE poster_id = ?', [$posterId]), 'file_path');
    if ($current) { $files[] = $current; }
    foreach (array_unique($files) as $f) {
        $path = APP_ROOT . '/assets/ar-targets/' . basename((string) $f);
        if (is_file($path)) { @unlink($path); }
    }
}

if (is_post()) {
    csrf_require();
    $id = input_int('id');
    $p = poster_find($id);
    if ($p && input('action') === 'delete') {
        delete_poster_targets($id, $p['target_file']);
        db_exec('DELETE FROM ar_posters WHERE id = ?', [$id]);
        delete_upload($p['poster_image']);
        log_activity('poster_deleted', 'Admin deleted AR poster "' . $p['poster_name'] . '"');
        flash('success', 'AR poster deleted.');
    } elseif ($p && input('action') === 'publish') {
        $new = $p['status'] === 'published' ? 'draft' : 'published';
        if ($new === 'published' && !poster_is_scannable($p)) {
            flash('warning', 'Poster published, but it cannot be scanned until its target is compiled.');
        }
        db_exec('UPDATE ar_posters SET status = ? WHERE id = ?', [$new, $id]);
        log_activity('poster_updated', 'Admin set AR poster "' . $p['poster_name'] . '" to ' . $new);
        flash('success', 'Poster is now ' . $new . '.');
    }
    redirect('admin/ar-posters/index.php');
}

$q = input('q');
$posters = posters_list($q);
$goto = input('goto');
$adminTitle = 'AR Posters';
$adminActive = $goto === 'editor' ? 'hotspots' : ($goto === 'compile' ? 'compile' : 'posters');
require dirname(__DIR__) . '/partials/layout_start.php';
if ($goto === 'editor') { echo '<div class="alert alert-info">Choose a poster and click <strong>Hotspots</strong> to open the visual editor.</div>'; }
if ($goto === 'compile') { echo '<div class="alert alert-info">Choose a poster and click <strong>Compile</strong> to build its MindAR target.</div>'; }
admin_toolbar(url('admin/ar-posters/form.php'), 'Add AR poster', $q, 'Search posters…');
?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th></th><th>Poster</th><th>Hotspots</th><th>Target status</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($posters as $p): ?>
    <tr>
      <td><img class="thumb" src="<?= e(image_url($p['poster_image'])) ?>" alt="" loading="lazy"></td>
      <td class="fw-semibold"><?= e($p['poster_name']) ?><br><small class="text-muted fw-normal"><?= (int) $p['image_width'] ?>×<?= (int) $p['image_height'] ?>px</small></td>
      <td><?= (int) $p['hotspot_count'] ?></td>
      <td><?= status_badge($p['target_status']) ?><?= $p['target_compiled_at'] ? '<br><small class="text-muted">' . e(date('d M Y H:i', strtotime((string) $p['target_compiled_at']))) . '</small>' : '' ?></td>
      <td><?= status_badge($p['status']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-success" href="<?= e(url('admin/ar-posters/editor.php?id=' . (int) $p['id'])) ?>"><i class="fa-solid fa-bullseye me-1"></i>Hotspots</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/ar-posters/compile.php?id=' . (int) $p['id'])) ?>"><i class="fa-solid fa-microchip me-1"></i><?= $p['target_status'] === 'READY' ? 'Recompile' : 'Compile' ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('ar.php?poster=' . (int) $p['id'])) ?>" target="_blank" rel="noopener" title="Preview AR"><i class="fa-solid fa-vr-cardboard"></i><span class="visually-hidden">Preview AR</span></a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-sm btn-outline-secondary" type="submit" title="Publish / unpublish"><i class="fa-solid fa-<?= $p['status'] === 'published' ? 'eye-slash' : 'upload' ?>"></i><span class="visually-hidden">Publish or unpublish</span></button></form>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/ar-posters/form.php?id=' . (int) $p['id'])) ?>" title="Edit"><i class="fa-solid fa-pen"></i><span class="visually-hidden">Edit</span></a>
        <?= delete_form('delete', (int) $p['id'], 'Delete poster "' . $p['poster_name'] . '", its hotspots and its compiled targets?') ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$posters): ?><tr><td colspan="6" class="text-center text-muted py-4">No AR posters yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
