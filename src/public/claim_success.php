<?php
require_once __DIR__ . '/../inc/auth.php';
require_login();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Claim Submitted — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container" style="text-align:center;">
    <div style="width:64px; height:64px; border-radius:50%; background:var(--green-soft); color:var(--green); display:flex; align-items:center; justify-content:center; font-size:32px; margin:0 auto 20px;">✓</div>
    <h1>Claim submitted</h1>
    <p style="color:var(--ink-soft);">
        Thanks — your answer has been sent to Security. They'll compare it against the finder's report
        and confirm the handover once it checks out. You'll see the status update on your dashboard.
    </p>
    <a href="dashboard.php" class="btn-primary" style="text-decoration:none; display:inline-block;">Back to dashboard</a>
</div>
</body>
</html>