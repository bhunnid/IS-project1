<?php
/* logout.php — destroy the session and return to login. */
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
?>
