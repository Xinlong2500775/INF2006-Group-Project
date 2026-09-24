<?php
require_once __DIR__ . '/../inc/auth.php';
require_login();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reported — Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container" style="text-align:center;">
    <h1>Thank you</h1>
    <p>Your found item has been logged. If it matches something someone else has reported lost, they'll be able to see and claim it.</p>
    <a href="report_found.php" class="btn-primary" style="text-decoration:none; display:inline-block;">Report another item</a>
    <a href="dashboard.php" class="btn-secondary" style="text-decoration:none; display:inline-block; margin-left:10px;">Back to dashboard</a>
</div>
</body>
</html>