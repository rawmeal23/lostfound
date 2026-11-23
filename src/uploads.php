<?php
declare(strict_types=1);

// CHANGE: Validate and store optional report photos under the uploads directory.
function upload_photo(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => '', 'errors' => []];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'errors' => ['The photo upload failed.']];
    }

    $temporary_path = $file['tmp_name'] ?? '';
    $file_size = $file['size'] ?? 0;
    if (!is_string($temporary_path) || !is_uploaded_file($temporary_path)) {
        return ['ok' => false, 'path' => '', 'errors' => ['The uploaded photo is not valid.']];
    }
    if (!is_int($file_size) || $file_size < 1 || $file_size > 3 * 1024 * 1024) {
        return ['ok' => false, 'path' => '', 'errors' => ['The photo must be 3 MB or smaller.']];
    }

    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->file($temporary_path);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!is_string($mime_type) || !isset($extensions[$mime_type])) {
        return ['ok' => false, 'path' => '', 'errors' => ['Only JPG and PNG photos are allowed.']];
    }

    $uploads_directory = __DIR__ . '/../uploads';
    if (!is_dir($uploads_directory) || !is_writable($uploads_directory)) {
        return ['ok' => false, 'path' => '', 'errors' => ['The uploads directory is unavailable.']];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime_type];
    if (!move_uploaded_file($temporary_path, $uploads_directory . '/' . $filename)) {
        return ['ok' => false, 'path' => '', 'errors' => ['The photo could not be saved.']];
    }

    return ['ok' => true, 'path' => '../uploads/' . $filename, 'errors' => []];
}
