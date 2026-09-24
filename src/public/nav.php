<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="report_lost.php">Report Lost Item</a>
    <a href="report_found.php">Report Found Item</a>
    <a href="browse.php">Browse Found Items</a>
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="admin.php">Admin Panel</a>
    <?php endif; ?>
        <span style="margin-left:auto; display:flex; align-items:center; gap:10px; color:var(--ink); font-weight:600; font-size:14px;">
        <span style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#f472b6,var(--primary));color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;">
            <?= strtoupper(substr($_SESSION['name'] ?? '?', 0, 1)) ?>
        </span>
        <?= htmlentities($_SESSION['name'] ?? '') ?> | <a href="logout.php">Log out</a>
    </span>
</nav>
