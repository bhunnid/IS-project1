<?php
/* login.php - CSRF, rate limiting (5 failures / 15 min), session fixation defence,
   automatic upgrade of old password hashes to Argon2id. */
require __DIR__ . '/config.php';
require __DIR__ . '/security.php';
$pageTitle = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_str('username');
    $pass     = post_raw('password');

    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif (login_locked($conn, $username)) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $ok = password_verify($pass, $row['password']);
        } else {
            // spend the same time whether or not the user exists
            password_verify($pass, password_hash('dummy-value', PASSWORD_ARGON2ID));
            $ok = false;
        }

        if ($ok) {
            log_attempt($conn, $username, true);
            clear_failures($conn, $username);
            session_regenerate_id(true);
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['login_at'] = time();
            if (password_needs_rehash($row['password'], PASSWORD_ARGON2ID)) {
                $new = password_hash($pass, PASSWORD_ARGON2ID);
                $u = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $u->bind_param('si', $new, $row['id']);
                $u->execute();
                $u->close();
            }
            header('Location: dashboard.php');
            exit;
        }
        log_attempt($conn, $username, false);
        $error = 'Invalid username or password.';
    }
}
include __DIR__ . '/partials/header.php';
?>
<div class="auth-card">
    <div class="card-head"><h4>Welcome back</h4></div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus maxlength="60"
                       autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required maxlength="128"
                       autocomplete="current-password">
            </div>
            <button class="btn btn-primary w-100">Login</button>
            <p class="text-center mt-3 mb-0">No account?
                <a href="register.php">Sign up</a></p>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
