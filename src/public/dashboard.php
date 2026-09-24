<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/matching.php';
require_login();

$conn = get_db_connection();
$uid = current_user_id();

$result = mysqli_query($conn, "SELECT * FROM items WHERE reported_by = $uid ORDER BY created_at DESC");
$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}
$total_reports = count($items);
$lost_count = count(array_filter($items, fn($i) => $i['type'] === 'lost'));
$found_count = count(array_filter($items, fn($i) => $i['type'] === 'found'));

// Real "possible matches" count: run the matching script against every
// open lost item this user reported, count found items scoring above 20%
// (filters out weak, coincidental overlaps like sharing just one word).
$match_count = 0;
$first_matched_item_id = null;
foreach ($items as $item) {
    if ($item['type'] === 'lost' && $item['status'] === 'open') {
        $matches = find_matches_for_lost_item($conn, $item, 0.2);
        if (count($matches) > 0 && $first_matched_item_id === null) {
            $first_matched_item_id = $item['item_id'];
        }
        $match_count += count($matches);
    }
}

// Pending claims relevant to this user: claims THEY made awaiting review,
// and claims OTHERS made on found items THEY reported.
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM claims WHERE claimed_by = ? AND status = 'pending'");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$my_pending_claims = mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM claims c JOIN items i ON c.found_item_id = i.item_id WHERE i.reported_by = ? AND c.status = 'pending'");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$claims_on_my_items = mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];

$pending_total = $my_pending_claims + $claims_on_my_items;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="wrap">
    <h1>Welcome back, <?= htmlentities($_SESSION['name']) ?></h1>
    <p>
        <?php if (isset($_GET['claimed'])): ?>
            Claim submitted — an admin will review it and confirm the handover.
        <?php else: ?>
            Here's what's happening with your reports.
        <?php endif; ?>
    </p>

    <div class="stats">
        <div class="stat">
            <div class="stat-icon" style="background:var(--primary-soft); color:var(--primary);">📋</div>
            <div class="stat-num"><?= $total_reports ?></div>
            <div class="stat-label">Total reports</div>
        </div>
        <div class="stat">
            <div class="stat-icon" style="background:var(--lost-soft); color:var(--lost);">🔍</div>
            <div class="stat-num"><?= $lost_count ?></div>
            <div class="stat-label">Lost items</div>
        </div>
        <div class="stat">
            <div class="stat-icon" style="background:var(--found-soft); color:var(--found);">📦</div>
            <div class="stat-num"><?= $found_count ?></div>
            <div class="stat-label">Found items</div>
        </div>
        <?php if ($match_count > 0 && $first_matched_item_id): ?>
        <a href="matches.php?item_id=<?= $first_matched_item_id ?>" style="text-decoration:none; color:inherit;">
        <?php endif; ?>
        <div class="stat" <?= $match_count > 0 ? 'style="cursor:pointer;"' : '' ?>>
            <div class="stat-icon" style="background:var(--orange-soft); color:var(--orange);">✨</div>
            <div class="stat-num"><?= $match_count ?></div>
            <div class="stat-label">Possible matches</div>
        </div>
        <?php if ($match_count > 0 && $first_matched_item_id): ?>
        </a>
        <?php endif; ?>
        <div class="stat">
            <div class="stat-icon" style="background:#fef2f2; color:#dc2626;">⏳</div>
            <div class="stat-num"><?= $pending_total ?></div>
            <div class="stat-label">Pending claims</div>
        </div>
    </div>

    <div class="section-title">Quick actions</div>
    <div class="actions">
        <a href="report_lost.php" class="action-card">
            <div class="action-icon" style="background:linear-gradient(135deg,#dc2626,#f87171);">🔍</div>
            <div class="action-title">Report lost item</div>
            <div class="action-desc">Let us find it for you</div>
        </a>
        <a href="report_found.php" class="action-card">
            <div class="action-icon" style="background:linear-gradient(135deg,var(--green),#34d399);">📦</div>
            <div class="action-title">Report found item</div>
            <div class="action-desc">Help someone get it back</div>
        </a>
        <a href="browse.php" class="action-card">
            <div class="action-icon" style="background:linear-gradient(135deg,var(--primary),#8b8ff5);">🗂️</div>
            <div class="action-title">Browse found items</div>
            <div class="action-desc">See what's been turned in</div>
        </a>
    </div>

    <div class="section-title">Your reports</div>
    <?php if ($total_reports === 0): ?>
        <div class="item-card" style="text-align:center; padding:40px 28px;">
            <strong>Nothing here yet</strong>
            <div class="meta" style="margin-bottom:20px;">Lost something, or found something? Either way, it starts with a report.</div>
            <a href="report_lost.php" class="btn-primary" style="text-decoration:none; display:inline-block;">Report a lost item</a>
            <a href="report_found.php" class="btn-secondary" style="text-decoration:none; display:inline-block; margin-left:10px;">Report a found item</a>
        </div>
    <?php endif; ?>
    <?php foreach ($items as $item): ?>
        <div class="item-card"<?= $item['status'] === 'matched' ? ' style="border-color:var(--orange); border-width:2px;"' : '' ?>>
            <span class="badge badge-<?= $item['type'] ?>"><?= strtoupper($item['type']) ?></span>
            <?php if ($item['status'] === 'matched'): ?>
                <span class="badge" style="background:var(--orange-soft); color:var(--orange);">NEEDS YOUR ATTENTION</span>
            <?php endif; ?>
            <strong><?= htmlentities($item['category']) ?></strong> — <?= htmlentities($item['description']) ?>
            <div class="meta">
                Location: <?= htmlentities($item['location']) ?> |
                Date: <?= htmlentities($item['item_date']) ?> |
                Status: <?= htmlentities($item['status']) ?>
            </div>
            <?php if ($item['type'] === 'lost'): ?>
                <a href="matches.php?item_id=<?= $item['item_id'] ?>">View possible matches →</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>