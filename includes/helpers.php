<?php

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}


function sanitizeString(string $input, int $maxLen = 255): string {
    $input = trim($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return substr($input, 0, $maxLen);
}


function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


function validatePhone(string $phone): bool {
    return preg_match('/^[0-9\+\-\s]{8,15}$/', $phone);
}


function validatePassword(string $pass): ?string {
    if (strlen($pass) < 8)                     return "Mật khẩu tối thiểu 8 ký tự.";
    if (!preg_match('/[A-Z]/', $pass))         return "Cần ít nhất 1 chữ hoa.";
    if (!preg_match('/[a-z]/', $pass))         return "Cần ít nhất 1 chữ thường.";
    if (!preg_match('/[0-9]/', $pass))         return "Cần ít nhất 1 chữ số.";
    if (!preg_match('/[^A-Za-z0-9]/', $pass))  return "Cần ít nhất 1 ký tự đặc biệt.";
    return null;
}
