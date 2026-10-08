<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_super_admin();

$id  = input_int('id');
$row = $id ? db_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
if ($id && !$row) { flash('danger', 'User not found.'); redirect('admin/users/index.php'); }
$row = $row ?? [];
$errors = [];

if (is_post()) {
    csrf_require();
    $username = input('username');
    $email    = input('email');
    $full     = mb_substr(input('full_name'), 0, 100);
    $role     = input('role') === 'admin' ? 'admin' : 'editor';
    $status   = input('status') === 'inactive' ? 'inactive' : 'active';
    $pass     = (string) ($_POST['password'] ?? '');
    $pass2    = (string) ($_POST['password2'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) { $errors['username'] = '3–50 characters: letters, numbers, dot, dash, underscore.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) { $errors['email'] = 'Enter a valid email address.'; }
    if (!$id || $pass !== '') {
        if (strlen($pass) < 10) { $errors['password'] = 'Use at least 10 characters.'; }
        elseif ($pass !== $pass2) { $errors['password2'] = 'Passwords do not match.'; }
    }
    if (!$errors && db_val('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ?', [$username, $email, $id]) !== null) {
        $errors['username'] = 'That username or email is already used.';
    }
    $me = current_user();
    if (!$errors && $id === (int) $me['id'] && ($role !== 'admin' || $status !== 'active')) {
        $errors['role'] = 'You cannot remove your own admin access.';
    }
    if (!$errors) {
        if ($id) {
            db_exec('UPDATE users SET username=?, email=?, full_name=?, role=?, status=? WHERE id=?', [$username, $email, $full, $role, $status, $id]);
            if ($pass !== '') { db_exec('UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]); }
            log_activity('user_updated', 'Admin updated user "' . $username . '"');
            flash('success', 'User updated.');
        } else {
            db_exec('INSERT INTO users (username, email, full_name, role, status, password_hash) VALUES (?,?,?,?,?,?)', [$username, $email, $full, $role, $status, password_hash($pass, PASSWORD_DEFAULT)]);
            log_activity('user_added', 'Admin added user "' . $username . '"');
            flash('success', 'User added.');
        }
        redirect('admin/users/index.php');
    }
}
$adminTitle = $id ? 'Edit user' : 'Add user';
$adminActive = 'users';
require dirname(__DIR__) . '/partials/layout_start.php';
?>
<div class="card" style="max-width:680px"><div class="card-body">
<form method="post" novalidate autocomplete="off"><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label required" for="username">Username</label><input class="form-control<?= invalid_class($errors, 'username') ?>" id="username" name="username" value="<?= e(fv($row, 'username')) ?>" required><?= field_error($errors, 'username') ?></div>
    <div class="col-md-6"><label class="form-label" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" value="<?= e(fv($row, 'full_name')) ?>"></div>
    <div class="col-12"><label class="form-label required" for="email">Email</label><input class="form-control<?= invalid_class($errors, 'email') ?>" type="email" id="email" name="email" value="<?= e(fv($row, 'email')) ?>" required><?= field_error($errors, 'email') ?></div>
    <div class="col-md-6"><label class="form-label" for="role">Role</label><select class="form-select<?= invalid_class($errors, 'role') ?>" id="role" name="role"><option value="editor" <?= fv($row, 'role', 'editor') === 'editor' ? 'selected' : '' ?>>Editor (content only)</option><option value="admin" <?= fv($row, 'role') === 'admin' ? 'selected' : '' ?>>Admin (everything)</option></select><?= field_error($errors, 'role') ?></div>
    <div class="col-md-6"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="active" <?= fv($row, 'status', 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= fv($row, 'status') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
    <div class="col-md-6"><label class="form-label <?= $id ? '' : 'required' ?>" for="password"><?= $id ? 'New password' : 'Password' ?></label><input class="form-control<?= invalid_class($errors, 'password') ?>" type="password" id="password" name="password" autocomplete="new-password"><?= field_error($errors, 'password') ?><?php if ($id): ?><div class="form-text">Leave blank to keep the current password.</div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="password2">Confirm password</label><input class="form-control<?= invalid_class($errors, 'password2') ?>" type="password" id="password2" name="password2" autocomplete="new-password"><?= field_error($errors, 'password2') ?></div>
  </div>
  <div class="mt-4"><button class="btn btn-success" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button> <a class="btn btn-light" href="<?= e(url('admin/users/index.php')) ?>">Cancel</a></div>
</form></div></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
