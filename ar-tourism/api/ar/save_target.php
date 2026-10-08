<?php
/**
 * POST api/ar/save_target.php?poster_id=N   body = raw .mind bytes (compiled in the admin's browser)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!is_post()) { json_response(['ok' => false, 'error' => 'POST required.'], 405); }
require_login_json();
csrf_require_json();

$poster = poster_find(input_int('poster_id'));
if (!$poster) { json_response(['ok' => false, 'error' => 'Poster not found.'], 404); }

$data = (string) file_get_contents('php://input', false, null, 0, MAX_TARGET_BYTES + 1);
$size = strlen($data);
if ($size < 200) { json_response(['ok' => false, 'error' => 'The compiled file is empty or too small.'], 400); }
if ($size > MAX_TARGET_BYTES) { json_response(['ok' => false, 'error' => 'The compiled file is too large. Use a smaller poster image.'], 413); }
// A .mind file is MessagePack: a map (fixmap 0x80-0x8f / map16 0xde / map32 0xdf). Reject anything that looks like text/script.
$first = ord($data[0]);
if (!(($first >= 0x80 && $first <= 0x8f) || $first === 0xde || $first === 0xdf)) {
    json_response(['ok' => false, 'error' => 'That does not look like a MindAR target file.'], 400);
}

$dir = APP_ROOT . '/assets/ar-targets';
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    json_response(['ok' => false, 'error' => 'Target folder is not writable.'], 500);
}
$name = sprintf('poster-%d-%s.mind', $poster['id'], bin2hex(random_bytes(8)));
if (file_put_contents($dir . '/' . $name, $data, LOCK_EX) === false) {
    json_response(['ok' => false, 'error' => 'Could not write the target file (check folder permissions).'], 500);
}
@chmod($dir . '/' . $name, 0644);

$user = current_user();
$pdo = db();
$pdo->beginTransaction();
try {
    db_exec('INSERT INTO ar_targets (poster_id, file_path, file_size, checksum, mindar_version, compiled_by) VALUES (?,?,?,?,?,?)',
        [$poster['id'], $name, $size, hash('sha256', $data), mb_substr(input('version', MINDAR_VERSION), 0, 20), $user['id'] ?? null]);
    db_exec("UPDATE ar_posters SET target_file = ?, target_status = 'READY', target_compiled_at = NOW() WHERE id = ?", [$name, $poster['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($dir . '/' . $name);
    throw $e;
}
// keep the 3 most recent targets, delete older files
foreach (db_all('SELECT id, file_path FROM ar_targets WHERE poster_id = ? ORDER BY id DESC LIMIT 100 OFFSET 3', [$poster['id']]) as $old) {
    @unlink($dir . '/' . basename((string) $old['file_path']));
    db_exec('DELETE FROM ar_targets WHERE id = ?', [$old['id']]);
}
log_activity('target_compiled', 'Admin compiled AR target for poster "' . $poster['poster_name'] . '" (' . round($size / 1024) . ' KB)');
json_response(['ok' => true, 'file' => $name, 'size' => $size]);
