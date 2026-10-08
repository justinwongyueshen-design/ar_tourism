<?php
/**
 * Visitor AR experience.
 *   ar.php            -> picks the only published poster, or lets the visitor choose
 *   ar.php?poster=ID  -> that poster (logged-in admins may also preview drafts / uncompiled posters)
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$posterId = input_int('poster');
$poster   = null;
$choices  = [];
$problem  = null;   // message shown instead of the AR start button

if ($posterId > 0) {
    $poster = poster_find($posterId);
    if (!$poster || ($poster['status'] !== 'published' && !is_logged_in())) {
        $poster = null;
        $problem = 'This AR poster is not available.';
    }
} else {
    $choices = array_values(array_filter(posters_list(), static fn($p) => $p['status'] === 'published' && poster_is_scannable($p)));
    if (count($choices) === 1) {
        $poster = $choices[0];
        $choices = [];
    } elseif (!$choices) {
        $problem = 'No AR posters are available yet. Please ask the administrator to compile and publish a poster.';
    }
}
if ($poster && poster_target_url($poster) === null) {
    $problem = 'AR tracking is not ready. Please ask the administrator to compile this poster.';
}

$siteName = setting('site_name', APP_NAME);
$secureOk = is_secure_context_server();

// Import-map URLs (local vendor copy if downloaded, otherwise CDN)
$three = THREE_VERSION;
$mind  = MINDAR_VERSION;
$localOr = static fn(string $rel, string $cdn): string => is_file(APP_ROOT . '/assets/vendor/' . $rel) ? url('assets/vendor/' . $rel) : $cdn;
$importMap = [
    'imports' => [
        'three'               => $localOr('three/three.module.js', "https://cdn.jsdelivr.net/npm/three@$three/build/three.module.js"),
        'three/addons/'       => is_file(APP_ROOT . '/assets/vendor/three/addons/renderers/CSS3DRenderer.js')
                                   ? url('assets/vendor/three/addons/') : "https://cdn.jsdelivr.net/npm/three@$three/examples/jsm/",
        'mindar-image-three'  => $localOr('mindar/mindar-image-three.prod.js', "https://cdn.jsdelivr.net/npm/mind-ar@$mind/dist/mindar-image-three.prod.js"),
    ],
];
$config = [
    'posterId'    => $poster ? (int) $poster['id'] : null,
    'apiUrl'      => url('api/ar/poster.php'),
    'backUrl'     => url('index.php'),
    'filterMinCF' => (float) setting('ar_filter_min_cf', '0.001'),
    'filterBeta'  => (float) setting('ar_filter_beta', '1000'),
    'secureServer'=> $secureOk,
    'msg' => [
        'https'        => 'Camera access requires HTTPS. Please access this website using an HTTPS URL.',
        'cameraDenied' => 'Camera access was denied. Please allow camera permission in your browser settings and try again.',
        'noCamera'     => 'No camera was found on this device.',
        'cameraBusy'   => 'The camera could not be started. Close other apps that use the camera and try again.',
        'notDetected'  => 'Poster not detected. Move closer and make sure the entire poster is visible.',
        'targetMissing'=> 'AR tracking is not ready. Please ask the administrator to compile this poster.',
        'noHotspots'   => 'No AR attractions have been configured for this poster.',
        'videoError'   => 'Video unavailable. Please try again later.',
        'engine'       => 'The AR engine could not be loaded. Check your internet connection and try again.',
        'unsupported'  => 'This browser does not support camera access. Try Chrome (Android) or Safari (iPhone).',
    ],
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>AR Experience · <?= e($siteName) ?></title>
  <meta name="theme-color" content="#0a1411">
  <link rel="icon" href="<?= e(asset('images/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(vendor('fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/' . FONTAWESOME_VERSION . '/css/all.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/ar.css')) ?>">
  <script type="importmap"><?= json_encode($importMap, JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="ar-body">
<div id="ar-root">
  <div id="ar-container" aria-label="Camera view"></div>

  <header class="ar-top" id="arTop" hidden>
    <a class="ar-btn ar-back" id="arBack" href="<?= e(url('index.php')) ?>" aria-label="Back to website"><i class="fa-solid fa-arrow-left"></i><span>Back</span></a>
    <div class="ar-brand"><i class="fa-solid fa-vr-cardboard"></i> AR TOURISM</div>
    <div class="ar-status" id="arStatus" data-state="idle" role="status" aria-live="polite"><span class="dot"></span><span class="txt">STARTING</span></div>
  </header>

  <div class="ar-hint" id="arHint" role="status" aria-live="polite" hidden></div>
  <button class="ar-focus" id="focusBar" type="button" hidden><span class="ar-focus-name"></span><span class="ar-focus-sub">Tap for details</span></button>

  <section class="ar-card" id="arCard" role="dialog" aria-modal="false" aria-labelledby="cardTitle" hidden>
    <button class="ar-card-close" id="cardClose" type="button" aria-label="Close information card"><i class="fa-solid fa-xmark"></i></button>
    <img class="ar-card-img" id="cardImg" alt="" hidden>
    <div class="ar-card-body">
      <span class="ar-card-cat" id="cardCat" hidden></span>
      <h2 id="cardTitle"></h2>
      <p id="cardDesc"></p>
      <ul class="ar-meta" id="cardMeta"></ul>
      <div class="ar-video" id="cardVideo" hidden>
        <div class="ar-video-frame"><div id="ytMount"></div></div>
        <div class="ar-video-ctrl">
          <button type="button" class="ar-chip" id="ytPlay"><i class="fa-solid fa-play"></i> <span>Play</span></button>
          <button type="button" class="ar-chip" id="ytMute"><i class="fa-solid fa-volume-xmark"></i> <span>Unmute</span></button>
          <a class="ar-chip" id="ytOpen" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-youtube"></i> YouTube</a>
        </div>
        <p class="ar-video-error" id="ytError" hidden></p>
      </div>
      <div class="ar-actions">
        <button type="button" class="ar-act" id="btnWatch" hidden><i class="fa-solid fa-play"></i> Watch Video</button>
        <a class="ar-act" id="btnMap" target="_blank" rel="noopener noreferrer" hidden><i class="fa-solid fa-location-dot"></i> View Location</a>
        <a class="ar-act" id="btnWeb" target="_blank" rel="noopener noreferrer" hidden><i class="fa-solid fa-globe"></i> Website</a>
        <a class="ar-act" id="btnMore" hidden><i class="fa-solid fa-circle-info"></i> More Information</a>
        <button type="button" class="ar-act" id="btnDist" hidden><i class="fa-solid fa-route"></i> <span>Show distance</span></button>
      </div>
    </div>
  </section>

  <nav class="ar-bottom" id="arBottom" aria-label="AR controls" hidden>
    <button type="button" class="ar-btn round" id="tbMute" aria-label="Unmute video" disabled><i class="fa-solid fa-volume-xmark"></i></button>
    <button type="button" class="ar-btn round" id="tbInfo" aria-label="Show information" disabled><i class="fa-solid fa-circle-info"></i></button>
    <button type="button" class="ar-btn round" id="tbYoutube" aria-label="Open on YouTube" disabled><i class="fa-brands fa-youtube"></i></button>
    <button type="button" class="ar-btn round" id="tbMap" aria-label="Open map" disabled><i class="fa-solid fa-map-location-dot"></i></button>
    <button type="button" class="ar-btn round danger" id="tbClose" aria-label="Close AR content" disabled><i class="fa-solid fa-xmark"></i></button>
  </nav>

  <!-- Welcome / start screen -->
  <div class="ar-screen" id="arStart">
    <div class="ar-panel">
      <img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="64" height="64">
      <h1>Welcome to AR Tourism</h1>
      <?php if ($choices): ?>
        <p>Choose a poster, then allow camera access to begin.</p>
        <ul class="ar-choices">
          <?php foreach ($choices as $c): ?>
            <li><a href="<?= e(url('ar.php?poster=' . (int) $c['id'])) ?>"><img src="<?= e(image_url($c['poster_image'])) ?>" alt="" width="56" height="56" loading="lazy"><span><?= e($c['poster_name']) ?></span><i class="fa-solid fa-chevron-right"></i></a></li>
          <?php endforeach; ?>
        </ul>
      <?php elseif ($problem): ?>
        <p class="ar-problem" role="alert"><?= e($problem) ?></p>
        <a class="ar-act primary" href="<?= e(url('index.php')) ?>">Back to website</a>
      <?php else: ?>
        <p>Allow camera access to begin the AR experience.</p>
        <?php if (!$secureOk): ?>
          <p class="ar-problem" role="alert"><strong>Camera access requires HTTPS.</strong><br>Please access this website using an HTTPS URL.</p>
        <?php endif; ?>
        <button type="button" class="ar-act primary big" id="btnStart"><i class="fa-solid fa-camera"></i> START AR</button>
        <p class="ar-tip">Tip: have the whole poster in view and keep the phone steady.</p>
        <a class="ar-link" href="<?= e(url('index.php')) ?>">Back to website</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="ar-screen" id="arLoading" hidden>
    <div class="ar-panel"><div class="ar-spinner" aria-hidden="true"></div><p id="arLoadingText" role="status">Loading…</p></div>
  </div>

  <div class="ar-screen" id="arError" hidden>
    <div class="ar-panel">
      <i class="fa-solid fa-triangle-exclamation ar-error-icon" aria-hidden="true"></i>
      <p id="arErrorText" role="alert"></p>
      <button type="button" class="ar-act primary" id="btnRetry"><i class="fa-solid fa-rotate-right"></i> Try again</button>
      <a class="ar-link" href="<?= e(url('index.php')) ?>">Back to website</a>
    </div>
  </div>
</div>
<noscript><p style="position:fixed;inset:0;display:grid;place-items:center;background:#0a1411;color:#fff;padding:2rem;text-align:center">JavaScript is required for the AR experience.</p></noscript>

<script>window.AR_CONFIG = <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php if ($poster && !$problem): ?>
<script type="module" src="<?= e(url('ar/js/ar-app.js')) ?>?v=<?= (int) @filemtime(APP_ROOT . '/ar/js/ar-app.js') ?>"></script>
<?php endif; ?>
</body>
</html>
