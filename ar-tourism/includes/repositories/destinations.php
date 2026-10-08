<?php
declare(strict_types=1);

/**
 * @param array{q?:string,category_id?:int,status?:string,featured?:bool} $f
 * @return array{rows:array,pg:array}
 */
function destinations_query(array $f, int $page, int $perPage, bool $publicOnly): array
{
    $where = [];
    $params = [];
    if ($publicOnly) {
        $where[] = "d.status = 'active'";
    } elseif (!empty($f['status']) && in_array($f['status'], ['active', 'inactive'], true)) {
        $where[] = 'd.status = ?';
        $params[] = $f['status'];
    }
    if (!empty($f['q'])) {
        $where[] = '(d.name LIKE ? OR d.short_description LIKE ? OR d.location LIKE ?)';
        $l = like($f['q']);
        array_push($params, $l, $l, $l);
    }
    if (!empty($f['category_id'])) {
        $where[] = 'd.category_id = ?';
        $params[] = (int) $f['category_id'];
    }
    if (!empty($f['featured'])) {
        $where[] = 'd.featured = 1';
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int) db_val("SELECT COUNT(*) FROM destinations d $w", $params);
    $pg = paginate($total, $perPage, $page);
    $limit = (int) $pg['per_page'];
    $offset = (int) $pg['offset'];
    $rows = db_all(
        "SELECT d.*, c.name AS category_name, c.icon AS category_icon,
                (SELECT COUNT(*) FROM attractions a WHERE a.destination_id = d.id AND a.status = 'active') AS attraction_count
         FROM destinations d LEFT JOIN categories c ON c.id = d.category_id
         $w ORDER BY d.featured DESC, d.name LIMIT $limit OFFSET $offset",
        $params
    );
    return ['rows' => $rows, 'pg' => $pg];
}

function destination_find(int $id): ?array
{
    return db_one('SELECT d.*, c.name AS category_name FROM destinations d LEFT JOIN categories c ON c.id = d.category_id WHERE d.id = ?', [$id]);
}

function destination_by_slug(string $slug): ?array
{
    return db_one("SELECT d.*, c.name AS category_name, c.icon AS category_icon FROM destinations d
                   LEFT JOIN categories c ON c.id = d.category_id WHERE d.slug = ? AND d.status = 'active'", [$slug]);
}

function destinations_options(): array
{
    return db_all('SELECT id, name FROM destinations ORDER BY name');
}
