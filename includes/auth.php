<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/security.php';

function requireLogin() {
    checkSessionTimeout(); // Check session timeout
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }
}

function requireTeacher() {
    requireLogin();
    if ($_SESSION['role'] !== 'teacher') {
        header("Location: /index.php");
        exit();
    }
}


function canEditMessage(int $messageId, int $userId, mysqli $conn): bool {
    $stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ?");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && (int)$row['sender_id'] === $userId;
}


function canDeleteStudent(int $studentId, mysqli $conn): bool {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && $row['role'] === 'student';
}
?>