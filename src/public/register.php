<?php
require_once __DIR__ . '/../inc/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($name && $email && $password) {
        $conn = get_db_connection();
        $email_esc = mysqli_real_escape_string($conn, $email);

        $check = mysqli_query($conn, "SELECT user_id FROM users WHERE email = '$email_esc'");
        if (mysqli_num_rows($check) > 0) {
            $error = "An account with that email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $name_esc = mysqli_real_escape_string($conn, $name);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
            mysqli_stmt_bind_param($stmt, "sss", $name_esc, $email_esc, $hash);
            if (mysqli_stmt_execute($stmt)) {
                header('Location: login.php?registered=1');
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Create an account</h1>
    <p>Use your school email so your reports can be matched and claimed with confidence.</p>
    <?php if ($error): ?><p class="error"><?= htmlentities($error) ?></p><?php endif; ?>
    <form method="POST">
        <label>Full name</label>
        <input type="text" name="name" required autofocus>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Password</label>
        <input type="password" name="password" required minlength="6">
        <button type="submit">Create account</button>
    </form>
    <p style="margin-top:16px; font-size:14px; color:var(--ink-soft);">Already have an account? <a href="login.php">Log in</a></p>
</div>
</body>
</html>