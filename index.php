<?php
/* index.php — landing page. Sends logged-in users to the dashboard,
   everyone else to the login page. */
session_start();
if (!empty($_SESSION['username'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>
