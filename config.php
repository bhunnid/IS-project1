<?php
/*
 * config.php — Database connection for the CAT2 database (MySQL 8.0)
 * Author: Brandon Kiplangat — CNS 3A, Server Administration CAT 2
 *
 * Uses a dedicated, least-privilege MySQL account (cat2_user) that only
 * has rights on the CAT2 database. We never connect to MySQL as root
 * from a web app — that is a basic server-hardening practice.
 */

$DB_HOST = 'localhost';
$DB_USER = 'cat2_user';
$DB_PASS = 'Cat2@StrathCNS';
$DB_NAME = 'CAT2';

// Create the connection (mysqli, object style)
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Stop immediately if the database is unreachable
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Force UTF-8 so names/characters are stored correctly
$conn->set_charset('utf8mb4');
?>
