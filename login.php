<?php
session_start();
require_once 'config/db.php';
require_once 'includes/helpers.php';
require_once 'includes/csrf.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}


$error = '';

if (isset($_GET['timeout'])) {
    $error = "Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ip = $_SERVER['REMOTE_ADDR'];
    $username = sanitizeString($_POST['username'] ?? '', 50);
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['avatar']    = $user['avatar'];
        $_SESSION['last_activity'] = time();
        header("Location: index.php");
        exit();
    } else {
        $error = "Sai tên đăng nhập hoặc mật khẩu.";
    }
    
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Student Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="login-layout">
    <div class="login-left">
        <div>
            <div class="brand-name">Student<br>Manager</div>
            <p class="tagline">Hệ thống quản lý thông tin sinh viên</p>
        </div>
    </div>
    <div class="login-right">
        <div class="login-form-wrap">
            <h2>Đăng nhập</h2>
            <p>Nhập thông tin tài khoản để tiếp tục.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">Tên đăng nhập</label>
                    <input type="text" name="username" class="form-control" required autocomplete="username" placeholder="username">
                </div>
                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password" placeholder="********">
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Đăng nhập</button>
            </form>
        </div>
    </div>
</div>
</body>

</html>
