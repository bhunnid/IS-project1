<?php
// Include-only file: never serve it directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
// Credentials live outside the web root and outside Git
require '/etc/isproject/db.php';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    die('Service temporarily unavailable.');
}
$conn->set_charset('utf8mb4');
