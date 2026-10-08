<?php
/**
 * Health check for the load balancer (target group health check path: /health.php).
 *
 * Returns 200 "OK" only if this server can run PHP AND reach the database.
 * Returns 503 otherwise, so the ALB stops sending users to a server that
 * would only show errors, and the Auto Scaling group replaces it.
 *
 * Deliberately reveals nothing else (no host names, versions or error text).
 */
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: text/plain');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($conn && mysqli_query($conn, 'SELECT 1')) {
    http_response_code(200);
    echo "OK\n";
} else {
    error_log('health.php: database check failed');
    http_response_code(503);
    echo "UNAVAILABLE\n";
}
