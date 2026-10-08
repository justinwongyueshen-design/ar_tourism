<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/partials/helpers.php';
require_login();

if (is_post()) {
    csrf_require();
    $id = input_int('id');
    $a = attraction_find($id);
    if ($a && input('action') === 'delete') {
        $imgs = attraction_images($id);
        db_exec('DELETE FROM attractions WHERE id = ?', [$id]);
        delete_upload($a['main_image']);
        foreach ($imgs as $i) { delete_upload($i['image_path']); }
        log_activity('attraction_deleted', 'Admin deleted attraction "' . $a['name'] . '"');
        flash('success', 'Attraction deleted. Any AR hotspots that used it were detached.');
    } elseif ($a && input('action') === 'toggle') {
        $new = $a['status'] === 'active' ? 'inactive' : 'active';
        db_exec('UPDATE attractions SET status = ? WHERE id = ?', [$new, $id]);
        log_activity('attraction_updated', 'Admin set attraction "' . $a['name'] . '" to ' . $new);
        flash('success', 'Attraction is now ' . $new . '.');
    }
    redirect('admin/attractions/index.php');
}

$q = input('q'); $status = input('status'); $destId = input_int('destination'); $catId = input_int('category');
$result = attractions_query(['q' => $q, 'status' => $status, 'destination_id' => $destId, 'category_id' => $catId], max(1, input_int('page', 1)), 10, false);

$adminTitle = 'Attractions';
$adminActive = 'attractions';
require dirname(__DIR__) . '/partials/layout_start.php';
$filters = '<select class="form-select" style="max-width:180px" name="destination" aria-label="Destination"><option value="0">All destinations</option>' . select_options(destinations_options(), $destId) . '</select>'
         . '<select class="form-select" style="max-width:160px" name="category" aria-label="Category"><option value="0">All categories</option>' . select_options(categories_list(), $catId) . '</select>'
         . '<select class="form-select" style="max-width:140px" name="status" aria-label="Status"><option value="">Any status</option><option value="active"' . ($status === 'active' ? ' selected' : '') . '>Active</option><option value="inactive"' . ($status === 'inactive' ? ' selected' : '') . '>Inactive</option></select>';
admin_toolbar(url('admin/attractions/form.php'), 'Add attraction', $q, 'Search attractions…', $filters);
?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th></th><th>Name</th><th>Destination</th><th>Category</th><th>Video</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($result['rows'] as $a): ?>
    <tr>
      <td><img class="thumb" src="<?= e(image_url($a['main_image'])) ?>" alt="" loading="lazy"></td>
      <td class="fw-semibold"><?= e($a['name']) ?><?= $a['featured'] ? ' <i class="fa-solid fa-star text-warning" title="Featured"></i>' : '' ?></td>
      <td><?= e($a['destination_name']) ?></td><td><?= e((string) $a['category_name']) ?></td>
      <td><?= $a['youtube_id'] ? '<i class="fa-brands fa-youtube text-danger"></i>' : '<span class="text-muted">—</span>' ?></td>
      <td><?= status_badge($a['status']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('attraction.php?slug=' . rawurlencode($a['slug']))) ?>" target="_blank" rel="noopener" title="View"><i class="fa-solid fa-eye"></i><span class="visually-hidden">View</span></a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit" title="Activate / deactivate"><i class="fa-solid fa-power-off"></i><span class="visually-hidden">Toggle status</span></button></form>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/attractions/form.php?id=' . (int) $a['id'])) ?>" title="Edit"><i class="fa-solid fa-pen"></i><span class="visually-hidden">Edit</span></a>
        <?= delete_form('delete', (int) $a['id'], 'Delete attraction "' . $a['name'] . '"? This cannot be undone.') ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$result['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">No attractions found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= pagination_html($result['pg'], array_filter(['q' => $q, 'status' => $status, 'destination' => $destId, 'category' => $catId])) ?></div>
<?php require dirname(__DIR__) . '/partials/layout_end.php';
