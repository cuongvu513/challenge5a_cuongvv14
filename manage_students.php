<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';
requireTeacher();

$flash = getFlash();
$error = '';

// DELETE student
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (!canDeleteStudent($id, $conn)) {
        http_response_code(403);
        die('Không có quyền thực hiện hành động này.');
    }
    
    // Xóa dữ liệu liên quan trước
    $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    
    $stmt = $conn->prepare("DELETE FROM submissions WHERE student_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $stmt = $conn->prepare("DELETE FROM challenge_answers WHERE student_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Xóa user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    setFlash('success', 'Đã xóa sinh viên thành công!');
    header("Location: manage_students.php");
    exit();
}

// ADD or EDIT student
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $id       = intval($_POST['id'] ?? 0);
    $username = sanitizeString($_POST['username'] ?? '', 50);
    $fullname = sanitizeString($_POST['full_name'] ?? '', 100);
    $email    = sanitizeString($_POST['email'] ?? '', 100);
    $phone    = sanitizeString($_POST['phone'] ?? '', 20);
    $password = $_POST['password'] ?? '';

    // Validate
    if ($email && !validateEmail($email)) {
        $error = "Email không hợp lệ.";
    }
    if ($phone && !validatePhone($phone)) {
        $error = "Số điện thoại không hợp lệ.";
    }

    if (!$error && $id > 0) {
        // EDIT
        if ($password !== '') {
            $passError = validatePassword($password);
            if ($passError) { $error = $passError; }
            else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, phone=?, password=? WHERE id=? AND role='student'");
                $stmt->bind_param("sssssi", $username, $fullname, $email, $phone, $hash, $id);
                $stmt->execute();
                setFlash('success', 'Cập nhật sinh viên thành công!');
                header("Location: manage_students.php");
                exit();
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, phone=? WHERE id=? AND role='student'");
            $stmt->bind_param("ssssi", $username, $fullname, $email, $phone, $id);
            $stmt->execute();
            setFlash('success', 'Cập nhật sinh viên thành công!');
            header("Location: manage_students.php");
            exit();
        }
    } elseif (!$error) {
        // ADD
        if ($password === '') {
            $error = "Vui lòng nhập mật khẩu!";
        } else {
            $passError = validatePassword($password);
            if ($passError) { $error = $passError; }
            else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, 'student')");
                $stmt->bind_param("sssss", $username, $hash, $fullname, $email, $phone);
                if ($stmt->execute()) {
                    setFlash('success', 'Thêm sinh viên thành công!');
                    header("Location: manage_students.php");
                    exit();
                } else {
                    $error = "Username đã tồn tại!";
                }
            }
        }
    }
}

// Get student being edited
$editUser = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
}

// Pagination
$perPage = 12;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$total   = $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetch_row()[0];
$pages   = (int) ceil($total / $perPage);

$stmt = $conn->prepare("SELECT * FROM users WHERE role='student' ORDER BY full_name LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sinh viên - Student Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>Quản lý sinh viên</h1>
        <p>Thêm, sửa, xóa thông tin sinh viên trong hệ thống.</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= $editUser ? 'Sửa sinh viên' : 'Thêm sinh viên mới' ?></h3>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)($editUser['id'] ?? 0) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tên đăng nhập</label>
                    <input type="text" name="username" class="form-control" required value="<?= e($editUser['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Họ tên</label>
                    <input type="text" name="full_name" class="form-control" required value="<?= e($editUser['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($editUser['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($editUser['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Mật khẩu <?= $editUser ? '(để trống = không đổi)' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div style="display: flex; gap: var(--space-3);">
                <button type="submit" class="btn btn-primary"><?= $editUser ? 'Lưu thay đổi' : 'Thêm sinh viên' ?></button>
                <?php if ($editUser): ?>
                    <a href="manage_students.php" class="btn btn-ghost">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- STUDENT LIST -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Danh sách sinh viên</h3>
            <span class="card-subtitle" style="margin:0;"><?= (int)$total ?> người</span>
        </div>

        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-title">Chưa có sinh viên nào</div>
                <div class="empty-desc">Sử dụng form phía trên để thêm sinh viên mới.</div>
            </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Username</th><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= e($s['username']) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:var(--space-2);">
                                <img src="uploads/avatars/<?= e($s['avatar']) ?>"
                                     onerror="this.src='uploads/avatars/default.png'"
                                     style="width:28px;height:28px;border-radius:var(--radius-sm);object-fit:cover;border:1px solid var(--color-border);">
                                <?= e($s['full_name']) ?>
                            </div>
                        </td>
                        <td><?= e($s['email']) ?></td>
                        <td><?= e($s['phone']) ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm">Sửa</a>
                                <a href="#" onclick="confirmDelete('?delete=<?= (int)$s['id'] ?>', 'Xóa sinh viên <?= e($s['full_name']) ?>?'); return false;" class="btn btn-danger btn-sm">Xóa</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>