<?php
/* login.php — Login page (Part B Q6).
   Looks up the user, verifies the hashed password, and starts a session. */
require __DIR__ . '/config.php';
session_start();
$pageTitle = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        if (password_verify($pass, $row['password'])) {
            // success — store identity in the session
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            header('Location: dashboard.php');
            exit;
        }
    }
    $error = 'Invalid username or password.';
    $stmt->close();
}
include __DIR__ . '/partials/header.php';
?>
<div class="auth-card">
    <div class="card-head"><h4>Welcome back</h4></div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Login</button>
            <p class="text-center mt-3 mb-0">No account?
                <a href="register.php">Sign up</a></p>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
