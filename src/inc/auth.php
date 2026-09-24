<?php
session_start();

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        die('Access denied: admin only.');
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
?>
