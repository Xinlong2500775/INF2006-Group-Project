<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

$conn = get_db_connection();
$uid = current_user_id();

// Claims I submitted — waiting on someone else's item
$stmt = mysqli_prepare($conn, "
    SELECT c.claim_id, c.status, c.verification_answer, c.created_at,
           li.category AS lost_category, li.description AS lost_description,
           fi.category AS found_category, fi.description AS found_description, fi.location AS found_location
    FROM claims c
    JOIN items li ON c.lost_item_id = li.item_id
    JOIN items fi ON c.found_item_id = fi.item_id
    WHERE c.claimed_by = ?
    ORDER BY c.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$my_claims = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

// Claims others submitted on items I found
$stmt = mysqli_prepare($conn, "
    SELECT c.claim_id, c.status, c.verification_answer, c.created_at,
           fi.category AS found_category, fi.description AS found_description,
           claimant.name AS claimant_name
    FROM claims c
    JOIN items fi ON c.found_item_id = fi.item_id
    JOIN users claimant ON c.claimed_by = claimant.user_id
    WHERE fi.reported_by = ?
    ORDER BY c.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$claims_on_my_items = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

function status_badge($status) {
    $colors = [
        'pending'  => ['bg' => 'var(--orange-soft)', 'fg' => 'var(--orange)'],
        'approved' => ['bg' => 'var(--green-soft)',  'fg' => 'var(--green)'],
        'rejected' => ['bg' => 'var(--red-soft)',    'fg' => 'var(--red)'],
    ];
    $c = $colors[$status] ?? ['bg' => '#eee', 'fg' => '#555'];
    echo '<span class="badge" style="background:' . $c['bg'] . '; color:' . $c['fg'] . ';">' . strtoupper(htmlentities($status)) . '</span>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Claims — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="wrap">
    <h1>My claims</h1>
    <p style="color:var(--ink-soft);">Everything you've claimed, and everyone who's claimed your finds.</p>

    <div class="section-title" style="margin-top:28px;">Claims you've submitted</div>
    <?php if (empty($my_claims)): ?>
        <div class="item-card"><div class="meta">You haven't submitted any claims yet.</div></div>
    <?php endif; ?>
    <?php foreach ($my_claims as $c): ?>
        <div class="item-card">
            <?php status_badge($c['status']); ?>
            <strong><?= htmlentities($c['found_category']) ?></strong> — <?= htmlentities($c['found_description']) ?>
            <div class="meta">
                For your lost report: <?= htmlentities($c['lost_category']) ?> — <?= htmlentities($c['lost_description']) ?><br>
                Submitted <?= htmlentities($c['created_at']) ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="section-title" style="margin-top:32px;">Claims on items you found</div>
    <?php if (empty($claims_on_my_items)): ?>
        <div class="item-card"><div class="meta">No one has claimed anything you've found yet.</div></div>
    <?php endif; ?>
    <?php foreach ($claims_on_my_items as $c): ?>
        <div class="item-card">
            <?php status_badge($c['status']); ?>
            <strong><?= htmlentities($c['found_category']) ?></strong> — <?= htmlentities($c['found_description']) ?>
            <div class="meta">
                Claimed by <?= htmlentities($c['claimant_name']) ?> on <?= htmlentities($c['created_at']) ?>
                <?php if ($c['status'] === 'pending'): ?>
                    — <a href="admin.php">waiting on Security review</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>