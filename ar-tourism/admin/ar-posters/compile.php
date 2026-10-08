<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

$poster = poster_find(input_int('id'));
if (!$poster) { flash('danger', 'Poster not found.'); redirect('admin/ar-posters/index.php'); }
$history = db_all('SELECT t.*, u.username FROM ar_targets t LEFT JOIN users u ON u.id = t.compiled_by WHERE t.poster_id = ? ORDER BY t.id DESC LIMIT 5', [(int) $poster['id']]);

$mindarCompiler = vendor('mindar/mindar-image.prod.js', 'https://cdn.jsdelivr.net/npm/mind-ar@' . MINDAR_VERSION . '/dist/mindar-image.prod.js');
$adminTitle = 'Compile AR target';
$adminActive = 'compile';
$adminScripts = '<script src="' . e($mindarCompiler) . '"></script><script src="' . e(asset('js/mind-compiler.js')) . '"></script>';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<div class="row g-4">
  <div class="col-lg-7"><div class="card"><div class="card-body" id="compileApp"
       data-image="<?= e(image_url($poster['poster_image'])) ?>"
       data-poster-id="<?= (int) $poster['id'] ?>"
       data-upload-url="<?= e(url('api/ar/save_target.php')) ?>"
       data-preview-url="<?= e(url('ar.php?poster=' . (int) $poster['id'])) ?>"
       data-version="<?= e(MINDAR_VERSION) ?>">
    <h2 class="h5"><?= e($poster['poster_name']) ?></h2>
    <p class="mb-2">Target status: <span id="targetStatus"><?= status_badge($poster['target_status']) ?></span>
      <?php if ($poster['target_compiled_at']): ?><small class="text-muted ms-2">compiled <?= e(date('d M Y H:i', strtotime((string) $poster['target_compiled_at']))) ?></small><?php endif; ?></p>
    <p class="text-muted small">Compiling analyses the poster image and builds the <code>.mind</code> tracking file. It runs <strong>in this browser</strong> (nothing special is needed on the server) and usually takes 10–60 seconds. Keep this tab open until it finishes.</p>
    <div class="mb-3" id="progressBox" hidden>
      <div class="d-flex justify-content-between small mb-1"><span id="progressLabel">Preparing AR Target</span><span id="progressPct">0%</span></div>
      <div class="progress" style="height:22px" role="progressbar" aria-label="Compilation progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progressWrap"><div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="progressBar" style="width:0%"></div></div>
      <p class="small text-muted mt-1 mb-0">Please wait…</p>
    </div>
    <div id="compileMsg" class="alert d-none" role="status"></div>
    <button class="btn btn-success btn-lg" type="button" id="btnCompile"><i class="fa-solid fa-microchip me-2"></i><?= $poster['target_status'] === 'READY' ? 'Recompile target' : 'Compile target' ?></button>
    <a class="btn btn-outline-secondary btn-lg" id="btnPreview" href="<?= e(url('ar.php?poster=' . (int) $poster['id'])) ?>" target="_blank" rel="noopener" <?= $poster['target_status'] === 'READY' ? '' : 'hidden' ?>><i class="fa-solid fa-vr-cardboard me-2"></i>Preview AR</a>
    <a class="btn btn-light btn-lg" href="<?= e(url('admin/ar-posters/index.php')) ?>">Back</a>
  </div></div></div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-body"><img class="img-fluid rounded" src="<?= e(image_url($poster['poster_image'])) ?>" alt="Poster to compile"><p class="small text-muted mt-2 mb-0"><?= (int) $poster['image_width'] ?>×<?= (int) $poster['image_height'] ?>px</p></div></div>
    <?php if ($history): ?><div class="card"><div class="card-header">Compile history</div><ul class="list-group list-group-flush small">
      <?php foreach ($history as $h): ?><li class="list-group-item"><?= e(date('d M Y H:i', strtotime((string) $h['compiled_at']))) ?> · <?= e(number_format($h['file_size'] / 1024, 0)) ?> KB · <?= e((string) $h['username']) ?></li><?php endforeach; ?>
    </ul></div><?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
