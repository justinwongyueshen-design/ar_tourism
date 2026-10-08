<?php
declare(strict_types=1);

function poster_find(int $id): ?array
{
    return db_one('SELECT * FROM ar_posters WHERE id = ?', [$id]);
}

function posters_list(string $q = ''): array
{
    $params = [];
    $w = '';
    if ($q !== '') {
        $w = 'WHERE p.poster_name LIKE ? OR p.description LIKE ?';
        $params = [like($q), like($q)];
    }
    return db_all("SELECT p.*, (SELECT COUNT(*) FROM ar_hotspots h WHERE h.poster_id = p.id) AS hotspot_count
                   FROM ar_posters p $w ORDER BY p.updated_at DESC", $params);
}

function poster_hotspots(int $posterId): array
{
    return db_all('SELECT * FROM ar_hotspots WHERE poster_id = ? ORDER BY z_index, id', [$posterId]);
}

function poster_is_scannable(array $poster): bool
{
    return $poster['target_status'] === 'READY' && !empty($poster['target_file']);
}

/** Public URL of the compiled .mind file (null when not compiled). */
function poster_target_url(array $poster): ?string
{
    if (!poster_is_scannable($poster)) {
        return null;
    }
    $file = APP_ROOT . '/assets/ar-targets/' . basename((string) $poster['target_file']);
    return is_file($file) ? asset('ar-targets/' . basename((string) $poster['target_file'])) : null;
}

/**
 * Everything the AR front-end needs, as plain data.
 * Coordinates stay normalised (0..1, origin top-left of the poster).
 */
function poster_ar_payload(array $poster): array
{
    $rows = db_all(
        "SELECT h.*, a.name AS a_name, a.short_description AS a_short, a.main_image AS a_image, a.youtube_id AS a_youtube,
                a.maps_url AS a_maps, a.latitude AS a_lat, a.longitude AS a_lng, a.website_url AS a_web,
                a.opening_hours AS a_hours, a.entry_info AS a_entry, a.slug AS a_slug, c.name AS a_category
         FROM ar_hotspots h
         LEFT JOIN attractions a ON a.id = h.attraction_id AND a.status = 'active'
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE h.poster_id = ? ORDER BY h.z_index, h.id",
        [$poster['id']]
    );
    $hotspots = [];
    foreach ($rows as $r) {
        $hasAttraction = $r['a_name'] !== null;
        $videoId = youtube_id($r['video_url']) ?: ($hasAttraction ? $r['a_youtube'] : null);
        $hotspots[] = [
            'id'           => (int) $r['id'],
            'label'        => $r['label'] !== '' ? $r['label'] : ($r['a_name'] ?? 'Hotspot'),
            'x'            => (float) $r['x'],
            'y'            => (float) $r['y'],
            'w'            => (float) $r['width'],
            'h'            => (float) $r['height'],
            'z'            => (int) $r['z_index'],
            'content_type' => $r['content_type'],
            'video_id'     => $videoId,
            'card'         => [
                'title'         => $hasAttraction ? $r['a_name'] : ($r['label'] ?: 'Information'),
                'description'   => $hasAttraction ? ($r['a_short'] ?? '') : (string) $r['custom_content'],
                'extra'         => $hasAttraction ? (string) $r['custom_content'] : '',
                'category'      => $r['a_category'],
                'image'         => $hasAttraction ? image_url($r['a_image']) : null,
                'opening_hours' => $r['a_hours'],
                'entry_info'    => $r['a_entry'],
                'maps_url'      => $hasAttraction ? map_link(['maps_url' => $r['a_maps'], 'latitude' => $r['a_lat'], 'longitude' => $r['a_lng']]) : null,
                'website_url'   => $hasAttraction ? clean_url($r['a_web']) : null,
                'detail_url'    => $hasAttraction ? base_url('attraction.php?slug=' . rawurlencode($r['a_slug'])) : null,
                'lat'           => $hasAttraction && $r['a_lat'] !== null ? (float) $r['a_lat'] : null,
                'lng'           => $hasAttraction && $r['a_lng'] !== null ? (float) $r['a_lng'] : null,
            ],
        ];
    }
    $aspect = $poster['image_width'] > 0 ? $poster['image_height'] / $poster['image_width'] : 1.0;
    return [
        'poster' => [
            'id'          => (int) $poster['id'],
            'name'        => $poster['poster_name'],
            'description' => $poster['description'],
            'width'       => (int) $poster['image_width'],
            'height'      => (int) $poster['image_height'],
            'aspect'      => round((float) $aspect, 6),
            'target_url'  => poster_target_url($poster),
        ],
        'hotspots' => $hotspots,
    ];
}
