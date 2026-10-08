<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

if (is_post()) {
    csrf_require();
    $id = input_int('id');
    $cat = category_find($id);
    if ($cat && input('action') === 'delete') {
        db_exec('DELETE FROM categories WHERE id = ?', [$id]);
        log_activity('category_deleted', 'Admin deleted category "' . $cat['name'] . '"');
        flash('success', 'Category deleted. Items that used it are now uncategorised.');
    } elseif ($cat && input('action') === 'toggle') {
        $new = $cat['status'] === 'active' ? 'inactive' : 'active';
        db_exec('UPDATE categories SET status = ? WHERE id = ?', [$new, $id]);
        log_activity('category_updated', 'Admin set category "' . $cat['name'] . '" to ' . $new);
        flash('success', 'Category is now ' . $new . '.');
    }
    redirect('admin/categories/index.php');
}

$q = input('q');
$params = [];
$where = '';
if ($q !== '') { $where = 'WHERE c.name LIKE ?'; $params[] = like($q); }
$total = (int) db_val("SELECT COUNT(*) FROM categories c $where", $params);
$pg = paginate($total, 15, input_int('page', 1));
$limit = (int) $pg['per_page']; $offset = (int) $pg['offset'];
$rows = db_all("SELECT c.*, (SELECT COUNT(*) FROM attractions a WHERE a.category_id = c.id) AS n_attr,
                (SELECT COUNT(*) FROM destinations d WHERE d.category_id = c.id) AS n_dest
                FROM categories c $where ORDER BY c.name LIMIT $limit OFFSET $offset", $params);

$adminTitle = 'Categories';
$adminActive = 'categories';
require dirname(__DIR__) . '/partials/layout_start.php';
admin_toolbar(url('admin/categories/form.php'), 'Add category', $q, 'Search categories…');
?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Name</th><th>Icon</th><th>Destinations</th><th>Attractions</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td class="fw-semibold"><?= e($c['name']) ?></td>
      <td><i class="fa-solid <?= e($c['icon']) ?> me-1"></i><small class="text-muted"><?= e($c['icon']) ?></small></td>
      <td><?= (int) $c['n_dest'] ?></td><td><?= (int) $c['n_attr'] ?></td>
      <td><?= status_badge($c['status']) ?></td>
      <td class="text-end text-nowrap">
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <button class="btn btn-sm btn-outline-secondary" type="submit" title="Activate / deactivate"><i class="fa-solid fa-power-off"></i><span class="visually-hidden">Toggle status</span></button></form>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/categories/form.php?id=' . (int) $c['id'])) ?>" title="Edit"><i class="fa-solid fa-pen"></i><span class="visually-hidden">Edit</span></a>
        <?= delete_form('delete', (int) $c['id'], 'Delete category "' . $c['name'] . '"? Items using it become uncategorised.') ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No categories found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= pagination_html($pg, array_filter(['q' => $q])) ?></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
