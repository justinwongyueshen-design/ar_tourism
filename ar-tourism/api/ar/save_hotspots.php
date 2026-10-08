<?php
/** POST JSON {poster_id, hotspots:[...]}  - replaces the hotspot layout of a poster. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!is_post()) { json_response(['ok' => false, 'error' => 'POST required.'], 405); }
require_login_json();
csrf_require_json();

$body = json_decode((string) file_get_contents('php://input', false, null, 0, 1048576), true);
if (!is_array($body) || !isset($body['poster_id'], $body['hotspots']) || !is_array($body['hotspots'])) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 400);
}
$poster = poster_find((int) $body['poster_id']);
if (!$poster) { json_response(['ok' => false, 'error' => 'Poster not found.'], 404); }
if (count($body['hotspots']) > 100) { json_response(['ok' => false, 'error' => 'Too many hotspots (max 100).'], 400); }

$validAttractions = array_flip(array_column(db_all('SELECT id FROM attractions'), 'id'));
$clean = [];
foreach ($body['hotspots'] as $i => $h) {
    if (!is_array($h)) { json_response(['ok' => false, 'error' => 'Invalid hotspot #' . ($i + 1)], 400); }
    foreach (['x', 'y', 'w', 'h'] as $k) {
        if (!isset($h[$k]) || !is_numeric($h[$k])) { json_response(['ok' => false, 'error' => "Hotspot #" . ($i + 1) . ": $k is missing."], 400); }
    }
    $x = (float) $h['x']; $y = (float) $h['y']; $w = (float) $h['w']; $hh = (float) $h['h'];
    if ($x < 0 || $y < 0 || $w < 0.02 || $hh < 0.02 || $x + $w > 1.0001 || $y + $hh > 1.0001) {
        json_response(['ok' => false, 'error' => 'Hotspot #' . ($i + 1) . ' is outside the poster or too small.'], 400);
    }
    $attrId = isset($h['attraction_id']) && $h['attraction_id'] !== null && $h['attraction_id'] !== '' ? (int) $h['attraction_id'] : null;
    if ($attrId !== null && !isset($validAttractions[$attrId])) { $attrId = null; }
    $type = in_array($h['content_type'] ?? 'card', ['card', 'video', 'both'], true) ? $h['content_type'] : 'card';
    $videoRaw = trim((string) ($h['video_url'] ?? ''));
    if ($videoRaw !== '' && youtube_id($videoRaw) === null) {
        json_response(['ok' => false, 'error' => 'Hotspot #' . ($i + 1) . ': the video URL is not a valid YouTube link.'], 400);
    }
    $clean[] = [
        'attraction_id' => $attrId,
        'label'   => mb_substr(trim((string) ($h['label'] ?? '')), 0, 120),
        'x' => round($x, 6), 'y' => round($y, 6), 'w' => round($w, 6), 'h' => round($hh, 6),
        'z' => max(0, min(99, (int) ($h['z'] ?? $i))),
        'type' => $type,
        'video' => $videoRaw === '' ? null : $videoRaw,
        'custom' => mb_substr(trim((string) ($h['custom_content'] ?? '')), 0, 2000),
    ];
}

$pdo = db();
$pdo->beginTransaction();
try {
    db_exec('DELETE FROM ar_hotspots WHERE poster_id = ?', [$poster['id']]);
    $ids = [];
    foreach ($clean as $c) {
        db_exec('INSERT INTO ar_hotspots (poster_id, attraction_id, label, x, y, width, height, z_index, content_type, video_url, custom_content) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$poster['id'], $c['attraction_id'], $c['label'], $c['x'], $c['y'], $c['w'], $c['h'], $c['z'], $c['type'], $c['video'], $c['custom'] ?: null]);
        $ids[] = db_insert_id();
    }
    db_exec('UPDATE ar_posters SET updated_at = NOW() WHERE id = ?', [$poster['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
log_activity('hotspots_saved', 'Admin saved ' . count($clean) . ' hotspot(s) on poster "' . $poster['poster_name'] . '"');
json_response(['ok' => true, 'ids' => $ids, 'count' => count($ids)]);
