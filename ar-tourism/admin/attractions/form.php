<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

$id  = input_int('id');
$row = $id ? attraction_find($id) : null;
if ($id && !$row) { flash('danger', 'Attraction not found.'); redirect('admin/attractions/index.php'); }
$row = $row ?? [];
$errors = [];
$gallery = $id ? attraction_images($id) : [];

if (is_post()) {
    csrf_require();
    $destId = input_int('destination_id');
    $catId  = input_int('category_id') ?: null;
    $name   = mb_substr(input('name'), 0, 150);
    $short  = mb_substr(input('short_description'), 0, 300);
    $full   = mb_substr(input('full_description'), 0, 20000);
    $hours  = mb_substr(input('opening_hours'), 0, 255);
    $entry  = mb_substr(input('entry_info'), 0, 255);
    $ytRaw  = input('youtube_url');
    $ytId   = $ytRaw === '' ? null : youtube_id($ytRaw);
    $webRaw = input('website_url');
    $web    = $webRaw === '' ? null : clean_url($webRaw);
    $mapsRaw = input('maps_url');
    $maps   = $mapsRaw === '' ? null : clean_url($mapsRaw);
    $lat    = clean_coordinate(input('latitude'), -90, 90);
    $lng    = clean_coordinate(input('longitude'), -180, 180);
    $order  = max(0, min(9999, input_int('display_order')));
    $status = input('status') === 'inactive' ? 'inactive' : 'active';
    $featured = isset($_POST['featured']) ? 1 : 0;

    if ($name === '') { $errors['name'] = 'Name is required.'; }
    if (!$destId || !destination_find($destId)) { $errors['destination_id'] = 'Choose a destination.'; }
    if ($catId !== null && !category_find($catId)) { $catId = null; }
    if ($ytRaw !== '' && $ytId === null) { $errors['youtube_url'] = 'Enter a YouTube link (watch?v=, youtu.be/ or /embed/).'; }
    if ($webRaw !== '' && $web === null) { $errors['website_url'] = 'Enter a valid http(s) URL.'; }
    if ($mapsRaw !== '' && $maps === null) { $errors['maps_url'] = 'Enter a valid http(s) URL.'; }
    if (input('latitude') !== '' && $lat === null) { $errors['latitude'] = 'Latitude must be between -90 and 90.'; }
    if (input('longitude') !== '' && $lng === null) { $errors['longitude'] = 'Longitude must be between -180 and 180.'; }

    $main = $row['main_image'] ?? null;
    $newMain = null;
    $newGallery = [];
    if (!$errors && has_upload('main_image')) {
        $up = handle_image_upload(uploaded_files_list('main_image')[0], 'attractions', MAX_IMAGE_BYTES, ['min_width' => 400]);
        if ($up['ok']) { $newMain = $up['path']; } else { $errors['main_image'] = $up['error']; }
    }
    if (!$errors && has_upload('gallery')) {
        foreach (array_slice(uploaded_files_list('gallery'), 0, 12) as $file) {
            $up = handle_image_upload($file, 'gallery', MAX_IMAGE_BYTES, ['min_width' => 400]);
            if ($up['ok']) { $newGallery[] = $up['path']; } else { $errors['gallery'] = $file['name'] . ': ' . $up['error']; break; }
        }
    }

    if (!$errors) {
        if ($newMain) { $main = $newMain; }
        $slug = unique_slug('attractions', $name, $id ?: null);
        $ytUrl = $ytId ? 'https://www.youtube.com/watch?v=' . $ytId : null;
        $params = [$destId, $catId, $name, $slug, $short, $full, $main, $ytUrl, $ytId, $web, $maps, $lat, $lng, $hours, $entry, $order, $featured, $status];
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                db_exec('UPDATE attractions SET destination_id=?, category_id=?, name=?, slug=?, short_description=?, full_description=?, main_image=?, youtube_url=?, youtube_id=?, website_url=?, maps_url=?, latitude=?, longitude=?, opening_hours=?, entry_info=?, display_order=?, featured=?, status=? WHERE id=?', [...$params, $id]);
                $attrId = $id;
            } else {
                db_exec('INSERT INTO attractions (destination_id, category_id, name, slug, short_description, full_description, main_image, youtube_url, youtube_id, website_url, maps_url, latitude, longitude, opening_hours, entry_info, display_order, featured, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $params);
                $attrId = db_insert_id();
            }
            // delete ticked gallery images
            $toDelete = array_map('intval', (array) ($_POST['delete_images'] ?? []));
            $removedFiles = [];
            foreach ($gallery as $g) {
                if (in_array((int) $g['id'], $toDelete, true)) {
                    db_exec('DELETE FROM attraction_images WHERE id = ? AND attraction_id = ?', [$g['id'], $attrId]);
                    $removedFiles[] = $g['image_path'];
                }
            }
            // update captions
            foreach ((array) ($_POST['caption'] ?? []) as $imgId => $cap) {
                if (is_string($cap)) {
                    db_exec('UPDATE attraction_images SET caption = ? WHERE id = ? AND attraction_id = ?', [mb_substr(trim($cap), 0, 200), (int) $imgId, $attrId]);
                }
            }
            $next = (int) db_val('SELECT COALESCE(MAX(sort_order), 0) FROM attraction_images WHERE attraction_id = ?', [$attrId]);
            foreach ($newGallery as $path) {
                db_exec('INSERT INTO attraction_images (attraction_id, image_path, caption, sort_order) VALUES (?,?,?,?)', [$attrId, $path, $name, ++$next]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            foreach ($newGallery as $p) { delete_upload($p); }
            if ($newMain) { delete_upload($newMain); }
            throw $ex;
        }
        foreach ($removedFiles as $f) { delete_upload($f); }
        if ($newMain) { delete_upload($row['main_image'] ?? null); }
        log_activity($id ? 'attraction_updated' : 'attraction_added', 'Admin ' . ($id ? 'updated' : 'added') . ' attraction "' . $name . '"');
        flash('success', $id ? 'Attraction updated.' : 'Attraction added.');
        redirect('admin/attractions/index.php');
    } else {
        if ($newMain) { delete_upload($newMain); }
        foreach ($newGallery as $p) { delete_upload($p); }
    }
}
$adminTitle = $id ? 'Edit attraction' : 'Add attraction';
$adminActive = 'attractions';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<form method="post" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-4"><div class="card-body">
      <div class="mb-3"><label class="form-label required" for="name">Attraction name</label>
        <input class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name" maxlength="150" value="<?= e(fv($row, 'name')) ?>" required><?= field_error($errors, 'name') ?></div>
      <div class="mb-3"><label class="form-label" for="short_description">Short description <small class="text-muted">(shown on cards and in AR)</small></label>
        <input class="form-control" id="short_description" name="short_description" maxlength="300" value="<?= e(fv($row, 'short_description')) ?>"></div>
      <div class="mb-3"><label class="form-label" for="full_description">Full description</label>
        <textarea class="form-control" id="full_description" name="full_description" rows="7"><?= e(fv($row, 'full_description')) ?></textarea></div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="opening_hours">Opening hours</label><input class="form-control" id="opening_hours" name="opening_hours" maxlength="255" value="<?= e(fv($row, 'opening_hours')) ?>" placeholder="Daily 9:00-17:00"></div>
        <div class="col-md-6"><label class="form-label" for="entry_info">Entry information</label><input class="form-control" id="entry_info" name="entry_info" maxlength="255" value="<?= e(fv($row, 'entry_info')) ?>" placeholder="Adults 10, children 5"></div>
        <div class="col-md-6"><label class="form-label" for="youtube_url"><i class="fa-brands fa-youtube text-danger me-1"></i>YouTube URL</label>
          <input class="form-control<?= invalid_class($errors, 'youtube_url') ?>" id="youtube_url" name="youtube_url" maxlength="500" value="<?= e(fv($row, 'youtube_url')) ?>" placeholder="https://www.youtube.com/watch?v=…"><?= field_error($errors, 'youtube_url') ?></div>
        <div class="col-md-6"><label class="form-label" for="website_url"><i class="fa-solid fa-globe me-1"></i>Website URL</label>
          <input class="form-control<?= invalid_class($errors, 'website_url') ?>" id="website_url" name="website_url" maxlength="500" value="<?= e(fv($row, 'website_url')) ?>" placeholder="https://"><?= field_error($errors, 'website_url') ?></div>
        <div class="col-12"><label class="form-label" for="maps_url"><i class="fa-solid fa-map me-1"></i>Google Maps URL</label>
          <input class="form-control<?= invalid_class($errors, 'maps_url') ?>" id="maps_url" name="maps_url" maxlength="500" value="<?= e(fv($row, 'maps_url')) ?>" placeholder="https://maps.google.com/…"><?= field_error($errors, 'maps_url') ?>
          <div class="form-text">Optional. If empty, a link is built from latitude/longitude.</div></div>
        <div class="col-md-6"><label class="form-label" for="latitude">Latitude</label><input class="form-control<?= invalid_class($errors, 'latitude') ?>" id="latitude" name="latitude" inputmode="decimal" value="<?= e(fv($row, 'latitude')) ?>"><?= field_error($errors, 'latitude') ?></div>
        <div class="col-md-6"><label class="form-label" for="longitude">Longitude</label><input class="form-control<?= invalid_class($errors, 'longitude') ?>" id="longitude" name="longitude" inputmode="decimal" value="<?= e(fv($row, 'longitude')) ?>"><?= field_error($errors, 'longitude') ?></div>
      </div>
    </div></div>

    <div class="card"><div class="card-header">Gallery</div><div class="card-body">
      <?php if ($gallery): ?>
        <div class="row g-3 mb-3">
          <?php foreach ($gallery as $g): ?>
            <div class="col-6 col-md-4"><div class="border rounded p-2 h-100">
              <img class="img-fluid rounded mb-2" src="<?= e(image_url($g['image_path'])) ?>" alt="<?= e($g['caption']) ?>" loading="lazy">
              <label class="visually-hidden" for="cap<?= (int) $g['id'] ?>">Caption</label>
              <input class="form-control form-control-sm mb-2" id="cap<?= (int) $g['id'] ?>" name="caption[<?= (int) $g['id'] ?>]" value="<?= e($g['caption']) ?>" maxlength="200" placeholder="Caption">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="delete_images[]" value="<?= (int) $g['id'] ?>" id="del<?= (int) $g['id'] ?>"><label class="form-check-label small text-danger" for="del<?= (int) $g['id'] ?>">Delete</label></div>
            </div></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <label class="form-label" for="gallery">Add gallery images</label>
      <input class="form-control<?= invalid_class($errors, 'gallery') ?>" type="file" id="gallery" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple><?= field_error($errors, 'gallery') ?>
      <div class="form-text">Up to 12 images per save. Images are re-encoded and resized to 1800px wide to keep pages fast.</div>
    </div></div>
  </div>

  <div class="col-lg-4"><div class="card"><div class="card-body">
    <div class="mb-3"><label class="form-label required" for="destination_id">Destination</label>
      <select class="form-select<?= invalid_class($errors, 'destination_id') ?>" id="destination_id" name="destination_id" required><?= select_options(destinations_options(), fv($row, 'destination_id', (string) input_int('destination')), 'id', 'name', '— choose —') ?></select><?= field_error($errors, 'destination_id') ?></div>
    <div class="mb-3"><label class="form-label" for="category_id">Category</label>
      <select class="form-select" id="category_id" name="category_id"><?= select_options(categories_list(), fv($row, 'category_id'), 'id', 'name', '— none —') ?></select></div>
    <div class="mb-3"><label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status"><option value="active" <?= fv($row, 'status', 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= fv($row, 'status') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
    <div class="mb-3"><label class="form-label" for="display_order">Display order</label><input class="form-control" type="number" min="0" max="9999" id="display_order" name="display_order" value="<?= e(fv($row, 'display_order', '0')) ?>"></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="featured" name="featured" <?= (is_post() ? isset($_POST['featured']) : !empty($row['featured'])) ? 'checked' : '' ?>><label class="form-check-label" for="featured">Featured on home page</label></div>
    <div class="mb-3"><label class="form-label" for="main_image">Main image</label>
      <img id="mainPreview" class="img-fluid rounded mb-2" src="<?= e(image_url($row['main_image'] ?? null)) ?>" alt="Current main image" <?= empty($row['main_image']) ? 'hidden' : '' ?>>
      <input class="form-control<?= invalid_class($errors, 'main_image') ?>" type="file" id="main_image" name="main_image" accept="image/jpeg,image/png,image/webp" data-preview="#mainPreview"><?= field_error($errors, 'main_image') ?>
      <div class="form-text">JPG, PNG or WEBP, up to <?= (int) (MAX_IMAGE_BYTES / 1048576) ?> MB.</div></div>
    <button class="btn btn-success w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save attraction</button>
    <a class="btn btn-light w-100 mt-2" href="<?= e(url('admin/attractions/index.php')) ?>">Cancel</a>
  </div></div></div>
</div></form>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
