<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse.php');
    exit;
}

$found_item_id = intval($_POST['found_item_id']);
$lost_item_id = isset($_POST['lost_item_id']) ? intval($_POST['lost_item_id']) : null;
$uid = current_user_id();

$conn = get_db_connection();
$stmt = mysqli_prepare($conn,
    "INSERT INTO claims (lost_item_id, found_item_id, claimed_by) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iii", $lost_item_id, $found_item_id, $uid);
mysqli_stmt_execute($stmt);

// Found item is now matched to a claim...
$stmt = mysqli_prepare($conn, "UPDATE items SET status = 'matched' WHERE item_id = ?");
mysqli_stmt_bind_param($stmt, "i", $found_item_id);
mysqli_stmt_execute($stmt);

// ...and the lost item is now claimed by its owner, so both sides of
// the transaction reflect what actually happened.
if ($lost_item_id) {
    $stmt = mysqli_prepare($conn, "UPDATE items SET status = 'claimed' WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $lost_item_id);
    mysqli_stmt_execute($stmt);
}

header('Location: dashboard.php?claimed=1');
exit;
?>