<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$search  = sanitizeString($_GET['search'] ?? '', 100);
$perPage = 12;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

if ($search) {
    $like = "%$search%";
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE full_name LIKE ? OR username LIKE ?");
    $countStmt->bind_param("ss", $like, $like);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT * FROM users WHERE full_name LIKE ? OR username LIKE ? ORDER BY role, full_name LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $like, $like, $perPage, $offset);
} else {
    $total = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY role, full_name LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pages = (int) ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách người dùng - Student Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>Người dùng</h1>
        <p>Danh sách tất cả thành viên trong hệ thống (<?= (int)$total ?> người)</p>
    </div>

    <form method="GET" class="search-form">
        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm theo tên hoặc username..." value="<?= e($search) ?>">
        <button type="submit" class="btn btn-primary">Tìm</button>
        <?php if ($search): ?>
            <a href="index.php" class="btn btn-ghost">Xóa bộ lọc</a>
        <?php endif; ?>
    </form>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-title">Không tìm thấy kết quả</div>
            <div class="empty-desc">Thử thay đổi từ khóa tìm kiếm.</div>
        </div>
    <?php else: ?>
    <div class="user-grid">
        <?php foreach ($users as $user): ?>
        <a href="profile.php?id=<?= (int)$user['id'] ?>" class="user-card">
            <img src="uploads/avatars/<?= e($user['avatar']) ?>"
                 onerror="this.src='uploads/avatars/default.png'"
                 class="user-card-avatar" alt="">
            <div class="user-card-info">
                <div class="user-card-name"><?= e($user['full_name']) ?></div>
                <span class="badge <?= $user['role'] === 'teacher' ? 'badge-teacher' : 'badge-student' ?>"><?= $user['role'] === 'teacher' ? 'Giáo viên' : 'Sinh viên' ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>