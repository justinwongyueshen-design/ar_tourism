<?php
/** GET api/ar/poster.php?id=N  ->  poster info + normalised hotspots for the AR front-end. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$poster = poster_find(input_int('id'));
if (!$poster || ($poster['status'] !== 'published' && !is_logged_in())) {
    json_response(['ok' => false, 'error' => 'Poster not found.'], 404);
}
json_response(['ok' => true] + poster_ar_payload($poster));
