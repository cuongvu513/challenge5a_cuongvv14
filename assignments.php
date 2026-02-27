<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId    = $_SESSION['user_id'];
$isTeacher = $_SESSION['role'] === 'teacher';
$flash     = getFlash();
$error     = '';

// TEACHER: Upload new assignment
if ($isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_assignment'])) {
    csrf_verify();
    $title = sanitizeString($_POST['title'] ?? '', 200);
    $desc  = sanitizeString($_POST['description'] ?? '', 2000);

    if (!empty($_FILES['assignment_file']['name'])) {
        $allowedExts = ['pdf', 'txt', 'docx', 'jpg', 'jpeg', 'png'];
        $uploadErrors = validateUpload($_FILES['assignment_file'], $allowedExts, 10);
        if (!empty($uploadErrors)) {
            $error = implode(' ', $uploadErrors);
        } else {
            $newName = safeFilename($_FILES['assignment_file']['name']);
            $dest    = 'uploads/assignments/' . $newName;
            if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $dest)) {
                $stmt = $conn->prepare("INSERT INTO assignments (title, description, file_path, teacher_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $title, $desc, $newName, $userId);
                $stmt->execute();
                setFlash('success', 'Đã upload bài tập mới!');
                header("Location: assignments.php");
                exit();
            } else {
                $error = "Lỗi upload file!";
            }
        }
    } else {
        $error = "Vui lòng chọn file!";
    }
}


if ($isTeacher && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT file_path FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        // Chống path traversal
        $safeFile = basename($row['file_path']);
        @unlink('uploads/assignments/' . $safeFile);
        // Xóa submissions liên quan
        $stmt = $conn->prepare("DELETE FROM submissions WHERE assignment_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        // Xóa assignment
        $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        setFlash('success', 'Đã xóa bài tập!');
    } else {
        setFlash('error', 'Không có quyền xóa bài tập này!');
    }
    header("Location: assignments.php");
    exit();
}


if (!$isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    csrf_verify();
    $assignId = intval($_POST['assignment_id']);

    if (!empty($_FILES['submission_file']['name'])) {
        $allowedExts = ['pdf', 'txt', 'docx', 'jpg', 'jpeg', 'png'];
        $uploadErrors = validateUpload($_FILES['submission_file'], $allowedExts, 10);
        if (!empty($uploadErrors)) {
            $error = implode(' ', $uploadErrors);
        } else {
            $newName = safeFilename($_FILES['submission_file']['name']);
            $dest    = 'uploads/submissions/' . $newName;
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $dest)) {
               
                $stmt = $conn->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?");
                $stmt->bind_param("ii", $assignId, $userId);
                $stmt->execute();
                $check = $stmt->get_result();

                if ($check->num_rows > 0) {
                    $stmt = $conn->prepare("UPDATE submissions SET file_path=?, submitted_at=NOW() WHERE assignment_id=? AND student_id=?");
                    $stmt->bind_param("sii", $newName, $assignId, $userId);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path) VALUES (?, ?, ?)");
                    $stmt->bind_param("iis", $assignId, $userId, $newName);
                    $stmt->execute();
                }
                setFlash('success', 'Nộp bài thành công!');
                header("Location: assignments.php");
                exit();
            } else {
                $error = "Lỗi upload file!";
            }
        }
    } else {
        $error = "Vui lòng chọn file bài làm!";
    }
}

