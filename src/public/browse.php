<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

$conn = get_db_connection();
$result = mysqli_query($conn, "SELECT items.*, users.name AS finder_name FROM items
    JOIN users ON items.reported_by = users.user_id
    WHERE items.type = 'found' AND items.status = 'open'
    ORDER BY items.created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Browse Found Items — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Found items</h1>
    <?php while ($item = mysqli_fetch_assoc($result)): ?>
        <div class="item-card">
            <strong><?= htmlentities($item['category']) ?></strong> — <?= htmlentities($item['description']) ?>
            <div class="meta">
                Found at: <?= htmlentities($item['location']) ?> on <?= htmlentities($item['item_date']) ?>
            </div>
            <form method="POST" action="claim.php" style="margin-top:8px;">
                <input type="hidden" name="found_item_id" value="<?= $item['item_id'] ?>">
                <button type="submit">This is mine, claim it</button>
            </form>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
