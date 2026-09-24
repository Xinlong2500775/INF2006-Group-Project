<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/matching.php';
require_login();

$item_id = intval($_GET['item_id'] ?? 0);
$conn = get_db_connection();

$uid = current_user_id();
$stmt = mysqli_prepare($conn, "SELECT * FROM items WHERE item_id = ? AND reported_by = ? AND type = 'lost'");
mysqli_stmt_bind_param($stmt, "ii", $item_id, $uid);
mysqli_stmt_execute($stmt);
$lost_item = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$lost_item) {
    die("Item not found.");
}

$matches = find_matches_for_lost_item($conn, $lost_item, 0.2);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Possible Matches — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Possible matches for your report</h1>
    <div class="item-card">
        <span class="badge badge-lost">YOUR REPORT</span>
        <strong><?= htmlentities($lost_item['category']) ?></strong> — <?= htmlentities($lost_item['description']) ?>
    </div>

    <h2 style="margin-top:24px; font-size:18px;">Matching found items</h2>
    <?php if (empty($matches)): ?>
        <p>No matching found items yet. Check back later, or <a href="browse.php">browse all found items</a>.</p>
    <?php endif; ?>
    <?php foreach ($matches as $m): ?>
        <div class="item-card">
            <span class="badge badge-found">FOUND</span>
            <strong><?= htmlentities($m['category']) ?></strong> — <?= htmlentities($m['description']) ?>
            <div class="meta">
                Found at: <?= htmlentities($m['location']) ?> on <?= htmlentities($m['item_date']) ?> |
                Match score: <?= round($m['score'] * 100) ?>%
            </div>
            <form method="POST" action="claim.php" style="margin-top:8px;">
                <input type="hidden" name="found_item_id" value="<?= $m['item_id'] ?>">
                <input type="hidden" name="lost_item_id" value="<?= $lost_item['item_id'] ?>">
                <button type="submit">This is mine, claim it</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>