// Get assignments list
$assignments = $conn->query("
    SELECT a.*, u.full_name as teacher_name FROM assignments a
    JOIN users u ON u.id = a.teacher_id
    ORDER BY a.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Teacher: view submissions
$viewSubmissions = null;
$viewAssignmentId = 0;
if ($isTeacher && isset($_GET['view_submissions'])) {
    $viewAssignmentId = intval($_GET['view_submissions']);
    // Kiểm tra quyền sở hữu assignment
    $stmt = $conn->prepare("SELECT teacher_id FROM assignments WHERE id = ?");
    $stmt->bind_param("i", $viewAssignmentId);
    $stmt->execute();
    $assignmentOwner = $stmt->get_result()->fetch_assoc();
    
    if ($assignmentOwner && (int)$assignmentOwner['teacher_id'] === $userId) {
        $stmt = $conn->prepare("
            SELECT s.*, u.full_name, u.username FROM submissions s
            JOIN users u ON u.id = s.student_id
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmt->bind_param("i", $viewAssignmentId);
        $stmt->execute();
        $viewSubmissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bài tập - Student Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>Bài tập</h1>
        <p>Quản lý và nộp bài tập.</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- TEACHER: UPLOAD FORM -->
    <?php if ($isTeacher): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Upload bài tập mới</h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Tiêu đề</label>
                <input type="text" name="title" class="form-control" required placeholder="VD: Bài tập Toán tuần 1">
            </div>
            <div class="form-group">
                <label class="form-label">Mô tả</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Mô tả bài tập"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">File bài tập</label>
                <input type="file" name="assignment_file" class="form-control" required>
                <div class="form-hint">Chấp nhận: PDF, TXT, DOCX, JPG, PNG. Tối đa 10MB.</div>
            </div>
            <button type="submit" name="upload_assignment" class="btn btn-primary">Upload</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ASSIGNMENT LIST -->
    <?php if (empty($assignments)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-title">Chưa có bài tập nào</div>
                <div class="empty-desc"><?= $isTeacher ? 'Hãy upload bài tập đầu tiên.' : 'Giáo viên chưa đăng bài tập nào.' ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($assignments as $a): ?>
    <div class="assignment-item" style="display:block;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-4);">
            <div class="assignment-content">
                <div class="assignment-title"><?= e($a['title']) ?></div>
                <div class="assignment-meta"><?= e($a['teacher_name']) ?> &middot; <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></div>
                <?php if ($a['description']): ?>
                    <div class="assignment-desc"><?= nl2br(e($a['description'])) ?></div>
                <?php endif; ?>
            </div>
            <div class="assignment-actions">
                <?php $safeFile = basename($a['file_path']); ?>
                <a href="uploads/assignments/<?= e($safeFile) ?>" class="btn btn-secondary btn-sm" download>Tải về</a>
                <?php if ($isTeacher && (int)$a['teacher_id'] === $userId): ?>
                    <a href="?view_submissions=<?= (int)$a['id'] ?>" class="btn btn-ghost btn-sm">Xem bài nộp</a>
                    <a href="#" onclick="confirmDelete('?delete=<?= (int)$a['id'] ?>', 'Xóa bài tập này?'); return false;" class="btn btn-danger btn-sm">Xóa</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- STUDENT: SUBMISSION -->
        <?php if (!$isTeacher): ?>
        <?php
        $stmt2 = $conn->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
        $stmt2->bind_param("ii", $a['id'], $userId);
        $stmt2->execute();
        $sub = $stmt2->get_result()->fetch_assoc();
        ?>
        <div class="submission-area">
            <?php if ($sub): ?>
                <span class="submission-status-ok">Đã nộp lúc <?= date('d/m/Y H:i', strtotime($sub['submitted_at'])) ?></span>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="submission-form">
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                <div class="form-group">
                    <label class="form-label"><?= $sub ? 'Nộp lại bài' : 'Nộp bài' ?></label>
                    <input type="file" name="submission_file" class="form-control">
                </div>
                <button type="submit" name="submit_assignment" class="btn btn-primary btn-sm">Nộp bài</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- TEACHER: VIEW SUBMISSIONS -->
    <?php if ($isTeacher && $viewSubmissions !== null): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Danh sách bài nộp</h3>
        </div>
        <?php if (empty($viewSubmissions)): ?>
            <div class="empty-state">
                <div class="empty-title">Chưa có sinh viên nào nộp bài</div>
            </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Sinh viên</th><th>Thời gian nộp</th><th>File</th></tr></thead>
                <tbody>
                <?php foreach ($viewSubmissions as $s): ?>
                <tr>
                    <td>
                        <a href="profile.php?id=<?= (int)$s['student_id'] ?>" style="color:var(--color-text-primary);font-weight:500;">
                            <?= e($s['full_name']) ?>
                        </a>
                        <span style="color:var(--color-text-muted);font-size:var(--text-xs);"> (<?= e($s['username']) ?>)</span>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:var(--text-xs);"><?= date('d/m/Y H:i', strtotime($s['submitted_at'])) ?></td>
                    <td>
                        <?php $safeFile = basename($s['file_path']); ?>
                        <a href="uploads/submissions/<?= e($safeFile) ?>" class="btn btn-secondary btn-sm" download>Tải về</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>