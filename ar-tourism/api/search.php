<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$q = mb_substr(input('q'), 0, 60);
if (mb_strlen($q) < 2) {
    json_response(['ok' => true, 'destinations' => [], 'attractions' => [], 'categories' => []]);
}
json_response(['ok' => true] + search_all($q, 5));
