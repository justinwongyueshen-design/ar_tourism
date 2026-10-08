<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_super_admin();

$q = input('q');
$params = []; $where = '';
if ($q !== '') { $where = 'WHERE l.action LIKE ? OR l.description LIKE ? OR u.username LIKE ? OR l.ip_address LIKE ?'; $l = like($q); $params = [$l, $l, $l, $l]; }
$total = (int) db_val("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id $where", $params);
$pg = paginate($total, 25, input_int('page', 1));
$limit = (int) $pg['per_page']; $offset = (int) $pg['offset'];
$rows = db_all("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id $where ORDER BY l.id DESC LIMIT $limit OFFSET $offset", $params);

$adminTitle = 'Activity Logs';
$adminActive = 'logs';
require dirname(__DIR__) . '/partials/layout_start.php';
admin_toolbar('', '', $q, 'Search logs…');
?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $l): ?>
    <tr><td class="text-nowrap"><small><?= e(date('d M Y H:i:s', strtotime((string) $l['created_at']))) ?></small></td><td><?= e($l['username'] ?? '—') ?></td>
      <td><code><?= e($l['action']) ?></code></td><td><?= e($l['description']) ?></td><td><small class="text-muted"><?= e($l['ip_address']) ?></small></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No log entries.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= pagination_html($pg, array_filter(['q' => $q])) ?></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
