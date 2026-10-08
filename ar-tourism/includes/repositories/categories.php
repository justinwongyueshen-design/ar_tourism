<?php
declare(strict_types=1);

function categories_list(bool $activeOnly = false): array
{
    $sql = 'SELECT c.*, (SELECT COUNT(*) FROM attractions a WHERE a.category_id = c.id) AS attraction_count
            FROM categories c ' . ($activeOnly ? "WHERE c.status = 'active' " : '') . 'ORDER BY c.name';
    return db_all($sql);
}

function category_find(int $id): ?array
{
    return db_one('SELECT * FROM categories WHERE id = ?', [$id]);
}
