<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

$id  = input_int('id');
$row = $id ? poster_find($id) : null;
if ($id && !$row) { flash('danger', 'Poster not found.'); redirect('admin/ar-posters/index.php'); }
$row = $row ?? [];
$errors = [];

if (is_post()) {
    csrf_require();
    $name   = mb_substr(input('poster_name'), 0, 150);
    $desc   = mb_substr(input('description'), 0, 2000);
    $status = input('status') === 'published' ? 'published' : 'draft';
    if ($name === '') { $errors['poster_name'] = 'Poster name is required.'; }
    $upload = null;
    if (has_upload('poster_image')) {
        // Poster is stored at max 1600px wide: big enough to track well, small enough to compile fast on any computer.
        $upload = handle_image_upload(uploaded_files_list('poster_image')[0], 'posters', MAX_POSTER_BYTES, ['min_width' => 400, 'store_max_width' => 1600]);
        if (!$upload['ok']) { $errors['poster_image'] = $upload['error']; $upload = null; }
    } elseif (!$id) {
        $errors['poster_image'] = 'Please upload the poster image.';
    }
    if (!$errors) {
        if ($id) {
            if ($upload) {
                // New image => old target is invalid
                $old = poster_find($id);
                foreach (array_column(db_all('SELECT file_path FROM ar_targets WHERE poster_id = ?', [$id]), 'file_path') as $f) {
                    @unlink(APP_ROOT . '/assets/ar-targets/' . basename((string) $f));
                }
                db_exec('DELETE FROM ar_targets WHERE poster_id = ?', [$id]);
                db_exec("UPDATE ar_posters SET poster_name=?, description=?, status=?, poster_image=?, image_width=?, image_height=?, target_file=NULL, target_status='NOT_COMPILED', target_compiled_at=NULL WHERE id=?",
                    [$name, $desc, $status, $upload['path'], $upload['width'], $upload['height'], $id]);
                delete_upload($old['poster_image']);
                flash('warning', 'Poster image replaced. The AR target must be compiled again.');
            } else {
                db_exec('UPDATE ar_posters SET poster_name=?, description=?, status=? WHERE id=?', [$name, $desc, $status, $id]);
            }
            log_activity('poster_updated', 'Admin updated AR poster "' . $name . '"');
            flash('success', 'AR poster updated.');
            redirect('admin/ar-posters/index.php');
        }
        db_exec("INSERT INTO ar_posters (poster_name, description, poster_image, image_width, image_height, status, target_status) VALUES (?,?,?,?,?,?, 'NOT_COMPILED')",
            [$name, $desc, $upload['path'], $upload['width'], $upload['height'], $status]);
        $newId = db_insert_id();
        log_activity('poster_added', 'Admin uploaded AR poster "' . $name . '"');
        flash('success', 'Poster created. Now add hotspots.');
        redirect('admin/ar-posters/editor.php?id=' . $newId);
    }
}
$adminTitle = $id ? 'Edit AR poster' : 'Add AR poster';
$adminActive = 'posters';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<form method="post" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
<div class="row g-4">
  <div class="col-lg-7"><div class="card"><div class="card-body">
    <div class="mb-3"><label class="form-label required" for="poster_name">Poster name</label>
      <input class="form-control<?= invalid_class($errors, 'poster_name') ?>" id="poster_name" name="poster_name" maxlength="150" value="<?= e(fv($row, 'poster_name')) ?>" required><?= field_error($errors, 'poster_name') ?></div>
    <div class="mb-3"><label class="form-label" for="description">Description</label>
      <textarea class="form-control" id="description" name="description" rows="4"><?= e(fv($row, 'description')) ?></textarea></div>
    <div class="mb-4"><label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status"><option value="draft" <?= fv($row, 'status', 'draft') === 'draft' ? 'selected' : '' ?>>Draft (only admins can scan it)</option><option value="published" <?= fv($row, 'status') === 'published' ? 'selected' : '' ?>>Published (visitors can scan it)</option></select></div>
    <button class="btn btn-success" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
    <a class="btn btn-light" href="<?= e(url('admin/ar-posters/index.php')) ?>">Cancel</a>
  </div></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-body">
    <label class="form-label <?= $id ? '' : 'required' ?>" for="poster_image">Poster image</label>
    <img id="posterPreview" class="img-fluid rounded mb-2" src="<?= e(image_url($row['poster_image'] ?? null)) ?>" alt="Poster preview" <?= empty($row['poster_image']) ? 'hidden' : '' ?>>
    <input class="form-control<?= invalid_class($errors, 'poster_image') ?>" type="file" id="poster_image" name="poster_image" accept="image/jpeg,image/png,image/webp" data-preview="#posterPreview"><?= field_error($errors, 'poster_image') ?>
    <div class="form-text">This exact image becomes the AR tracking target.<?php if ($id): ?> <strong>Replacing it resets the compiled target.</strong><?php endif; ?></div>
    <div class="alert alert-light border small mt-3 mb-0"><strong>For reliable tracking</strong>
      <ul class="mb-0 ps-3"><li>Use the artwork exactly as printed (same crop, same aspect ratio)</li><li>Rich, detailed, high-contrast imagery works best</li><li>Avoid large flat colour areas, plain text blocks and repeating patterns</li><li>Print matte (glossy paper reflects light and breaks tracking)</li></ul></div>
  </div></div></div>
</div></form>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
