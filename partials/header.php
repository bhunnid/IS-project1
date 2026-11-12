<?php
/* partials/header.php — shared top of every page (nav + Bootstrap) */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pageTitle = $pageTitle ?? 'IS Project 1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Ian Muyala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark px-4">
    <a class="navbar-brand" href="index.php">IS&nbsp;Project&nbsp;1</a>
    <div class="ms-auto">
        <?php if (!empty($_SESSION['username'])): ?>
            <span class="text-light me-3">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a class="btn btn-sm btn-outline-light" href="feedback.php">Feedback</a>
            <a class="btn btn-sm btn-outline-light" href="dashboard.php">Dashboard</a>
            <a class="btn btn-sm btn-danger" href="logout.php">Logout</a>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="login.php">Login</a>
            <a class="btn btn-sm btn-primary" href="register.php">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>
