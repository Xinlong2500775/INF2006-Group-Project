<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse.php');
    exit;
}

$found_item_id = intval($_POST['found_item_id']);
$lost_item_id = intval($_POST['lost_item_id']);
$verification_answer = trim($_POST['verification_answer'] ?? '');
$uid = current_user_id();

if (!$verification_answer) {
    die("Please describe something about the item to prove it's yours.");
}

$conn = get_db_connection();

// SECURITY FIX: confirm the lost item in the form actually belongs to
// the person submitting the claim, instead of trusting the hidden field.
$stmt = mysqli_prepare($conn, "SELECT item_id FROM items WHERE item_id = ? AND reported_by = ? AND type = 'lost'");
mysqli_stmt_bind_param($stmt, "ii", $lost_item_id, $uid);
mysqli_stmt_execute($stmt);
$owned_lost_item = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$owned_lost_item) {
    die("That lost item report doesn't belong to you.");
}

// Confirm the found item is still open (hasn't already been claimed by someone else).
$stmt = mysqli_prepare($conn, "SELECT item_id FROM items WHERE item_id = ? AND type = 'found' AND status = 'open'");
mysqli_stmt_bind_param($stmt, "i", $found_item_id);
mysqli_stmt_execute($stmt);
$open_found_item = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$open_found_item) {
    die("This item is no longer available to claim.");
}

// Claim goes in as 'pending' — nothing about the items changes yet.
// Security has to approve it first (in admin.php) before the found item
// is marked matched and the lost item is marked claimed.
$stmt = mysqli_prepare($conn,
    "INSERT INTO claims (lost_item_id, found_item_id, claimed_by, verification_answer) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iiis", $lost_item_id, $found_item_id, $uid, $verification_answer);
mysqli_stmt_execute($stmt);

header('Location: claim_success.php');
exit;
?>