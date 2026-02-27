<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId    = $_SESSION['user_id'];
$isTeacher = $_SESSION['role'] === 'teacher';
$flash     = getFlash();
$error     = '';
$success   = '';

// TEACHER: Create new challenge
if ($isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_challenge'])) {
    csrf_verify();
    $hint = sanitizeString($_POST['hint'] ?? '', 2000);

    if (!empty($_FILES['challenge_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['challenge_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'txt') {
            $error = "Chỉ chấp nhận file .txt!";
        } else {
            $uploadErrors = validateUpload($_FILES['challenge_file'], ['txt'], 5);
            if (!empty($uploadErrors)) {
                $error = implode(' ', $uploadErrors);
            } else {
                // Lấy tên file gốc (đáp án)
                $originalName = basename($_FILES['challenge_file']['name']);
                $filename = pathinfo($originalName, PATHINFO_FILENAME);
                
                // Sanitize tên file - chỉ giữ chữ cái, số, khoảng trắng, gạch ngang/dưới
                $safeName = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $filename);
                $safeName = preg_replace('/\s+/', ' ', trim($safeName));
                
                if (empty($safeName)) {
                    $error = "Tên file không hợp lệ!";
                } else {
                    // Tạo thư mục challenges nếu chưa có
                    $challengeDir = 'uploads/challenges/';
                    if (!is_dir($challengeDir)) {
                        mkdir($challengeDir, 0755, true);
                    }
                    
                    // Đáp án = tên file gốc (không có đuôi .txt)
                    $answer = $safeName;
                    
                    // Hash đáp án bằng SHA-256 
                    $answerHash = hash('sha256', strtolower($answer));
                    
                    // Lưu file với tên khác
                    $uuid = uniqid('challenge_', true);
                    $storedFilename = $uuid . '.txt';
                    $dest = $challengeDir . $storedFilename;
                    
                    if (move_uploaded_file($_FILES['challenge_file']['tmp_name'], $dest)) {
                        // Lưu vào DB:
                        // - answer_hash: hash SHA-256 của đáp án 
                        // - file_path: tên file UUID random 
                        $stmt = $conn->prepare("INSERT INTO challenges (hint, answer_hash, file_path, teacher_id) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("sssi", $hint, $answerHash, $storedFilename, $userId);
                        $stmt->execute();
                        
                        setFlash('success', 'Đã tạo challenge thành công! Đáp án là: <strong>' . htmlspecialchars($answer) . '</strong> (chỉ hiển thị 1 lần, hãy ghi nhớ!)');
                        header("Location: challenge.php");
                        exit();
                    } else {
                        $error = "Lỗi upload file!";
                    }
                }
            }
        }
    } else {
        $error = "Vui lòng chọn file .txt!";
    }
}

// TEACHER: Delete challenge (chỉ của mình)
if ($isTeacher && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT file_path FROM challenges WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $safeFile = basename($row['file_path']);
        @unlink('uploads/challenges/' . $safeFile);
        $stmt = $conn->prepare("DELETE FROM challenge_answers WHERE challenge_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM challenges WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        setFlash('success', 'Đã xóa challenge!');
    } else {
        setFlash('error', 'Không có quyền xóa challenge này!');
    }
    header("Location: challenge.php");
    exit();
}

// STUDENT: Submit answer
$showContent = false;
$fileContent = '';
if (!$isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    csrf_verify();
    $challengeId = intval($_POST['challenge_id']);
    $userAnswer  = trim($_POST['answer'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM challenges WHERE id = ?");
    $stmt->bind_param("i", $challengeId);
    $stmt->execute();
    $challenge = $stmt->get_result()->fetch_assoc();

    if ($challenge) {
        // Hash đáp án mà sinh viên nhập
        $userAnswerHash = hash('sha256', strtolower($userAnswer));
        
        // Lấy file thực tế để đọc nội dung
        $safeFile = basename($challenge['file_path']);
        
        // So sánh hash của đáp án (KHÔNG lưu đáp án gốc trong DB)
        if ($userAnswerHash === $challenge['answer_hash']) {
            // Đọc nội dung file
            $fileContent = @file_get_contents('uploads/challenges/' . $safeFile);
            
            if ($fileContent !== false) {
                // Lưu vào DB để tracking (optional)
                $stmt = $conn->prepare("SELECT id FROM challenge_answers WHERE challenge_id = ? AND student_id = ?");
                $stmt->bind_param("ii", $challengeId, $userId);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    $stmt = $conn->prepare("INSERT INTO challenge_answers (challenge_id, student_id, answer_file) VALUES (?, ?, ?)");
                    $emptyFile = '';
                    $stmt->bind_param("iis", $challengeId, $userId, $emptyFile);
                    $stmt->execute();
                }
                
                $showContent = true;
                $success = "Chính xác! Đây là nội dung:";
            } else {
                $error = "Không thể đọc file!";
            }
        } else {
            $error = "Sai rồi! Hãy thử lại.";
        }
    }
}

// Get all challenges (danh sách)
$challenges = $conn->query("
    SELECT c.*, u.full_name as teacher_name FROM challenges c
    JOIN users u ON u.id = c.teacher_id
    ORDER BY c.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Xem chi tiết 1 challenge
$viewChallenge = null;
$solvedFileContent = '';
if (isset($_GET['view'])) {
    $viewId = intval($_GET['view']);
    $stmt = $conn->prepare("SELECT c.*, u.full_name as teacher_name FROM challenges c 
                            JOIN users u ON u.id = c.teacher_id 
                            WHERE c.id = ?");
    $stmt->bind_param("i", $viewId);
    $stmt->execute();
    $viewChallenge = $stmt->get_result()->fetch_assoc();
    
    // Kiểm tra xem sinh viên đã giải chưa
    if (!$isTeacher && $viewChallenge) {
        $stmt = $conn->prepare("SELECT * FROM challenge_answers WHERE challenge_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $viewId, $userId);
        $stmt->execute();
        $solvedCheck = $stmt->get_result()->fetch_assoc();
        if ($solvedCheck) {
            $viewChallenge['already_solved'] = true;
            // Đọc lại nội dung file để hiển thị
            $safeFile = basename($viewChallenge['file_path']);
            $solvedFileContent = @file_get_contents('uploads/challenges/' . $safeFile) ?: '';
        }
    }
}

// Teacher: view who solved
$viewAnswers = null;
if ($isTeacher && isset($_GET['view_answers'])) {
    $cid = intval($_GET['view_answers']);
    $stmt = $conn->prepare("SELECT teacher_id FROM challenges WHERE id = ?");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $challengeOwner = $stmt->get_result()->fetch_assoc();
    
    if ($challengeOwner && (int)$challengeOwner['teacher_id'] === $userId) {
        $stmt = $conn->prepare("
            SELECT ca.*, u.full_name, u.username FROM challenge_answers ca
            JOIN users u ON u.id = ca.student_id
            WHERE ca.challenge_id = ?
            ORDER BY ca.answered_at ASC
        ");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $viewAnswers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giải đố - Student Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <div class="page-header">
        <h1>Giải đố</h1>
        <p>Thử thách kiến thức của bạn.</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= $flash['message'] ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- TEACHER: CREATE CHALLENGE -->
    <?php if ($isTeacher && !$viewChallenge && $viewAnswers === null): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tạo challenge mới</h3>
        </div>
        <div class="notice">
            <strong>Lưu ý:</strong> Upload file <code>.txt</code> có nội dung bài thơ/văn.
            Tên file viết không dấu, các từ cách nhau bởi khoảng trắng (VD: <code>bai tho mua thu.txt</code>).<br>
            <strong style="color:var(--color-success);">Đáp án = Tên file không có đuôi:</strong> <code>bai tho mua thu</code><br>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Gợi ý (hint) cho sinh viên</label>
                <textarea name="hint" class="form-control" rows="3" required placeholder="VD: Bài thơ nổi tiếng về mùa thu của tác giả..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">File .txt (nội dung bài thơ/văn)</label>
                <input type="file" name="challenge_file" class="form-control" accept=".txt" required>
            </div>
            <button type="submit" name="create_challenge" class="btn btn-primary">Tạo challenge</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- VIEW CHALLENGE DETAIL (Student clicks on a challenge) -->
    <?php if ($viewChallenge && !$isTeacher): ?>
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 class="card-title">Challenge #<?= (int)$viewChallenge['id'] ?></h3>
            <a href="challenge.php" class="btn btn-ghost btn-sm">← Quay lại danh sách</a>
        </div>
        
        <div style="margin-bottom:var(--space-4);">
            <div style="color:var(--color-text-muted);font-size:var(--text-sm);">
                Giáo viên: <?= e($viewChallenge['teacher_name']) ?> | Ngày tạo: <?= date('d/m/Y', strtotime($viewChallenge['created_at'])) ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Gợi ý:</label>
            <div class="challenge-hint" style="background:var(--color-bg-secondary);padding:var(--space-4);border-radius:var(--radius-md);">
                <?= nl2br(e($viewChallenge['hint'])) ?>
            </div>
        </div>

        <?php if ($showContent && isset($_POST['challenge_id']) && $_POST['challenge_id'] == $viewChallenge['id']): ?>
            <!-- Vừa mới giải đúng -->
            <div class="alert alert-success">
                <strong>Chính xác!</strong> Bạn đã giải đúng challenge này.
            </div>
            <div style="margin-top:var(--space-4);">
                <button type="button" class="btn btn-secondary" onclick="toggleContent()">Ẩn/Hiện nội dung</button>
                <div id="fileContent" class="poem-content" style="display:block;margin-top:var(--space-3);background:var(--color-bg-secondary);padding:var(--space-4);border-radius:var(--radius-md);white-space:pre-wrap;font-family:var(--font-mono);font-size:var(--text-sm);"><?= nl2br(e($fileContent)) ?></div>
            </div>
        <?php elseif (isset($viewChallenge['already_solved']) && $viewChallenge['already_solved']): ?>
            <!-- Đã giải trước đó -->
            <div class="alert alert-success">
                <strong>✓ Đã hoàn thành!</strong> Bạn đã giải đúng challenge này trước đó.
            </div>
            <div style="margin-top:var(--space-4);">
                <button type="button" class="btn btn-secondary" onclick="toggleContent()">Ẩn/Hiện nội dung</button>
                <div id="fileContent" class="poem-content" style="display:none;margin-top:var(--space-3);background:var(--color-bg-secondary);padding:var(--space-4);border-radius:var(--radius-md);white-space:pre-wrap;font-family:var(--font-mono);font-size:var(--text-sm);"><?= nl2br(e($solvedFileContent)) ?></div>
            </div>
        <?php else: ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="challenge_id" value="<?= (int)$viewChallenge['id'] ?>">
                <div class="form-group">
                    <label class="form-label">Nhập đáp án của bạn:</label>
                    <input type="text" name="answer" class="form-control" placeholder="Nhập đáp án..." required autofocus>
                </div>
                <button type="submit" name="submit_answer" class="btn btn-primary">Nộp đáp án</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- CHALLENGES LIST (Compact view) -->
    <?php if (!$viewChallenge && $viewAnswers === null): ?>
        <?php if (empty($challenges)): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-title">Chưa có challenge nào</div>
                    <div class="empty-desc"><?= $isTeacher ? 'Hãy tạo challenge đầu tiên.' : 'Giáo viên chưa tạo challenge nào.' ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Danh sách Challenges</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Giáo viên</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($challenges as $c): ?>
                            <?php
                            // Check if student solved
                            $solved = false;
                            if (!$isTeacher) {
                                $stmt = $conn->prepare("SELECT id FROM challenge_answers WHERE challenge_id = ? AND student_id = ?");
                                $stmt->bind_param("ii", $c['id'], $userId);
                                $stmt->execute();
                                $solved = $stmt->get_result()->num_rows > 0;
                            }
                            ?>
                            <tr>
                                <td style="font-weight:600;">Challenge #<?= (int)$c['id'] ?></td>
                                <td><?= e($c['teacher_name']) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <?php if ($isTeacher): ?>
                                        <?php if ((int)$c['teacher_id'] === $userId): ?>
                                            <a href="?view_answers=<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm">Xem danh sách</a>
                                            <a href="#" onclick="confirmDelete('?delete=<?= (int)$c['id'] ?>', 'Xóa challenge này?'); return false;" class="btn btn-danger btn-sm">Xóa</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="?view=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">
                                            <?= $solved ? '✓ Đã giải' : 'Xem challenge' ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TEACHER: VIEW ANSWERS -->
    <?php if ($isTeacher && $viewAnswers !== null): ?>
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 class="card-title">Sinh viên đã giải challenge</h3>
            <a href="challenge.php" class="btn btn-ghost btn-sm">← Quay lại</a>
        </div>
        <?php if (empty($viewAnswers)): ?>
            <div class="empty-state">
                <div class="empty-title">Chưa có sinh viên nào giải được</div>
            </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Sinh viên</th><th>Thời gian giải</th></tr></thead>
                <tbody>
                <?php foreach ($viewAnswers as $ans): ?>
                <tr>
                    <td>
                        <a href="profile.php?id=<?= (int)$ans['student_id'] ?>" style="color:var(--color-text-primary);font-weight:500;">
                            <?= e($ans['full_name']) ?>
                        </a>
                        <span style="color:var(--color-text-muted);font-size:var(--text-xs);"> (<?= e($ans['username']) ?>)</span>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:var(--text-xs);"><?= date('d/m/Y H:i', strtotime($ans['answered_at'])) ?></td>
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
<script>
function toggleContent() {
    const content = document.getElementById('fileContent');
    if (content.style.display === 'none') {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
    }
}
</script>
</body>
</html>