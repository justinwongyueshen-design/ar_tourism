<?php
/**
 * Secure image uploads.
 *  - checks upload errors, size, real MIME type (finfo), extension and image dimensions
 *  - never uses the client's filename (random name is generated)
 *  - re-encodes the image with GD (when available) which strips any hidden payload / EXIF
 *  - stores below assets/uploads/<subdir>/ where PHP execution is disabled by .htaccess
 */
declare(strict_types=1);

const UPLOAD_MIME_MAP = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/webp' => ['webp'],
];

/** Normalises $_FILES[$field] (single or multiple) into a list of single-file arrays. */
function uploaded_files_list(string $field): array
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }
    $list = [];
    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
                   'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $list;
}

function has_upload(string $field): bool
{
    return uploaded_files_list($field) !== [];
}

/**
 * @param array{min_width?:int,store_max_width?:int,max_dimension?:int} $opt
 * @return array{ok:bool,path?:string,width?:int,height?:int,error?:string}
 */
function handle_image_upload(array $file, string $subdir, int $maxBytes, array $opt = []): array
{
    $minWidth  = $opt['min_width'] ?? 200;
    $storeMax  = $opt['store_max_width'] ?? 1800;
    $maxDim    = $opt['max_dimension'] ?? 8000;

    if (!preg_match('/^[a-z0-9_-]+$/', $subdir)) {
        return ['ok' => false, 'error' => 'Invalid upload folder.'];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return ['ok' => false, 'error' => 'The file is larger than the server allows (check upload_max_filesize).'];
        default: return ['ok' => false, 'error' => 'Upload failed (error code ' . (int) $file['error'] . ').'];
    }
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'Image must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);
    if (!isset(UPLOAD_MIME_MAP[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG or WEBP images are allowed.'];
    }
    $origExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($origExt, UPLOAD_MIME_MAP[$mime], true)) {
        return ['ok' => false, 'error' => 'The file extension does not match the image type.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] < 1 || $info[1] < 1) {
        return ['ok' => false, 'error' => 'The file is not a valid image.'];
    }
    if ($info[0] < $minWidth) {
        return ['ok' => false, 'error' => "Image is too small (minimum width {$minWidth}px)."];
    }
    if ($info[0] > $maxDim || $info[1] > $maxDim) {
        return ['ok' => false, 'error' => "Image dimensions are too large (maximum {$maxDim}px)."];
    }

    $ext     = UPLOAD_MIME_MAP[$mime][0];
    $dirRel  = 'uploads/' . $subdir;
    $dirAbs  = APP_ROOT . '/assets/' . $dirRel;
    if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
        return ['ok' => false, 'error' => 'Upload folder is not writable.'];
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = $dirAbs . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded file (check folder permissions).'];
    }
    @chmod($dest, 0644);

    if (!optimize_image($dest, $mime, $storeMax)) {
        @unlink($dest);
        return ['ok' => false, 'error' => 'The image could not be processed. Try another file.'];
    }
    $final = @getimagesize($dest);
    return ['ok' => true, 'path' => $dirRel . '/' . $name, 'width' => (int) ($final[0] ?? $info[0]), 'height' => (int) ($final[1] ?? $info[1])];
}

/** Re-encodes and (if needed) downsizes the image. Returns true when the file is a valid image. */
function optimize_image(string $path, string $mime, int $maxWidth): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return true; // GD missing: keep the validated original
    }
    $data = @file_get_contents($path);
    $img  = $data === false ? false : @imagecreatefromstring($data);
    if (!$img) {
        return false;
    }
    // Apply EXIF orientation for phone photos before EXIF is dropped
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rot = imagerotate($img, $angle, 0);
            if ($rot) { $img = $rot; }
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > $maxWidth) {
        $nh  = (int) round($h * $maxWidth / $w);
        $dst = imagecreatetruecolor($maxWidth, $nh);
        if ($mime !== 'image/jpeg') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);
        $img = $dst;
    }
    switch ($mime) {
        case 'image/jpeg': imageinterlace($img, true); $ok = imagejpeg($img, $path, 85); break;
        case 'image/png':  imagesavealpha($img, true); $ok = imagepng($img, $path, 6); break;
        case 'image/webp': $ok = function_exists('imagewebp') ? imagewebp($img, $path, 85) : true; break;
        default: $ok = false;
    }
    return (bool) $ok;
}

/** Deletes a file previously stored under assets/uploads/. Demo assets are never deleted. */
function delete_upload(?string $relative): void
{
    $relative = (string) $relative;
    if ($relative === '' || strpos($relative, 'uploads/') !== 0 || strpos($relative, '..') !== false) {
        return;
    }
    $file = APP_ROOT . '/assets/' . $relative;
    if (is_file($file)) {
        @unlink($file);
    }
}
