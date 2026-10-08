<?php
declare(strict_types=1);

/**
 * Extracts the 11-character video id from:
 *   youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, youtube.com/shorts/ID
 */
function youtube_id(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
        return $url;
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return null;
    }
    $host = strtolower(preg_replace('/^(www|m)\./', '', $parts['host']) ?? '');
    $id = null;
    if ($host === 'youtu.be') {
        $id = trim($parts['path'] ?? '', '/');
    } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        $path = $parts['path'] ?? '';
        if ($path === '/watch') {
            parse_str($parts['query'] ?? '', $q);
            $id = is_string($q['v'] ?? null) ? $q['v'] : null;
        } elseif (preg_match('#^/(embed|shorts|v)/([^/?]+)#', $path, $m)) {
            $id = $m[2];
        }
    }
    return ($id !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) ? $id : null;
}

function youtube_watch_url(string $id): string
{
    return 'https://www.youtube.com/watch?v=' . rawurlencode($id);
}
