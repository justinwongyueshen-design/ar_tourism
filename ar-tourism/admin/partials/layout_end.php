<?php declare(strict_types=1); ?>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="confirmTitle">Please confirm</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body" id="confirmBody">Are you sure?</div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmYes">Yes, continue</button></div>
  </div></div>
</div>

<script src="<?= e(vendor('bootstrap/js/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@' . BOOTSTRAP_VERSION . '/dist/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
<?php if (!empty($adminScripts)) { echo $adminScripts; } ?>
</body>
</html>
