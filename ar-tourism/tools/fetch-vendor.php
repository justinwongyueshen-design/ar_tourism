<?php
/**
 * Optional: downloads front-end libraries into assets/vendor/ so the site works without any CDN.
 * Run once from a command line on a machine with internet access:
 *      php tools/fetch-vendor.php
 * Then keep assets/vendor/ with the project. Pages automatically prefer local copies.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("Run from the command line: php tools/fetch-vendor.php\n"); }
require __DIR__ . '/../includes/config.php';

$T = THREE_VERSION; $M = MINDAR_VERSION; $B = BOOTSTRAP_VERSION; $F = FONTAWESOME_VERSION;
$files = [
  "bootstrap/css/bootstrap.min.css"           => "https://cdn.jsdelivr.net/npm/bootstrap@$B/dist/css/bootstrap.min.css",
  "bootstrap/js/bootstrap.bundle.min.js"      => "https://cdn.jsdelivr.net/npm/bootstrap@$B/dist/js/bootstrap.bundle.min.js",
  "fontawesome/css/all.min.css"               => "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/$F/css/all.min.css",
  "fontawesome/webfonts/fa-solid-900.woff2"   => "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/$F/webfonts/fa-solid-900.woff2",
  "fontawesome/webfonts/fa-regular-400.woff2" => "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/$F/webfonts/fa-regular-400.woff2",
  "fontawesome/webfonts/fa-brands-400.woff2"  => "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/$F/webfonts/fa-brands-400.woff2",
  "qrcode/qrcode.min.js"                      => "https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js",
  "three/three.module.js"                     => "https://cdn.jsdelivr.net/npm/three@$T/build/three.module.js",
  "three/addons/renderers/CSS3DRenderer.js"   => "https://cdn.jsdelivr.net/npm/three@$T/examples/jsm/renderers/CSS3DRenderer.js",
  "mindar/mindar-image-three.prod.js"         => "https://cdn.jsdelivr.net/npm/mind-ar@$M/dist/mindar-image-three.prod.js",
  "mindar/mindar-image.prod.js"               => "https://cdn.jsdelivr.net/npm/mind-ar@$M/dist/mindar-image.prod.js",
];
$ok = 0; $fail = 0;
foreach ($files as $rel => $src) {
    $dest = __DIR__ . '/../assets/vendor/' . $rel;
    if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0755, true); }
    echo str_pad($rel, 48);
    $data = @file_get_contents($src, false, stream_context_create(['http' => ['timeout' => 60, 'user_agent' => 'ar-tourism-fetch']]));
    if ($data === false || strlen($data) < 500) { echo "FAILED ($src)\n"; $fail++; continue; }
    file_put_contents($dest, $data);
    echo 'ok (' . round(strlen($data) / 1024) . " KB)\n";
    $ok++;
}
echo "\nDone: $ok downloaded, $fail failed.\n";
exit($fail ? 1 : 0);
