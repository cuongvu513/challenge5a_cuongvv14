<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <a href="/challenge5a/index.php" class="navbar-brand">Student Manager</a>
    <div class="navbar-center">
        <div class="navbar-links">
            <a href="/challenge5a/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Người dùng</a>
            <a href="/challenge5a/assignments.php" class="<?= $currentPage === 'assignments.php' ? 'active' : '' ?>">Bài tập</a>
            <a href="/challenge5a/challenge.php" class="<?= $currentPage === 'challenge.php' ? 'active' : '' ?>">Giải đố</a>
            <?php if ($_SESSION['role'] === 'teacher'): ?>
                <a href="/challenge5a/manage_students.php" class="<?= $currentPage === 'manage_students.php' ? 'active' : '' ?>">Quản lý SV</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="navbar-right">
        <div class="navbar-user">
            <a href="/challenge5a/profile.php?id=<?= (int)$_SESSION['user_id'] ?>"><?= e($_SESSION['full_name']) ?></a>
            <span class="badge <?= $_SESSION['role'] === 'teacher' ? 'badge-teacher' : 'badge-student' ?>"><?= $_SESSION['role'] === 'teacher' ? 'Giáo viên' : 'Sinh viên' ?></span>
        </div>
        <span class="navbar-separator">|</span>
        <a href="/challenge5a/logout.php" class="navbar-logout">Đăng xuất</a>
    </div>
</nav>