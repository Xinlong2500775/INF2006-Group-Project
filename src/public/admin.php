<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied. This page is for Security staff only.");
}

$conn = get_db_connection();

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id = intval($_POST['claim_id']);
    $action = $_POST['action'] ?? '';

    // Look up the claim so we know which items it touches
    $stmt = mysqli_prepare($conn, "SELECT lost_item_id, found_item_id FROM claims WHERE claim_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, "i", $claim_id);
    mysqli_stmt_execute($stmt);
    $claim = mysqli_stmt_get_result($stmt)->fetch_assoc();

    if ($claim) {
        if ($action === 'approve') {
            $stmt = mysqli_prepare($conn, "UPDATE claims SET status = 'approved' WHERE claim_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $claim_id);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE items SET status = 'matched' WHERE item_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $claim['found_item_id']);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE items SET status = 'claimed' WHERE item_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $claim['lost_item_id']);
            mysqli_stmt_execute($stmt);

            // Any other pending claims on the same found item no longer make sense — reject them
            $stmt = mysqli_prepare($conn, "UPDATE claims SET status = 'rejected' WHERE found_item_id = ? AND status = 'pending' AND claim_id != ?");
            mysqli_stmt_bind_param($stmt, "ii", $claim['found_item_id'], $claim_id);
            mysqli_stmt_execute($stmt);
        } elseif ($action === 'reject') {
            $stmt = mysqli_prepare($conn, "UPDATE claims SET status = 'rejected' WHERE claim_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $claim_id);
            mysqli_stmt_execute($stmt);
        }
    }

    header('Location: admin.php');
    exit;
}

// Pull every pending claim with everything Security needs to judge it
$result = mysqli_query($conn, "
    SELECT
        c.claim_id, c.verification_answer, c.created_at AS claim_date,
        li.item_id AS lost_id, li.category AS lost_category, li.description AS lost_description,
        li.location AS lost_location, li.item_date AS lost_date,
        fi.item_id AS found_id, fi.category AS found_category, fi.description AS found_description,
        fi.location AS found_location, fi.item_date AS found_date, fi.private_detail,
        claimant.name AS claimant_name,
        finder.name AS finder_name
    FROM claims c
    JOIN items li ON c.lost_item_id = li.item_id
    JOIN items fi ON c.found_item_id = fi.item_id
    JOIN users claimant ON c.claimed_by = claimant.user_id
    JOIN users finder ON fi.reported_by = finder.user_id
    WHERE c.status = 'pending'
    ORDER BY c.created_at ASC
");
$pending_claims = [];
while ($row = mysqli_fetch_assoc($result)) {
    $pending_claims[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="wrap">
    <h1>Admin panel</h1>
    <p style="color:var(--ink-soft);">Pending claims waiting on your review.</p>

    <?php if (empty($pending_claims)): ?>
        <div class="item-card" style="text-align:center; padding:40px 28px;">
            <strong>Nothing pending</strong>
            <div class="meta">All caught up — no claims are waiting on review right now.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($pending_claims as $c): ?>
        <div class="item-card" style="margin-top:18px;">
            <span class="badge" style="background:var(--orange-soft); color:var(--orange);">PENDING CLAIM #<?= $c['claim_id'] ?></span>
            <div class="meta" style="margin-bottom:14px;">
                <?= htmlentities($c['claimant_name']) ?> claims this belongs to them, submitted <?= htmlentities($c['claim_date']) ?>
            </div>

            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div style="flex:1; min-width:240px; background:var(--bg); border-radius:12px; padding:16px;">
                    <span class="badge badge-lost">LOST REPORT</span>
                    <strong><?= htmlentities($c['lost_category']) ?></strong> — <?= htmlentities($c['lost_description']) ?>
                    <div class="meta">Lost at <?= htmlentities($c['lost_location']) ?> on <?= htmlentities($c['lost_date']) ?></div>
                </div>
                <div style="flex:1; min-width:240px; background:var(--bg); border-radius:12px; padding:16px;">
                    <span class="badge badge-found">FOUND REPORT</span>
                    <strong><?= htmlentities($c['found_category']) ?></strong> — <?= htmlentities($c['found_description']) ?>
                    <div class="meta">Found at <?= htmlentities($c['found_location']) ?> on <?= htmlentities($c['found_date']) ?> by <?= htmlentities($c['finder_name']) ?></div>
                </div>
            </div>

            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:14px;">
                <div style="flex:1; min-width:240px; background:var(--orange-soft); border-radius:12px; padding:16px;">
                    <strong style="font-size:12.5px; text-transform:uppercase; letter-spacing:0.04em; color:var(--orange);">Private detail (from finder)</strong>
                    <div style="margin-top:6px;"><?= htmlentities($c['private_detail']) ?></div>
                </div>
                <div style="flex:1; min-width:240px; background:var(--primary-soft); border-radius:12px; padding:16px;">
                    <strong style="font-size:12.5px; text-transform:uppercase; letter-spacing:0.04em; color:var(--primary);">Claimant's answer</strong>
                    <div style="margin-top:6px;"><?= htmlentities($c['verification_answer']) ?></div>
                </div>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px;">
                <form method="POST">
                    <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit">Approve</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn-secondary">Reject</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>