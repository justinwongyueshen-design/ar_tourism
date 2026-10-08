<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

$id  = input_int('id');
$row = $id ? category_find($id) : null;
if ($id && !$row) { flash('danger', 'Category not found.'); redirect('admin/categories/index.php'); }
$row = $row ?? [];
$errors = [];

if (is_post()) {
    csrf_require();
    $name   = mb_substr(input('name'), 0, 80);
    $icon   = input('icon', 'fa-landmark');
    $status = input('status') === 'inactive' ? 'inactive' : 'active';
    if ($name === '') { $errors['name'] = 'Name is required.'; }
    if (!preg_match('/^fa-[a-z0-9-]{2,38}$/', $icon)) { $errors['icon'] = 'Use a Font Awesome class such as fa-landmark.'; }
    if (!$errors) {
        $slug = unique_slug('categories', $name, $id ?: null);
        if ($id) {
            db_exec('UPDATE categories SET name = ?, slug = ?, icon = ?, status = ? WHERE id = ?', [$name, $slug, $icon, $status, $id]);
            log_activity('category_updated', 'Admin updated category "' . $name . '"');
            flash('success', 'Category updated.');
        } else {
            db_exec('INSERT INTO categories (name, slug, icon, status) VALUES (?, ?, ?, ?)', [$name, $slug, $icon, $status]);
            log_activity('category_added', 'Admin added category "' . $name . '"');
            flash('success', 'Category added.');
        }
        redirect('admin/categories/index.php');
    }
}
$adminTitle = $id ? 'Edit category' : 'Add category';
$adminActive = 'categories';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<div class="card" style="max-width:640px"><div class="card-body">
<form method="post" novalidate><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label required" for="name">Name</label>
    <input class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name" maxlength="80" value="<?= e(fv($row, 'name')) ?>" required><?= field_error($errors, 'name') ?></div>
  <div class="mb-3"><label class="form-label" for="icon">Icon (Font Awesome class)</label>
    <input class="form-control<?= invalid_class($errors, 'icon') ?>" id="icon" name="icon" maxlength="40" value="<?= e(fv($row, 'icon', 'fa-landmark')) ?>">
    <div class="form-text">Examples: fa-landmark, fa-mountain-sun, fa-utensils, fa-masks-theater, fa-person-hiking</div><?= field_error($errors, 'icon') ?></div>
  <div class="mb-4"><label class="form-label" for="status">Status</label>
    <select class="form-select" id="status" name="status"><option value="active" <?= fv($row, 'status', 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= fv($row, 'status') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
  <button class="btn btn-success" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
  <a class="btn btn-light" href="<?= e(url('admin/categories/index.php')) ?>">Cancel</a>
</form></div></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
