<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_super_admin();

function active_admin_count(): int
{
    return (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
}

if (is_post()) {
    csrf_require();
    $id = input_int('id');
    $u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    $me = current_user();
    if ($u && input('action') === 'delete') {
        if ((int) $u['id'] === (int) $me['id']) {
            flash('danger', 'You cannot delete your own account.');
        } elseif ($u['role'] === 'admin' && $u['status'] === 'active' && active_admin_count() <= 1) {
            flash('danger', 'You cannot delete the last active administrator.');
        } else {
            db_exec('DELETE FROM users WHERE id = ?', [$id]);
            log_activity('user_deleted', 'Admin deleted user "' . $u['username'] . '"');
            flash('success', 'User deleted.');
        }
    } elseif ($u && input('action') === 'toggle') {
        if ((int) $u['id'] === (int) $me['id']) {
            flash('danger', 'You cannot deactivate your own account.');
        } elseif ($u['role'] === 'admin' && $u['status'] === 'active' && active_admin_count() <= 1) {
            flash('danger', 'You cannot deactivate the last active administrator.');
        } else {
            $new = $u['status'] === 'active' ? 'inactive' : 'active';
            db_exec('UPDATE users SET status = ? WHERE id = ?', [$new, $id]);
            log_activity('user_updated', 'Admin set user "' . $u['username'] . '" to ' . $new);
            flash('success', 'User is now ' . $new . '.');
        }
    }
    redirect('admin/users/index.php');
}

$q = input('q');
$params = []; $where = '';
if ($q !== '') { $where = 'WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?'; $l = like($q); $params = [$l, $l, $l]; }
$total = (int) db_val("SELECT COUNT(*) FROM users $where", $params);
$pg = paginate($total, 15, input_int('page', 1));
$limit = (int) $pg['per_page']; $offset = (int) $pg['offset'];
$rows = db_all("SELECT * FROM users $where ORDER BY username LIMIT $limit OFFSET $offset", $params);

$adminTitle = 'Users';
$adminActive = 'users';
require dirname(__DIR__) . '/partials/layout_start.php';
admin_toolbar(url('admin/users/form.php'), 'Add user', $q, 'Search users…');
?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $u): ?>
    <tr>
      <td class="fw-semibold"><?= e($u['username']) ?></td><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td>
      <td><span class="badge text-bg-<?= $u['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= e($u['role']) ?></span></td>
      <td><?= status_badge($u['status']) ?></td>
      <td><small class="text-muted"><?= $u['last_login_at'] ? e(date('d M Y H:i', strtotime((string) $u['last_login_at']))) : '—' ?></small></td>
      <td class="text-end text-nowrap">
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit" title="Activate / deactivate"><i class="fa-solid fa-power-off"></i><span class="visually-hidden">Toggle status</span></button></form>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/users/form.php?id=' . (int) $u['id'])) ?>" title="Edit"><i class="fa-solid fa-pen"></i><span class="visually-hidden">Edit</span></a>
        <?= delete_form('delete', (int) $u['id'], 'Delete user "' . $u['username'] . '"?') ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= pagination_html($pg, array_filter(['q' => $q])) ?></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
