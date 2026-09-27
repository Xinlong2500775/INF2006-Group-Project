<?php
require_once __DIR__ . '/../inc/db.php';
$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $errors[] = "Please fill in all fields.";
    } else {
        // Email: something before '@', and a '.' somewhere after the '@'
        if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
            $errors[] = "Please enter a valid email address (it must contain '@' and a '.' after it, e.g. name@sit.edu.sg).";
        }

        // Password strength: server-side check (the browser check can be bypassed)
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least 1 uppercase letter.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least 1 lowercase letter.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least 1 number.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least 1 symbol (e.g. ! @ # $ %).";
        }
    }

    if (empty($errors)) {
        $conn = get_db_connection();

        // Check for an existing account (prepared statement)
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $errors[] = "An account with that email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
            mysqli_stmt_bind_param($stmt, "sss", $name, $email, $hash);
            if (mysqli_stmt_execute($stmt)) {
                header('Location: login.php?registered=1');
                exit;
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pw-rules { list-style: none; padding: 0; margin: 6px 0 14px; font-size: 13px; color: var(--ink-soft); }
        .pw-rules li { margin: 2px 0; }
        .pw-rules li::before { content: "✗ "; color: var(--red); font-weight: 700; }
        .pw-rules li.ok { color: var(--green); }
        .pw-rules li.ok::before { content: "✓ "; color: var(--green); }
        .error ul { margin: 0; padding-left: 18px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Create an account</h1>
    <p>Use your school email so your reports can be matched and claimed with confidence.</p>

    <?php if ($errors): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlentities($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label>Full name</label>
        <input type="text" name="name" value="<?= htmlentities($name) ?>" required autofocus>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlentities($email) ?>" required
               pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
               title="Email must contain '@' and a '.' after it, e.g. name@sit.edu.sg">

        <label>Password</label>
        <input type="password" name="password" id="password" required minlength="8"
               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
               title="At least 8 characters, with 1 uppercase, 1 lowercase, 1 number and 1 symbol">
        <ul class="pw-rules" id="pw-rules">
            <li data-rule="length">At least 8 characters</li>
            <li data-rule="upper">1 uppercase letter</li>
            <li data-rule="lower">1 lowercase letter</li>
            <li data-rule="number">1 number</li>
            <li data-rule="symbol">1 symbol (e.g. ! @ # $ %)</li>
        </ul>

        <button type="submit">Create account</button>
    </form>
    <p style="margin-top:16px; font-size:14px; color:var(--ink-soft);">Already have an account? <a href="login.php">Log in</a></p>
</div>

<script>
// Live checklist: ticks each rule as the user types
const pw = document.getElementById('password');
const rules = {
    length: v => v.length >= 8,
    upper:  v => /[A-Z]/.test(v),
    lower:  v => /[a-z]/.test(v),
    number: v => /[0-9]/.test(v),
    symbol: v => /[^A-Za-z0-9]/.test(v)
};
pw.addEventListener('input', () => {
    document.querySelectorAll('#pw-rules li').forEach(li => {
        li.classList.toggle('ok', rules[li.dataset.rule](pw.value));
    });
});
</script>
</body>
</html>