<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/partials/cards.php';

$pageTitle = 'Destinations';
$navActive = 'destinations';
$q   = input('q');
$cat = input_int('category');
$result = destinations_query(['q' => $q, 'category_id' => $cat], max(1, input_int('page', 1)), 9, true);
$categories = categories_list(true);
require __DIR__ . '/includes/header.php';
?>
<header class="page-head"><div class="container"><h1>Destinations</h1><p class="mb-0">Choose a destination to see its attractions and AR experiences.</p></div></header>
<div class="container section">
  <form class="row g-2 mb-4" method="get" role="search">
    <div class="col-md-6"><label class="visually-hidden" for="q">Search destinations</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Search destinations…"></div>
    <div class="col-md-4"><label class="visually-hidden" for="category">Category</label>
      <select class="form-select" id="category" name="category"><option value="0">All categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-success" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Filter</button></div>
  </form>
  <?php if (!$result['rows']): ?>
    <div class="alert alert-info">No destinations found.</div>
  <?php else: ?>
    <div class="row g-4"><?php foreach ($result['rows'] as $d): ?><div class="col-md-6 col-lg-4"><?= render_destination_card($d) ?></div><?php endforeach; ?></div>
    <div class="mt-4"><?= pagination_html($result['pg'], array_filter(['q' => $q, 'category' => $cat])) ?></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
