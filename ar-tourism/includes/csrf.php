<?php
/** CSRF protection: one token per session, checked on every state-changing request. */
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** For HTML form handlers. */
function csrf_require(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        exit('Security token expired. Please go back, refresh the page and try again.');
    }
}

/** For JSON endpoints. */
function csrf_require_json(): void
{
    if (!csrf_valid()) {
        json_response(['ok' => false, 'error' => 'Security token expired. Refresh the page and try again.'], 419);
    }
}
