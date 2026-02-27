<?php


function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_generate() . '">';
}

function csrf_verify(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Yêu cầu không hợp lệ (CSRF).');
    }
    // Regenerate after use
    unset($_SESSION['csrf_token']);
}
