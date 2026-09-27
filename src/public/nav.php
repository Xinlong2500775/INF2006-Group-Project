<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<nav>
    <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="report_lost.php" class="<?= $current_page === 'report_lost.php' ? 'active' : '' ?>">Report Lost Item</a>
    <a href="report_found.php" class="<?= $current_page === 'report_found.php' ? 'active' : '' ?>">Report Found Item</a>
    <a href="browse.php" class="<?= $current_page === 'browse.php' ? 'active' : '' ?>">Browse Found Items</a>
    <a href="my_claims.php" class="<?= $current_page === 'my_claims.php' ? 'active' : '' ?>">My Claims</a>
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="admin.php" class="<?= $current_page === 'admin.php' ? 'active' : '' ?>">Admin Panel</a>
    <?php endif; ?>
    <span style="margin-left:auto; display:flex; align-items:center; gap:10px; color:var(--ink); font-weight:600; font-size:14px;">
        <span style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#f472b6,var(--primary));color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;">
            <?= strtoupper(substr($_SESSION['name'] ?? '?', 0, 1)) ?>
        </span>
        <?= htmlentities($_SESSION['name'] ?? '') ?> | <a href="logout.php">Log out</a>
    </span>
</nav>