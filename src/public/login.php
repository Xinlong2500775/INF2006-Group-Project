<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $conn = get_db_connection();

    // Prepared statement, consistent with the rest of the app
    $stmt = mysqli_prepare($conn, "SELECT user_id, name, password_hash, role FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();

    // Same message whether the email or the password is wrong,
    // so attackers can't tell which emails are registered
    if ($row && password_verify($password, $row['password_hash'])) {
        // New session ID on login, protects against session fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['name'] = $row['name'];
        $_SESSION['role'] = $row['role'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Incorrect email or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Welcome back</h1>
    <p>Log in to report an item, browse what's been found, or check your matches.</p>
    <?php if (isset($_GET['registered'])): ?><p class="success">Account created — you can log in now.</p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= htmlentities($error) ?></p><?php endif; ?>
    <form method="POST">
        <label>Email</label>
        <input type="email" name="email" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit">Log in</button>
    </form>
    <p style="margin-top:16px; font-size:14px; color:var(--ink-soft);">No account yet? <a href="register.php">Register</a></p>
</div>
</body>
</html>