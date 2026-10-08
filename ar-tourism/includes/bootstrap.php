<?php
/** Loaded first by every page. */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/youtube.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/upload.php';
foreach (['categories', 'destinations', 'attractions', 'posters'] as $repo) {
    require_once __DIR__ . '/repositories/' . $repo . '.php';
}

set_exception_handler(static function (Throwable $e): void {
    error_log('[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $wantsJson = str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/');
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => APP_ENV === 'development' ? $e->getMessage() : 'Server error.']);
    } elseif (APP_ENV === 'development') {
        echo '<pre style="padding:1rem">' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        include APP_ROOT . '/errors/500.php';
    }
    exit;
});

start_secure_session();
send_security_headers();
