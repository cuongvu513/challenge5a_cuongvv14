<?php

function isInternalIP(string $url): bool {
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return true; // Block invalid URLs
    }
    
    $host = $parsed['host'];
    
    // Resolve hostname to IP
    $ip = @gethostbyname($host);
    if (!$ip || $ip === $host) {
        // If resolution fails, block it
        return true;
    }
    
    // Check if it's a private/reserved IP
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // Block private IPv4 ranges
        $longIP = ip2long($ip);
        if ($longIP === false) return true;
        
        // 127.0.0.0/8 (localhost)
        if (($longIP & 0xFF000000) === 0x7F000000) return true;
        
        // 10.0.0.0/8 (private)
        if (($longIP & 0xFF000000) === 0x0A000000) return true;
        
        // 172.16.0.0/12 (private)
        if (($longIP & 0xFFF00000) === 0xAC100000) return true;
        
        // 192.168.0.0/16 (private)
        if (($longIP & 0xFFFF0000) === 0xC0A80000) return true;
        
        // 169.254.0.0/16 (link-local)
        if (($longIP & 0xFFFF0000) === 0xA9FE0000) return true;
        
        // 0.0.0.0/8 (current network)
        if (($longIP & 0xFF000000) === 0x00000000) return true;
        
        // 224.0.0.0/4 (multicast)
        if (($longIP & 0xF0000000) === 0xE0000000) return true;
        
        // 240.0.0.0/4 (reserved)
        if (($longIP & 0xF0000000) === 0xF0000000) return true;
    }
    
    // Also check with PHP's built-in filter (backup)
    $validIP = filter_var($ip, FILTER_VALIDATE_IP, 
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    
    return $validIP === false;
}


function safeDownloadUrl(string $url, int $maxSize = 5242880, int $timeout = 5): array {
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'error' => 'URL không hợp lệ.'];
    }
    
    // Check protocol whitelist
    $scheme = parse_url($url, PHP_SCHEME);
    if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
        return ['success' => false, 'error' => 'Chỉ chấp nhận HTTP hoặc HTTPS.'];
    }
    
    // Check for internal/private IPs (SSRF protection)
    if (isInternalIP($url)) {
        return ['success' => false, 'error' => 'URL không được phép (internal/private IP).'];
    }
    
    // Use cURL for safe download
    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'error' => 'Không thể khởi tạo request.'];
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,  // Disable redirects (SSRF)
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'StudentManager/1.0',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => 0,  // No redirects
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    
    if ($data === false) {
        return ['success' => false, 'error' => 'Không thể tải từ URL: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'Lỗi HTTP: ' . $httpCode];
    }
    
    // Check file size
    $size = strlen($data);
    if ($size > $maxSize) {
        return ['success' => false, 'error' => 'File quá lớn. Tối đa ' . round($maxSize/1048576, 1) . 'MB.'];
    }
    
    return ['success' => true, 'data' => $data, 'size' => $size];
}


function initSecureSession(): void {
    // Session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,  // Session cookie (expires when browser closes)
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',  // HTTPS only if available
        'httponly' => true,  // Prevent JavaScript access (XSS protection)
        'samesite' => 'Strict'  // CSRF protection
    ]);
}


function checkSessionTimeout(int $timeout = 1800): void {
    if (!isset($_SESSION['user_id'])) {
        return; // Not logged in
    }
    
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $currentTime = time();
    
    if ($currentTime - $lastActivity > $timeout) {
        // Session expired
        session_unset();
        session_destroy();
        header("Location: /login.php?timeout=1");
        exit();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = $currentTime;
}
