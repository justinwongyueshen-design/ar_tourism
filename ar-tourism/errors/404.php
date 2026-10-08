<?php
declare(strict_types=1);
if (!function_exists('e')) { require_once __DIR__ . '/../includes/bootstrap.php'; }
$pageTitle = 'Page not found';
require __DIR__ . '/../includes/header.php';
?>
<div class="container section text-center py-5">
  <h1 class="display-4"><i class="fa-solid fa-compass text-success"></i> 404</h1>
  <p class="lead">We could not find that page.</p>
  <a class="btn btn-success" href="<?= e(url('index.php')) ?>">Back to home</a>
</div>
<?php require __DIR__ . '/../includes/footer.php';
