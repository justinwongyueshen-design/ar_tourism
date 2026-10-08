<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

$id  = input_int('id');
$row = $id ? destination_find($id) : null;
if ($id && !$row) { flash('danger', 'Destination not found.'); redirect('admin/destinations/index.php'); }
$row = $row ?? [];
$errors = [];

if (is_post()) {
    csrf_require();
    $name  = mb_substr(input('name'), 0, 150);
    $short = mb_substr(input('short_description'), 0, 300);
    $full  = mb_substr(input('full_description'), 0, 20000);
    $loc   = mb_substr(input('location'), 0, 200);
    $catId = input_int('category_id') ?: null;
    $lat   = clean_coordinate(input('latitude'), -90, 90);
    $lng   = clean_coordinate(input('longitude'), -180, 180);
    $mapsRaw = input('maps_url');
    $maps  = $mapsRaw === '' ? null : clean_url($mapsRaw);
    $status = input('status') === 'inactive' ? 'inactive' : 'active';
    $featured = isset($_POST['featured']) ? 1 : 0;

    if ($name === '') { $errors['name'] = 'Name is required.'; }
    if (input('latitude') !== '' && $lat === null) { $errors['latitude'] = 'Latitude must be between -90 and 90.'; }
    if (input('longitude') !== '' && $lng === null) { $errors['longitude'] = 'Longitude must be between -180 and 180.'; }
    if ($mapsRaw !== '' && $maps === null) { $errors['maps_url'] = 'Enter a valid http(s) URL.'; }
    if ($catId !== null && !category_find($catId)) { $catId = null; }

    $cover = $row['cover_image'] ?? null;
    $newCover = null;
    if (!$errors && has_upload('cover_image')) {
        $up = handle_image_upload(uploaded_files_list('cover_image')[0], 'destinations', MAX_IMAGE_BYTES, ['min_width' => 400]);
        if ($up['ok']) { $newCover = $up['path']; } else { $errors['cover_image'] = $up['error']; }
    }
    if (!$errors) {
        if ($newCover) { $cover = $newCover; }
        $slug = unique_slug('destinations', $name, $id ?: null);
        $params = [$catId, $name, $slug, $short, $full, $cover, $loc, $lat, $lng, $maps, $featured, $status];
        if ($id) {
            db_exec('UPDATE destinations SET category_id=?, name=?, slug=?, short_description=?, full_description=?, cover_image=?, location=?, latitude=?, longitude=?, maps_url=?, featured=?, status=? WHERE id=?', [...$params, $id]);
            if ($newCover) { delete_upload($row['cover_image'] ?? null); }
            log_activity('destination_updated', 'Admin updated destination "' . $name . '"');
            flash('success', 'Destination updated.');
        } else {
            db_exec('INSERT INTO destinations (category_id, name, slug, short_description, full_description, cover_image, location, latitude, longitude, maps_url, featured, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', $params);
            log_activity('destination_added', 'Admin added destination "' . $name . '"');
            flash('success', 'Destination added.');
        }
        redirect('admin/destinations/index.php');
    } elseif ($newCover) {
        delete_upload($newCover);
    }
}
$cats = categories_list();
$adminTitle = $id ? 'Edit destination' : 'Add destination';
$adminActive = 'destinations';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<form method="post" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
<div class="row g-4">
  <div class="col-lg-8"><div class="card"><div class="card-body">
    <div class="mb-3"><label class="form-label required" for="name">Destination name</label>
      <input class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name" maxlength="150" value="<?= e(fv($row, 'name')) ?>" required><?= field_error($errors, 'name') ?></div>
    <div class="mb-3"><label class="form-label" for="short_description">Short description</label>
      <input class="form-control" id="short_description" name="short_description" maxlength="300" value="<?= e(fv($row, 'short_description')) ?>"></div>
    <div class="mb-3"><label class="form-label" for="full_description">Full description</label>
      <textarea class="form-control" id="full_description" name="full_description" rows="8"><?= e(fv($row, 'full_description')) ?></textarea><div class="form-text">Leave a blank line between paragraphs.</div></div>
    <div class="row g-3">
      <div class="col-md-12"><label class="form-label" for="location">Location</label><input class="form-control" id="location" name="location" maxlength="200" value="<?= e(fv($row, 'location')) ?>"></div>
      <div class="col-md-6"><label class="form-label" for="latitude">Latitude</label><input class="form-control<?= invalid_class($errors, 'latitude') ?>" id="latitude" name="latitude" inputmode="decimal" value="<?= e(fv($row, 'latitude')) ?>"><?= field_error($errors, 'latitude') ?></div>
      <div class="col-md-6"><label class="form-label" for="longitude">Longitude</label><input class="form-control<?= invalid_class($errors, 'longitude') ?>" id="longitude" name="longitude" inputmode="decimal" value="<?= e(fv($row, 'longitude')) ?>"><?= field_error($errors, 'longitude') ?></div>
      <div class="col-12"><label class="form-label" for="maps_url">Google Maps URL (optional)</label><input class="form-control<?= invalid_class($errors, 'maps_url') ?>" id="maps_url" name="maps_url" maxlength="500" value="<?= e(fv($row, 'maps_url')) ?>" placeholder="https://maps.google.com/…"><?= field_error($errors, 'maps_url') ?>
        <div class="form-text">If empty, a map link is built from the latitude/longitude.</div></div>
    </div>
  </div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <div class="mb-3"><label class="form-label" for="category_id">Category</label>
      <select class="form-select" id="category_id" name="category_id"><?= select_options($cats, fv($row, 'category_id'), 'id', 'name', '— none —') ?></select></div>
    <div class="mb-3"><label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status"><option value="active" <?= fv($row, 'status', 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= fv($row, 'status') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="featured" name="featured" <?= (is_post() ? isset($_POST['featured']) : !empty($row['featured'])) ? 'checked' : '' ?>><label class="form-check-label" for="featured">Featured on home page</label></div>
    <div class="mb-3"><label class="form-label" for="cover_image">Cover image</label>
      <img id="coverPreview" class="img-fluid rounded mb-2" src="<?= e(image_url($row['cover_image'] ?? null)) ?>" alt="Current cover image" <?= empty($row['cover_image']) ? 'hidden' : '' ?>>
      <input class="form-control<?= invalid_class($errors, 'cover_image') ?>" type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp" data-preview="#coverPreview"><?= field_error($errors, 'cover_image') ?>
      <div class="form-text">JPG, PNG or WEBP, up to <?= (int) (MAX_IMAGE_BYTES / 1048576) ?> MB, at least 400px wide.</div></div>
    <button class="btn btn-success w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save destination</button>
    <a class="btn btn-light w-100 mt-2" href="<?= e(url('admin/destinations/index.php')) ?>">Cancel</a>
  </div></div></div>
</div></form>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
