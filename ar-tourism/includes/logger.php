<?php
declare(strict_types=1);

/** Records an administrative action in activity_logs. Never throws. */
function log_activity(string $action, string $description = '', ?int $userId = null): void
{
    try {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        db_exec(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)',
            [$userId, mb_substr($action, 0, 80), mb_substr($description, 0, 500), client_ip()]
        );
    } catch (Throwable $e) {
        error_log('log_activity failed: ' . $e->getMessage());
    }
}
