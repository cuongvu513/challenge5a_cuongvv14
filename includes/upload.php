<?php


function validateUpload(array $file, array $allowedExts, int $maxSizeMB = 10): array {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Lỗi upload file (code: {$file['error']}).";
        return $errors;
    }

    // Check file size
    $maxBytes = $maxSizeMB * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        $errors[] = "File quá lớn. Tối đa {$maxSizeMB}MB.";
    }

    // Check extension from actual filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        $errors[] = "Định dạng file không được phép. Chỉ chấp nhận: " . implode(', ', $allowedExts);
    }

    // Check actual MIME type (don't trust $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (isset($allowed_mimes[$ext]) && $mime !== $allowed_mimes[$ext]) {
        $errors[] = "Nội dung file không khớp với định dạng.";
    }

    return $errors;
}

function safeFilename(string $originalName): string {
    $ext      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $basename = pathinfo($originalName, PATHINFO_FILENAME);
    // Remove dangerous characters, keep only alphanumeric + dashes
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
    $safe = substr($safe, 0, 60);
    return time() . '_' . $safe . '.' . $ext;
}
