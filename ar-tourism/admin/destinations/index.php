<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

if (is_post()) {
    csrf_require();
    $id = input_int('id');
    $d = destination_find($id);
    if ($d && input('action') === 'delete') {
        // gather files that belong to this destination's attractions before cascading delete
        $files = db_all('SELECT main_image AS f FROM attractions WHERE destination_id = ? UNION ALL
                         SELECT i.image_path FROM attraction_images i JOIN attractions a ON a.id = i.attraction_id WHERE a.destination_id = ?', [$id, $id]);
        db_exec('DELETE FROM destinations WHERE id = ?', [$id]);
        delete_upload($d['cover_image']);
        foreach ($files as $f) { delete_upload($f['f']); }
        log_activity('destination_deleted', 'Admin deleted destination "' . $d['name'] . '"');
        flash('success', 'Destination and its attractions were deleted.');
    } elseif ($d && input('action') === 'toggle') {
        $new = $d['status'] === 'active' ? 'inactive' : 'active';
        db_exec('UPDATE destinations SET status = ? WHERE id = ?', [$new, $id]);
        log_activity('destination_updated', 'Admin set destination "' . $d['name'] . '" to ' . $new);
        flash('success', 'Destination is now ' . $new . '.');
    }
    redirect('admin/destinations/index.php');
}

$q = input('q');
$status = input('status');
$result = destinations_query(['q' => $q, 'status' => $status, 'category_id' => input_int('category')], max(1, input_int('page', 1)), 10, false);
$cats = categories_list();

$adminTitle = 'Destinations';
$adminActive = 'destinations';
require dirname(__DIR__) . '/partials/layout_start.php';
$filters = '<select class="form-select" style="max-width:170px" name="category" aria-label="Category"><option value="0">All categories</option>' . select_options($cats, input_int('category')) . '</select>'
         . '<select class="form-select" style="max-width:150px" name="status" aria-label="Status"><option value="">Any status</option><option value="active"' . ($status === 'active' ? ' selected' : '') . '>Active</option><option value="inactive"' . ($status === 'inactive' ? ' selected' : '') . '>Inactive</option></select>';
admin_toolbar(url('admin/destinations/form.php'), 'Add destination', $q, 'Search destinations…', $filters);
?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th></th><th>Name</th><th>Category</th><th>Location</th><th>Attractions</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($result['rows'] as $d): ?>
    <tr>
      <td><img class="thumb" src="<?= e(image_url($d['cover_image'])) ?>" alt="" loading="lazy"></td>
      <td class="fw-semibold"><?= e($d['name']) ?><?= $d['featured'] ? ' <i class="fa-solid fa-star text-warning" title="Featured"></i>' : '' ?></td>
      <td><?= e((string) $d['category_name']) ?></td><td><?= e($d['location']) ?></td><td><?= (int) $d['attraction_count'] ?></td>
      <td><?= status_badge($d['status']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('destination.php?slug=' . rawurlencode($d['slug']))) ?>" target="_blank" rel="noopener" title="View"><i class="fa-solid fa-eye"></i><span class="visually-hidden">View</span></a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit" title="Activate / deactivate"><i class="fa-solid fa-power-off"></i><span class="visually-hidden">Toggle status</span></button></form>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/destinations/form.php?id=' . (int) $d['id'])) ?>" title="Edit"><i class="fa-solid fa-pen"></i><span class="visually-hidden">Edit</span></a>
        <?= delete_form('delete', (int) $d['id'], 'Delete destination "' . $d['name'] . '" and ALL of its attractions? This cannot be undone.') ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$result['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">No destinations found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= pagination_html($result['pg'], array_filter(['q' => $q, 'status' => $status, 'category' => input_int('category')])) ?></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
