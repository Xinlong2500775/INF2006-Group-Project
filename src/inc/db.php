<?php
/**
 * Database connection.
 *
 * Credentials are never stored in the repository. They are read from, in order:
 *   1. Real environment variables (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME),
 *      e.g. set by the EC2 launch script.
 *   2. src/.env (copy src/.env.example and fill in; the real .env is gitignored
 *      and sits outside the public web folder, so it can't be downloaded).
 *   3. src/inc/dbinfo.inc.php (older local setup, also gitignored).
 */
function load_env_file(string $path): void {
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {   // real environment variables win
            putenv("$key=$value");
        }
    }
}

load_env_file(__DIR__ . '/../.env');

if (getenv('DB_HOST') !== false) {
    define('DB_SERVER', getenv('DB_HOST'));
    define('DB_USERNAME', getenv('DB_USER') ?: '');
    define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
    define('DB_DATABASE', getenv('DB_NAME') ?: 'inf2006');
} else {
    require_once __DIR__ . '/dbinfo.inc.php';
}

function get_db_connection() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if (!$conn) {
        // Log the real reason for the team; show users a generic message only,
        // so hostnames and usernames are never leaked to the browser.
        error_log("Database connection failed: " . mysqli_connect_error());
        http_response_code(503);
        die("The service is temporarily unavailable. Please try again shortly.");
    }
    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}
?>
