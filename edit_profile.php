<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = sanitizeString($_POST['email'] ?? '', 100);
    $phone    = sanitizeString($_POST['phone'] ?? '', 20);
    $password = $_POST['password'] ?? '';
    $avatar   = $user['avatar'];

    // Validate email
    if ($email && !validateEmail($email)) {
        $error = "Email không hợp lệ.";
    }

    // Validate phone
    if ($phone && !validatePhone($phone)) {
        $error = "Số điện thoại không hợp lệ.";
    }

    // Validate password if provided
    if ($password !== '') {
        $passError = validatePassword($password);
        if ($passError) {
            $error = $passError;
        }
    }

    // UPLOAD AVATAR from file
    if (!$error && !empty($_FILES['avatar_file']['name'])) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $uploadErrors = validateUpload($_FILES['avatar_file'], $allowedExts, 5);
        if (!empty($uploadErrors)) {
            $error = implode(' ', $uploadErrors);
        } else {
            $newName = safeFilename($_FILES['avatar_file']['name']);
            $dest    = 'uploads/avatars/' . $newName;
            if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $dest)) {
                $avatar = $newName;
            } else {
                $error = "Lỗi upload file!";
            }
        }
    }

    // UPLOAD AVATAR from URL
    if (!$error && !empty($_POST['avatar_url'])) {
        $avatarUrl = filter_var($_POST['avatar_url'], FILTER_SANITIZE_URL);
        
        if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            $error = "URL không hợp lệ.";
        } else {
            // Safe download with SSRF protection
            $result = safeDownloadUrl($avatarUrl, 5 * 1024 * 1024, 5);
            
            if (!$result['success']) {
                $error = $result['error'];
            } else {
                $imageData = $result['data'];
                
                // Verify it's an image
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($imageData);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($mimeType, $allowedMimes)) {
                    $error = "URL không phải là ảnh hợp lệ.";
                } else {
                    // Generate filename
                    $ext = match($mimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                        default => 'jpg',
                    };
                    $newName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                    $dest = 'uploads/avatars/' . $newName;
                    
                    if (file_put_contents($dest, $imageData)) {
                        $avatar = $newName;
                    } else {
                        $error = "Lỗi lưu ảnh!";
                    }
                }
            }
        }
    }

    if (!$error) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("UPDATE users SET email=?, phone=?, avatar=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $email, $phone, $avatar, $hash, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET email=?, phone=?, avatar=? WHERE id=?");
            $stmt->bind_param("sssi", $email, $phone, $avatar, $userId);
        }
        $stmt->execute();
        $_SESSION['avatar'] = $avatar;

        // Re-fetch user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        setFlash('success', 'Cập nhật thông tin thành công!');
        header('Location: profile.php?id=' . $userId);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa hồ sơ - Student Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>Chỉnh sửa hồ sơ</h1>
        <p>Cập nhật thông tin cá nhân của bạn.</p>
    </div>

    <div class="card" style="max-width:560px;">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- AVATAR PREVIEW -->
        <img id="avatarPreview"
             src="uploads/avatars/<?= e($user['avatar']) ?>"
             onerror="this.src='uploads/avatars/default.png'"
             class="profile-edit-avatar" alt="">

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- READ-ONLY FIELDS -->
            <div class="form-group">
                <label class="form-label">Tên đăng nhập <span class="form-label-sub">(không thể thay đổi)</span></label>
                <input type="text" value="<?= e($user['username']) ?>" class="form-control" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Họ tên <span class="form-label-sub">(không thể thay đổi)</span></label>
                <input type="text" value="<?= e($user['full_name']) ?>" class="form-control" disabled>
            </div>

            <!-- EDITABLE FIELDS -->
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" placeholder="email@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Số điện thoại</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>" placeholder="0123456789">
            </div>
            <div class="form-group">
                <label class="form-label">Mật khẩu mới <span class="form-label-sub">(để trống = không đổi)</span></label>
                <input type="password" name="password" class="form-control" placeholder="Tối thiểu 8 ký tự" minlength="8" autocomplete="new-password">
                <div class="form-hint">Cần có chữ hoa, chữ thường, số và ký tự đặc biệt.</div>
            </div>

            <div class="section-sep"></div>

            <!-- AVATAR UPLOAD -->
            <div class="form-group">
                <label class="form-label">Avatar - Upload từ file</label>
                <input type="file" name="avatar_file" id="avatar_file" class="form-control" accept="image/*" onchange="previewAvatarFromFile(this)">
                <div class="form-hint">Chấp nhận: JPG, PNG, GIF, WebP. Tối đa 5MB.</div>
            </div>

            <div style="text-align: center; margin: var(--space-4) 0; color: var(--color-text-muted); font-size: var(--text-sm);">
                — hoặc —
            </div>

            <div class="form-group">
                <label class="form-label">Avatar - Nhập URL ảnh</label>
                <input type="url" name="avatar_url" id="avatar_url" class="form-control" placeholder="https://example.com/avatar.jpg" onchange="previewAvatarFromUrl(this.value)">
                <div class="form-hint">Nhập đường dẫn URL đến ảnh avatar của bạn.</div>
            </div>

            <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                <a href="profile.php?id=<?= $userId ?>" class="btn btn-ghost">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
function previewAvatarFromFile(input) {
    // Clear URL input when file is selected
    document.getElementById('avatar_url').value = '';
    
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { 
            document.getElementById('avatarPreview').src = e.target.result; 
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewAvatarFromUrl(url) {
    // Clear file input when URL is entered
    document.getElementById('avatar_file').value = '';
    
    if (url && url.trim() !== '') {
        document.getElementById('avatarPreview').src = url;
    }
}
</script>
</body>
</html>