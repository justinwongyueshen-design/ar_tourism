<?php
/** Small helpers shared by admin forms. */
declare(strict_types=1);

/** Value for a form field: submitted value first, then the DB row, then default. */
function fv(array $row, string $key, string $default = ''): string
{
    if (isset($_POST[$key]) && is_string($_POST[$key])) {
        return $_POST[$key];
    }
    return isset($row[$key]) ? (string) $row[$key] : $default;
}

function select_options(array $options, $selected, string $valueKey = 'id', string $labelKey = 'name', ?string $blank = null): string
{
    $html = $blank !== null ? '<option value="">' . e($blank) . '</option>' : '';
    foreach ($options as $o) {
        $sel = ((string) $o[$valueKey] === (string) $selected) ? ' selected' : '';
        $html .= '<option value="' . e((string) $o[$valueKey]) . '"' . $sel . '>' . e((string) $o[$labelKey]) . '</option>';
    }
    return $html;
}

function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
}

/** Standard admin search + header row. */
function admin_toolbar(string $addUrl, string $addLabel, string $q, string $placeholder, string $extraFilters = ''): void
{
    ?>
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
      <form class="d-flex flex-wrap gap-2 flex-grow-1" method="get" role="search">
        <label class="visually-hidden" for="q">Search</label>
        <input type="search" class="form-control" style="max-width:280px" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= e($placeholder) ?>">
        <?= $extraFilters ?>
        <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i><span class="visually-hidden">Search</span></button>
      </form>
      <?php if ($addUrl !== ''): ?><a class="btn btn-success" href="<?= e($addUrl) ?>"><i class="fa-solid fa-plus me-1"></i><?= e($addLabel) ?></a><?php endif; ?>
    </div>
    <?php
}

function delete_form(string $action, int $id, string $confirm, string $extra = ''): string
{
    return '<form method="post" class="d-inline" data-confirm="' . e($confirm) . '">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">' . $extra
        . '<button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="fa-solid fa-trash"></i><span class="visually-hidden">Delete</span></button></form>';
}
