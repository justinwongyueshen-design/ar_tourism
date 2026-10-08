<?php
declare(strict_types=1);

const ATTRACTION_SELECT = 'SELECT a.*, d.name AS destination_name, d.slug AS destination_slug,
        c.name AS category_name, c.icon AS category_icon
        FROM attractions a
        JOIN destinations d ON d.id = a.destination_id
        LEFT JOIN categories c ON c.id = a.category_id';

/**
 * @param array{q?:string,category_id?:int,destination_id?:int,status?:string,featured?:bool,order?:string} $f
 * @return array{rows:array,pg:array}
 */
function attractions_query(array $f, int $page, int $perPage, bool $publicOnly): array
{
    $where = [];
    $params = [];
    if ($publicOnly) {
        $where[] = "a.status = 'active' AND d.status = 'active'";
    } elseif (!empty($f['status']) && in_array($f['status'], ['active', 'inactive'], true)) {
        $where[] = 'a.status = ?';
        $params[] = $f['status'];
    }
    if (!empty($f['q'])) {
        $where[] = '(a.name LIKE ? OR a.short_description LIKE ? OR d.name LIKE ? OR c.name LIKE ?)';
        $l = like($f['q']);
        array_push($params, $l, $l, $l, $l);
    }
    if (!empty($f['category_id'])) {
        $where[] = 'a.category_id = ?';
        $params[] = (int) $f['category_id'];
    }
    if (!empty($f['destination_id'])) {
        $where[] = 'a.destination_id = ?';
        $params[] = (int) $f['destination_id'];
    }
    if (!empty($f['featured'])) {
        $where[] = 'a.featured = 1';
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderMap = ['popular' => 'a.views DESC, a.name', 'recent' => 'a.updated_at DESC', 'default' => 'd.name, a.display_order, a.name'];
    $order = $orderMap[$f['order'] ?? 'default'] ?? $orderMap['default'];

    $total = (int) db_val('SELECT COUNT(*) FROM attractions a JOIN destinations d ON d.id = a.destination_id LEFT JOIN categories c ON c.id = a.category_id ' . $w, $params);
    $pg = paginate($total, $perPage, $page);
    $limit = (int) $pg['per_page'];
    $offset = (int) $pg['offset'];
    $rows = db_all(ATTRACTION_SELECT . " $w ORDER BY $order LIMIT $limit OFFSET $offset", $params);
    return ['rows' => $rows, 'pg' => $pg];
}

function attraction_find(int $id): ?array
{
    return db_one(ATTRACTION_SELECT . ' WHERE a.id = ?', [$id]);
}

function attraction_by_slug(string $slug): ?array
{
    return db_one(ATTRACTION_SELECT . " WHERE a.slug = ? AND a.status = 'active' AND d.status = 'active'", [$slug]);
}

function attraction_images(int $attractionId): array
{
    return db_all('SELECT * FROM attraction_images WHERE attraction_id = ? ORDER BY sort_order, id', [$attractionId]);
}

function attractions_options(): array
{
    return db_all('SELECT a.id, a.name, d.name AS destination_name FROM attractions a JOIN destinations d ON d.id = a.destination_id ORDER BY d.name, a.name');
}

/** Cross-entity search used by the public AJAX search box. */
function search_all(string $q, int $limit = 6): array
{
    $l = like($q);
    $limit = max(1, min(20, $limit));
    return [
        'destinations' => db_all("SELECT name, slug, short_description FROM destinations WHERE status = 'active' AND (name LIKE ? OR short_description LIKE ?) ORDER BY name LIMIT $limit", [$l, $l]),
        'attractions'  => db_all("SELECT a.name, a.slug, d.name AS destination_name FROM attractions a JOIN destinations d ON d.id = a.destination_id
                                  WHERE a.status = 'active' AND d.status = 'active' AND (a.name LIKE ? OR a.short_description LIKE ?) ORDER BY a.name LIMIT $limit", [$l, $l]),
        'categories'   => db_all("SELECT name, slug FROM categories WHERE status = 'active' AND name LIKE ? ORDER BY name LIMIT $limit", [$l]),
    ];
}
