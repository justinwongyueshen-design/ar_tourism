<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

$poster = poster_find(input_int('id'));
if (!$poster) { flash('danger', 'Poster not found.'); redirect('admin/ar-posters/index.php'); }

$hotspots = array_map(static fn($h) => [
    'id' => (int) $h['id'], 'label' => $h['label'], 'attraction_id' => $h['attraction_id'] !== null ? (int) $h['attraction_id'] : null,
    'x' => (float) $h['x'], 'y' => (float) $h['y'], 'w' => (float) $h['width'], 'h' => (float) $h['height'],
    'z' => (int) $h['z_index'], 'content_type' => $h['content_type'], 'video_url' => (string) $h['video_url'], 'custom_content' => (string) $h['custom_content'],
], poster_hotspots((int) $poster['id']));
$attractions = array_map(static fn($a) => ['id' => (int) $a['id'], 'name' => $a['name'], 'label' => $a['destination_name'] . ' — ' . $a['name']], attractions_options());

$editorData = [
    'posterId'  => (int) $poster['id'],
    'saveUrl'   => url('api/ar/save_hotspots.php'),
    'hotspots'  => $hotspots,
    'attractions' => $attractions,
];
$adminTitle = 'AR Poster Editor';
$adminActive = 'hotspots';
$adminScripts = '<script src="' . e(asset('js/admin-editor.js')) . '"></script>';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <div><strong><?= e($poster['poster_name']) ?></strong> <?= status_badge($poster['target_status']) ?></div>
  <div class="ms-auto d-flex flex-wrap gap-2">
    <button class="btn btn-outline-success" type="button" id="btnAdd"><i class="fa-solid fa-plus me-1"></i>Add hotspot</button>
    <button class="btn btn-success" type="button" id="btnSave"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('ar.php?poster=' . (int) $poster['id'])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-vr-cardboard me-1"></i>Preview</a>
    <a class="btn btn-primary" href="<?= e(url('admin/ar-posters/compile.php?id=' . (int) $poster['id'])) ?>"><i class="fa-solid fa-microchip me-1"></i>Compile</a>
  </div>
</div>
<div id="editorMsg" class="alert d-none" role="status"></div>

<div class="editor-wrap">
  <div>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="clickAdd" checked><label class="form-check-label" for="clickAdd">Click on the poster to add a hotspot</label></div>
    <div class="editor-stage" id="stage"><img src="<?= e(image_url($poster['poster_image'])) ?>" alt="Poster: <?= e($poster['poster_name']) ?>" draggable="false"></div>
    <p class="small text-muted mt-2">Drag a hotspot to move it, drag the white corner squares to resize. Arrow keys nudge the selected hotspot (hold Shift for bigger steps), Delete removes it. Positions are saved as percentages of the poster, so they fit any screen.</p>
  </div>

  <div>
    <div class="card mb-3"><div class="card-header">Hotspot properties</div><div class="card-body" id="props">
      <p class="text-muted mb-0" id="noSel">Select or add a hotspot.</p>
      <div id="propsForm" hidden>
        <div class="mb-2"><label class="form-label" for="pLabel">Label</label><input class="form-control" id="pLabel" maxlength="120"></div>
        <div class="mb-2"><label class="form-label" for="pAttr">Attraction</label><select class="form-select" id="pAttr"></select></div>
        <div class="mb-2"><label class="form-label" for="pType">AR content</label>
          <select class="form-select" id="pType"><option value="card">Information card</option><option value="video">Card with video (opens playing)</option><option value="both">Card + "Watch Video" button</option></select></div>
        <div class="mb-2"><label class="form-label" for="pVideo">Video URL (overrides the attraction's)</label><input class="form-control" id="pVideo" maxlength="500" placeholder="https://www.youtube.com/watch?v=…"></div>
        <div class="mb-2"><label class="form-label" for="pContent">Extra text</label><textarea class="form-control" id="pContent" rows="3" maxlength="2000"></textarea><div class="form-text">Shown on the card. Used as the main text when no attraction is selected.</div></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label" for="pZ">Layer (z-index)</label><input class="form-control" type="number" id="pZ" min="0" max="99"></div>
          <div class="col-6"><label class="form-label">Position</label><div class="form-control-plaintext small mono" id="pPos"></div></div>
        </div>
        <button class="btn btn-outline-danger btn-sm" type="button" id="btnDelete"><i class="fa-solid fa-trash me-1"></i>Delete hotspot</button>
      </div>
    </div></div>
    <div class="card"><div class="card-header">Hotspots (<span id="hsCount">0</span>)</div><div class="list-group list-group-flush hs-list" id="hsList"></div></div>
  </div>
</div>
<script type="application/json" id="editorData"><?= json_encode($editorData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
