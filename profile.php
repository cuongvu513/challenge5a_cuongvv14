<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$profileId = intval($_GET['id'] ?? $_SESSION['user_id']);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $profileId);
$stmt->execute();
$r = $stmt->get_result();
if ($r->num_rows === 0) { header("Location: index.php"); exit(); }
$profileUser = $r->fetch_assoc();

// Flash messages
$flash = getFlash();

// SEND MESSAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    csrf_verify();
    $content = sanitizeString($_POST['content'] ?? '', 2000);
    if ($content !== '') {
        $sid = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $sid, $profileId, $content);
        $stmt->execute();
        setFlash('success', 'Đã gửi tin nhắn!');
        header("Location: profile.php?id=$profileId");
        exit();
    }
}

// EDIT MESSAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_message'])) {
    csrf_verify();
    $msgId   = intval($_POST['msg_id']);
    $content = sanitizeString($_POST['content'] ?? '', 2000);
    $sid     = $_SESSION['user_id'];

    if (!canEditMessage($msgId, $sid, $conn)) {
        http_response_code(403);
        die('Không có quyền thực hiện hành động này.');
    }

    $stmt = $conn->prepare("UPDATE messages SET content=? WHERE id=? AND sender_id=?");
    $stmt->bind_param("sii", $content, $msgId, $sid);
    $stmt->execute();
    setFlash('success', 'Đã cập nhật tin nhắn!');
    header("Location: profile.php?id=$profileId");
    exit();
}

// DELETE MESSAGE
if (isset($_GET['delete_msg'])) {
    $msgId = intval($_GET['delete_msg']);
    $sid   = $_SESSION['user_id'];

    if (!canEditMessage($msgId, $sid, $conn)) {
        http_response_code(403);
        die('Không có quyền thực hiện hành động này.');
    }

    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $msgId, $sid);
    $stmt->execute();
    setFlash('success', 'Đã xóa tin nhắn.');
    header("Location: profile.php?id=$profileId");
    exit();
}

// Get message being edited
$editMsg = null;
if (isset($_GET['edit_msg'])) {
    $msgId = intval($_GET['edit_msg']);
    $sid   = $_SESSION['user_id'];
    $stmt  = $conn->prepare("SELECT * FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $msgId, $sid);
    $stmt->execute();
    $editMsg = $stmt->get_result()->fetch_assoc();
}

// Get all messages on this profile (only visible to sender and receiver)
$currentUserId = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT m.*, u.full_name as sender_name, u.avatar as sender_avatar
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.receiver_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
    ORDER BY m.created_at DESC
");
$stmt->bind_param("iii", $profileId, $currentUserId, $currentUserId);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang ca nhan - <?= e($profileUser['full_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <!-- PROFILE HERO -->
    <div class="profile-hero">
        <div class="profile-avatar-wrap">
            <img src="uploads/avatars/<?= e($profileUser['avatar']) ?>"
                 onerror="this.src='uploads/avatars/default.png'"
                 class="profile-avatar" alt="">
        </div>
        <div class="profile-meta">
            <h2><?= e($profileUser['full_name']) ?></h2>
            <span class="badge <?= $profileUser['role'] === 'teacher' ? 'badge-teacher' : 'badge-student' ?>">
                <?= $profileUser['role'] === 'teacher' ? 'Giáo viên' : 'Sinh viên' ?>
            </span>
            <div class="profile-info-grid">
                <div class="profile-info-item">
                    <div class="label">Email</div>
                    <div class="value"><?= e($profileUser['email'] ?: 'Chưa cập nhật') ?></div>
                </div>
                <div class="profile-info-item">
                    <div class="label">Số điện thoại</div>
                    <div class="value"><?= e($profileUser['phone'] ?: 'Chưa cập nhật') ?></div>
                </div>
                <div class="profile-info-item">
                    <div class="label">Username</div>
                    <div class="value"><?= e($profileUser['username']) ?></div>
                </div>
            </div>
            <?php if ($profileId === $_SESSION['user_id']): ?>
                <div style="margin-top: var(--space-4);">
                    <a href="edit_profile.php" class="btn btn-secondary">Chỉnh sửa hồ sơ</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MESSAGES -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tin nhắn</h3>
        </div>

        <!-- SEND / EDIT FORM -->
        <form method="POST" style="margin-bottom: var(--space-5);">
            <?= csrf_field() ?>
            <input type="hidden" name="msg_id" value="<?= (int)($editMsg['id'] ?? 0) ?>">
            <div class="form-group">
                <label class="form-label"><?= $editMsg ? 'Sửa tin nhắn' : 'Để lại tin nhắn cho ' . e($profileUser['full_name']) ?></label>
                <textarea name="content" class="form-control" rows="3" placeholder="Nhập nội dung..."><?= e($editMsg['content'] ?? '') ?></textarea>
            </div>
            <?php if ($editMsg): ?>
                <button type="submit" name="edit_message" class="btn fbtn-primary">Lưu</button>
                <a href="profile.php?id=<?= $profileId ?>" class="btn btn-ghost" style="margin-left: var(--space-2);">Hủy</a>
            <?php else: ?>
                <button type="submit" name="send_message" class="btn btn-primary">Gửi</button>
            <?php endif; ?>
        </form>

        <!-- MESSAGE LIST -->
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <div class="empty-title">Chưa có tin nhắn nào</div>
                <div class="empty-desc">Hãy là để lại lời nhắn đầu tiên.</div>
            </div>
        <?php else: ?>
        <div class="message-thread">
            <?php foreach ($messages as $msg): ?>
            <div class="message-item">
                <div class="message-header">
                    <span class="message-sender">
                        <img src="uploads/avatars/<?= e($msg['sender_avatar']) ?>"
                             onerror="this.src='uploads/avatars/default.png'"
                             class="message-sender-avatar" alt="">
                        <?= e($msg['sender_name']) ?>
                    </span>
                    <span class="message-time"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                </div>
                <div class="message-body"><?= nl2br(e($msg['content'])) ?></div>
                <?php if ((int)$msg['sender_id'] === $_SESSION['user_id']): ?>
                <div class="message-actions">
                    <a href="?id=<?= $profileId ?>&edit_msg=<?= (int)$msg['id'] ?>" class="btn btn-ghost btn-sm">Sửa</a>
                    <a href="#" onclick="confirmDelete('?id=<?= $profileId ?>&delete_msg=<?= (int)$msg['id'] ?>', 'Xóa tin nhắn này?'); return false;" class="btn btn-danger btn-sm">Xóa</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>