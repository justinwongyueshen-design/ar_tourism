<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_super_admin();

$fields = [
    'site_name' => ['Site name', 100], 'tagline' => ['Tagline', 150], 'hero_title' => ['Home hero title', 150],
    'hero_subtitle' => ['Home hero subtitle', 300], 'contact_email' => ['Contact email', 120], 'contact_phone' => ['Contact phone', 50],
    'contact_address' => ['Contact address', 200], 'footer_text' => ['Footer text', 200],
];
$errors = [];
if (is_post()) {
    csrf_require();
    $save = [];
    foreach ($fields as $key => [$label, $max]) { $save[$key] = mb_substr(input($key), 0, $max); }
    $save['about_text'] = mb_substr(input('about_text'), 0, 5000);
    $minCf = input('ar_filter_min_cf'); $beta = input('ar_filter_beta');
    if (!is_numeric($minCf) || (float) $minCf <= 0 || (float) $minCf > 10) { $errors['ar_filter_min_cf'] = 'Enter a number between 0 and 10 (default 0.001).'; }
    if (!is_numeric($beta) || (float) $beta < 0 || (float) $beta > 100000) { $errors['ar_filter_beta'] = 'Enter a number between 0 and 100000 (default 1000).'; }
    if ($save['contact_email'] !== '' && !filter_var($save['contact_email'], FILTER_VALIDATE_EMAIL)) { $errors['contact_email'] = 'Enter a valid email address.'; }
    if ($save['site_name'] === '') { $errors['site_name'] = 'Site name is required.'; }
    if (!$errors) {
        $save['ar_filter_min_cf'] = (string) (float) $minCf;
        $save['ar_filter_beta'] = (string) (float) $beta;
        foreach ($save as $k => $v) {
            db_exec('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$k, $v]);
        }
        log_activity('settings_updated', 'Admin updated system settings');
        flash('success', 'Settings saved.');
        redirect('admin/settings/index.php');
    }
}
$cur = settings_all();
$adminTitle = 'Settings';
$adminActive = 'settings';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<form method="post" novalidate><?= csrf_field() ?>
<div class="row g-4">
  <div class="col-lg-7"><div class="card"><div class="card-header">Website</div><div class="card-body">
    <?php foreach ($fields as $key => [$label, $max]): ?>
      <div class="mb-3"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
        <input class="form-control<?= invalid_class($errors, $key) ?>" id="<?= e($key) ?>" name="<?= e($key) ?>" maxlength="<?= (int) $max ?>" value="<?= e(fv($cur, $key)) ?>"><?= field_error($errors, $key) ?></div>
    <?php endforeach; ?>
    <div class="mb-0"><label class="form-label" for="about_text">About text</label><textarea class="form-control" id="about_text" name="about_text" rows="5"><?= e(fv($cur, 'about_text')) ?></textarea></div>
  </div></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-header">AR tracking smoothness</div><div class="card-body">
    <p class="small text-muted">MindAR smooths the tracked position with a One-Euro filter. Change only if overlays feel jittery or laggy on your posters.</p>
    <div class="mb-3"><label class="form-label" for="ar_filter_min_cf">Filter cut-off (filterMinCF)</label><input class="form-control<?= invalid_class($errors, 'ar_filter_min_cf') ?>" id="ar_filter_min_cf" name="ar_filter_min_cf" value="<?= e(fv($cur, 'ar_filter_min_cf', '0.001')) ?>"><?= field_error($errors, 'ar_filter_min_cf') ?><div class="form-text">Lower = steadier but slower to react. Default 0.001.</div></div>
    <div class="mb-3"><label class="form-label" for="ar_filter_beta">Filter beta (filterBeta)</label><input class="form-control<?= invalid_class($errors, 'ar_filter_beta') ?>" id="ar_filter_beta" name="ar_filter_beta" value="<?= e(fv($cur, 'ar_filter_beta', '1000')) ?>"><?= field_error($errors, 'ar_filter_beta') ?><div class="form-text">Higher = follows fast movement more closely. Default 1000.</div></div>
  </div></div>
  <button class="btn btn-success mt-3" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save settings</button></div>
</div></form>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
