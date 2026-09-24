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

    if ($category && $description && $location && $item_date) {
        $conn = get_db_connection();
        $uid = current_user_id();
        $stmt = mysqli_prepare($conn,
            "INSERT INTO items (reported_by, type, category, description, location, item_date) VALUES (?, 'lost', ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $uid, $category, $description, $location, $item_date);
        mysqli_stmt_execute($stmt);
        $new_item_id = mysqli_insert_id($conn);
        header("Location: matches.php?item_id=$new_item_id");
        exit;
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Report Lost Item — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
    <h1>Report a lost item</h1>
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
        <textarea name="description" rows="3" placeholder="e.g. Blue Hydro Flask water bottle, dent on side, black sticker on cap" required></textarea>
        <label>Location lost</label>
        <input type="text" name="location" placeholder="e.g. Near Library, W3" required>
        <label>Date lost</label>
        <input type="date" name="item_date" required>
        <button type="submit">Submit report</button>
    </form>
</div>
</body>
</html>
