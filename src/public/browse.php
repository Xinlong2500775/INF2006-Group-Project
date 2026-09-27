<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

$conn = get_db_connection();

$category = trim($_GET['category'] ?? '');
$location = trim($_GET['location'] ?? '');
$date = trim($_GET['date'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');

$where = ["type = 'found'", "status = 'open'"];
$params = [];
$types = '';

if ($category !== '') {
    $where[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}
if ($location !== '') {
    $where[] = "location LIKE ?";
    $params[] = '%' . $location . '%';
    $types .= 's';
}
if ($date !== '') {
    $where[] = "item_date = ?";
    $params[] = $date;
    $types .= 's';
}
if ($keyword !== '') {
    $where[] = "description LIKE ?";
    $params[] = '%' . $keyword . '%';
    $types .= 's';
}

$sql = "SELECT item_id, category, description, location, item_date, created_at
        FROM items
        WHERE " . implode(' AND ', $where) . "
        ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

$has_filters = $category || $location || $date || $keyword;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Browse Found Items — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="wrap">
    <h1>Found items</h1>
    <p style="color:var(--ink-soft);">Everything Security currently has on hand.</p>

    <form method="GET" class="item-card" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; margin-bottom:24px;">
        <div style="flex:1; min-width:160px;">
            <label style="margin-top:0;">Category</label>
            <select name="category">
                <option value="">All categories</option>
                <?php foreach (['Bottle/Container','Electronics','Bag','ID Card/Wallet','Clothing','Stationery/Books','Other'] as $c): ?>
                    <option value="<?= htmlentities($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= htmlentities($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1; min-width:160px;">
            <label style="margin-top:0;">Location</label>
            <input type="text" name="location" value="<?= htmlentities($location) ?>" placeholder="e.g. W3">
        </div>
        <div style="flex:1; min-width:160px;">
            <label style="margin-top:0;">Date found</label>
            <input type="date" name="date" value="<?= htmlentities($date) ?>">
        </div>
        <div style="flex:2; min-width:200px;">
            <label style="margin-top:0;">Keyword</label>
            <input type="text" name="keyword" value="<?= htmlentities($keyword) ?>" placeholder="Search descriptions...">
        </div>
        <div style="display:flex; gap:10px;">
            <button type="submit" style="margin-top:0;">Filter</button>
            <?php if ($has_filters): ?>
                <a href="browse.php" class="btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center; margin-top:0;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($items)): ?>
        <div class="item-card" style="text-align:center; padding:40px 28px;">
            <strong>No found items match your search</strong>
            <div class="meta">Try clearing filters, or check back later — new items are reported all the time.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>
        <div class="item-card">
            <span class="badge badge-found">FOUND</span>
            <strong><?= htmlentities($item['category']) ?></strong> — <?= htmlentities($item['description']) ?>
            <div class="meta">
                Found at: <?= htmlentities($item['location']) ?> on <?= htmlentities($item['item_date']) ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="item-card" style="text-align:center; padding:28px; margin-top:24px; background:var(--primary-soft); border:none;">
        <strong>Think one of these is yours?</strong>
        <div class="meta" style="margin-bottom:14px;">Report it as lost so we can match it to your description and verify it properly.</div>
        <a href="report_lost.php" class="btn-primary" style="text-decoration:none; display:inline-block;">Report a lost item</a>
    </div>
</div>
</body>
</html>