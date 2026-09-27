<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $item_date = $_POST['item_date'];
    $private_detail = trim($_POST['private_detail']); 

    if ($category && $description && $location && $item_date && $private_detail) { 
        $conn = get_db_connection();
        $uid = current_user_id();
        $stmt = mysqli_prepare($conn,
            "INSERT INTO items (reported_by, type, category, description, location, item_date, private_detail) VALUES (?, 'found', ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssss", $uid, $category, $description, $location, $item_date, $private_detail); 
        mysqli_stmt_execute($stmt);
        header('Location: report_found_success.php');
        exit;
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Report Found Item — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Report a found item</h1>
    <p>Every found item you log gives someone a chance to get it back.</p>
    <?php if ($error): ?><p class="error"><?= htmlentities($error) ?></p><?php endif; ?>
    <form method="POST">
        <label>Category</label>
        <select name="category" required>
            <option value="">-- Select --</option>
            <option>Bottle/Container</option>
            <option>Electronics</option>
            <option>Bag</option>
            <option>ID Card/Wallet</option>
            <option>Clothing</option>
            <option>Stationery/Books</option>
            <option>Other</option>
        </select>
        <label>Description</label>
        <textarea name="description" rows="3" placeholder="e.g. Found a blue water bottle near W3 lobby, has a black sticker on it" required></textarea>
        <label>Location found</label>
        <input type="text" name="location" placeholder="e.g. W3 Lobby" required>
        <label>Date found</label>
        <input type="date" name="item_date" required>
        <label>Private distinguishing detail</label>
        <textarea name="private_detail" rows="2" placeholder="Something NOT in your description above — e.g. a scratch, a name written inside, a sticker on the underside. Only Security will see this, and it's used to check that a claimant really owns the item." required></textarea>
        <button type="submit">Submit report</button>
    </form>
</div>
</body>
</html>