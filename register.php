<?php
/* register.php — Signup page (Part B Q5).
   Inserts a new user into the CAT2.users table using a prepared
   statement and a hashed password. */
require __DIR__ . '/config.php';
$pageTitle = 'Sign Up';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $course   = trim($_POST['course'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $pass2    = $_POST['confirm'] ?? '';

    // --- basic validation ---
    if ($fullname === '' || $email === '' || $username === '' || $pass === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($pass) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($pass !== $pass2) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        // hash the password — we never store plaintext
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users (fullname, email, username, password, course)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $fullname, $email, $username, $hash, $course);

        if ($stmt->execute()) {
            $success = 'Account created for ' . htmlspecialchars($username) . '. You can now log in.';
        } else {
            // 1062 = duplicate key (email or username already taken)
            $errors[] = ($conn->errno === 1062)
                ? 'That username or email is already registered.'
                : 'Error: ' . $conn->error;
        }
        $stmt->close();
    }
}
include __DIR__ . '/partials/header.php';
?>
<div class="auth-card">
    <div class="card-head"><h4>Create your account — CNS CAT2</h4></div>
    <div class="card-body">
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger py-2"><?php echo $e; ?></div>
        <?php endforeach; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2"><?php echo $success; ?>
                <a href="login.php" class="alert-link">Go to login</a>.</div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="mb-3">
                <label class="form-label">Full name</label>
                <input type="text" name="fullname" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Course / Class</label>
                <input type="text" name="course" class="form-control" placeholder="e.g. CNS 3A">
            </div>
            <div class="row">
                <div class="col mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col mb-3">
                    <label class="form-label">Confirm</label>
                    <input type="password" name="confirm" class="form-control" required>
                </div>
            </div>
            <button class="btn btn-primary w-100">Sign Up</button>
            <p class="text-center mt-3 mb-0">Already have an account?
                <a href="login.php">Log in</a></p>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
