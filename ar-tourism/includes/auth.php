<?php
/** Session handling, login throttling and access control. */
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) REMEMBER_SECONDS);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => app_base_path() === '' ? '/' : app_base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout (skipped for "remember me" sessions)
    if (!empty($_SESSION['user_id']) && empty($_SESSION['remember'])) {
        $last = (int) ($_SESSION['last_active'] ?? time());
        if (time() - $last > SESSION_IDLE_SECONDS) {
            destroy_session();
            session_start();
            flash('warning', 'You were signed out because of inactivity.');
        }
    }
    // Light session-hijack protection: bind to the browser's user agent
    if (!empty($_SESSION['user_id'])) {
        $fp = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (($_SESSION['fp'] ?? $fp) !== $fp) {
            destroy_session();
            session_start();
        }
        $_SESSION['last_active'] = time();
    }
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $row = db_one("SELECT id, username, email, full_name, role, status FROM users WHERE id = ? AND status = 'active'", [(int) $_SESSION['user_id']]);
        $user = $row ?: null;
        if ($user === null) {
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_super_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '');
        redirect('admin/login.php' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
    }
}

/** Pages only the "admin" role may open (users, settings, logs). */
function require_super_admin(): void
{
    require_login();
    if (!is_super_admin()) {
        http_response_code(403);
        flash('danger', 'You do not have permission to open that page.');
        redirect('admin/index.php');
    }
}

function require_login_json(): void
{
    if (!is_logged_in()) {
        json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
    }
}

/** Only allow redirects back into this installation. */
function safe_next(string $next): string
{
    $base = app_base_path() . '/';
    if ($next !== '' && $next[0] === '/' && strpos($next, '//') !== 0 && strpos($next, '\\') === false && strpos($next, $base) === 0) {
        return $next;
    }
    return url('admin/index.php');
}

/** @return array{ok:bool,message:string} */
function attempt_login(string $identifier, string $password, bool $remember): array
{
    $ip      = client_ip();
    $minutes = (int) LOGIN_LOCK_MINUTES;
    db_exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');

    $ipFails = (int) db_val("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL $minutes MINUTE)", [$ip]);
    if ($ipFails >= LOGIN_MAX_ATTEMPTS_IP) {
        return ['ok' => false, 'message' => "Too many failed attempts. Please try again in $minutes minutes."];
    }

    $user = db_one('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$identifier, $identifier]);

    if ($user && $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
        return ['ok' => false, 'message' => "This account is temporarily locked. Please try again in $minutes minutes."];
    }

    // Always run a hash comparison so response time does not reveal whether the user exists.
    $hash  = $user['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
    $valid = password_verify($password, $hash) && $user !== null && $user['status'] === 'active';

    if (!$valid) {
        db_exec('INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)', [$ip, mb_substr($identifier, 0, 120)]);
        if ($user) {
            $fails = (int) $user['failed_attempts'] + 1;
            if ($fails >= LOGIN_MAX_ATTEMPTS_USER) {
                db_exec("UPDATE users SET failed_attempts = 0, locked_until = (NOW() + INTERVAL $minutes MINUTE) WHERE id = ?", [$user['id']]);
            } else {
                db_exec('UPDATE users SET failed_attempts = ? WHERE id = ?', [$fails, $user['id']]);
            }
        }
        log_activity('login_failed', 'Failed login for "' . mb_substr($identifier, 0, 60) . '"', $user ? (int) $user['id'] : null);
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    db_exec('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?', [$user['id']]);
    db_exec('DELETE FROM login_attempts WHERE ip_address = ?', [$ip]);

    session_regenerate_id(true);
    $_SESSION['user_id']     = (int) $user['id'];
    $_SESSION['fp']          = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $_SESSION['last_active'] = time();
    $_SESSION['remember']    = $remember;
    unset($_SESSION['_csrf']);

    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + REMEMBER_SECONDS,
            'path'     => app_base_path() === '' ? '/' : app_base_path() . '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    log_activity('login', 'Admin logged in', (int) $user['id']);
    return ['ok' => true, 'message' => 'OK'];
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        log_activity('logout', 'Admin logged out');
    }
    destroy_session();
}
