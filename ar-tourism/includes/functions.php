<?php
/** General helpers: escaping, URLs, flash messages, pagination, settings. */
declare(strict_types=1);

// ---------- Output escaping ----------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------- URLs (nothing is hard-coded to localhost) ----------
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function is_local_host(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
}

/** Camera access works on HTTPS or localhost. */
function is_secure_context_server(): bool
{
    return is_https() || is_local_host();
}

/** Web path of the installation, e.g. "/ar-tourism" or "" when installed at the web root. */
function app_base_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $root = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $doc  = str_replace('\\', '/', (string) realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')));
    if ($doc !== '' && strpos($root, $doc) === 0) {
        $path = rtrim(substr($root, strlen($doc)), '/');
    } else {
        // Fallback: derive from the script URL
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $path = preg_replace('#/(admin|api|install)(/.*)?$#', '', dirname($script)) ?? '';
        $path = rtrim($path === '.' ? '' : $path, '/');
    }
    return $path;
}

/** Root-relative URL: url('ar.php') => /ar-tourism/ar.php */
function url(string $path = ''): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

/** Absolute URL for the current host: used for the QR code and sharing. */
function base_url(string $path = ''): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . url($path);
}

/** URL of a file inside /assets with a cache-busting version (file modification time). */
function asset(string $relative): string
{
    $file = APP_ROOT . '/assets/' . ltrim($relative, '/');
    $ver  = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($relative, '/')) . '?v=' . $ver;
}

/** URL for a stored image path such as "uploads/attractions/x.jpg" or "images/demo/a.svg". */
function image_url(?string $path, string $fallback = 'images/placeholder.svg'): string
{
    $path = ltrim((string) $path, '/');
    if ($path === '' || strpos($path, '..') !== false || !is_file(APP_ROOT . '/assets/' . $path)) {
        $path = $fallback;
    }
    return asset($path);
}

/** Local vendor copy if present (see tools/fetch-vendor.php), otherwise the CDN URL. */
function vendor(string $localRelative, string $cdnUrl): string
{
    if (is_file(APP_ROOT . '/assets/vendor/' . $localRelative)) {
        return asset('vendor/' . $localRelative);
    }
    return $cdnUrl;
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ---------- Request helpers ----------
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// ---------- Flash messages ----------
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['_flash'] ?? [] as $f) {
        $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
              . e($f['message'])
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['_flash']);
    return $html;
}

// ---------- Strings ----------
function slugify(string $text): string
{
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '', '-'));
    return $text !== '' ? $text : 'item';
}

/** Unique slug for a table. $table must be one of our own table names (never user input). */
function unique_slug(string $table, string $name, ?int $ignoreId = null): string
{
    $allowed = ['categories', 'destinations', 'attractions'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid table');
    }
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    while (db_val("SELECT id FROM `$table` WHERE slug = ? AND id <> ?", [$slug, $ignoreId ?? 0]) !== null) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function short_text(?string $text, int $length = 120): string
{
    $text = trim(strip_tags((string) $text));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

/** Escapes text and turns blank-line separated paragraphs into <p> tags. */
function paragraphs(?string $text): string
{
    $parts = preg_split('/\R{2,}/', trim((string) $text)) ?: [];
    $out = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $out .= '<p>' . nl2br(e($p)) . '</p>';
        }
    }
    return $out;
}

// ---------- URL validation ----------
function valid_http_url(?string $url): bool
{
    if ($url === null || $url === '' || strlen($url) > 500) {
        return false;
    }
    $parts = parse_url($url);
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && isset($parts['scheme'])
        && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
}

/** Returns the URL if it is a valid http(s) URL, otherwise null. */
function clean_url(?string $url): ?string
{
    $url = trim((string) $url);
    return valid_http_url($url) ? $url : null;
}

function clean_coordinate(string $value, float $min, float $max): ?string
{
    $value = trim($value);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    $n = (float) $value;
    return ($n < $min || $n > $max) ? null : number_format($n, 7, '.', '');
}

/** Google Maps link for a row that has maps_url and/or latitude/longitude. */
function map_link(array $row): ?string
{
    if (!empty($row['maps_url']) && valid_http_url($row['maps_url'])) {
        return $row['maps_url'];
    }
    if (isset($row['latitude'], $row['longitude']) && $row['latitude'] !== null && $row['longitude'] !== null) {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($row['latitude'] . ',' . $row['longitude']);
    }
    return null;
}

// ---------- Settings ----------
function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT setting_key, setting_value FROM settings') as $r) {
                $cache[$r['setting_key']] = (string) $r['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

// ---------- Pagination ----------
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'pages' => $pages, 'page' => $page, 'offset' => ($page - 1) * $perPage];
}

function pagination_html(array $pg, array $query = []): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $link = static function (int $p) use ($query): string {
        $query['page'] = $p;
        return '?' . http_build_query($query);
    };
    $html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center flex-wrap">';
    $html .= '<li class="page-item' . ($pg['page'] <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($pg['page'] - 1)) . '">&laquo;</a></li>';
    $start = max(1, $pg['page'] - 2);
    $end   = min($pg['pages'], $pg['page'] + 2);
    for ($p = $start; $p <= $end; $p++) {
        $html .= '<li class="page-item' . ($p === $pg['page'] ? ' active' : '') . '"><a class="page-link" href="' . e($link($p)) . '">' . $p . '</a></li>';
    }
    $html .= '<li class="page-item' . ($pg['page'] >= $pg['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($pg['page'] + 1)) . '">&raquo;</a></li>';
    return $html . '</ul></nav>';
}

function status_badge(string $status): string
{
    $map = ['active' => 'success', 'published' => 'success', 'READY' => 'success',
            'inactive' => 'secondary', 'draft' => 'warning', 'NOT_COMPILED' => 'warning'];
    $label = str_replace('_', ' ', $status);
    return '<span class="badge text-bg-' . ($map[$status] ?? 'secondary') . '">' . e($label) . '</span>';
}

/** LIKE-pattern with wildcards escaped. */
function like(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}
