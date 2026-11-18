<?php
/* register.php - Signup: CSRF, strict validation, Argon2id, prepared statements,
   12+ character password policy and breached-password check. */
require __DIR__ . '/config.php';
require __DIR__ . '/security.php';
$pageTitle = 'Sign Up';
$errors  = [];
$success = '';
$old = ['fullname' => '', 'email' => '', 'username' => '', 'course' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['fullname'] = post_str('fullname');
    $old['email']    = post_str('email');
    $old['username'] = post_str('username');
    $old['course']   = post_str('course');
    $pass  = post_raw('password');
    $pass2 = post_raw('confirm');

    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    if (!$errors) {
        $len = mb_strlen($old['fullname']);
        if ($len < 2 || $len > 100 || !preg_match('/^[\p{L}\p{M}\' .-]+$/u', $old['fullname'])) {
            $errors[] = 'Enter your full name (letters, spaces, hyphens and apostrophes only).';
        }
        if (strlen($old['email']) > 120 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $old['username'])) {
            $errors[] = 'Username must be 3-30 characters: letters, numbers and underscore only.';
        }
        if ($old['course'] !== '' && !preg_match('/^[A-Za-z0-9 ]{1,40}$/', $old['course'])) {
            $errors[] = 'Course may contain only letters, numbers and spaces.';
        }
        foreach (password_problems($pass, $old['username']) as $p) {
            $errors[] = $p;
        }
        if ($pass !== $pass2) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$errors) {
            $n = pwned_count($pass);
            if ($n > 0) {
                $errors[] = 'That password appears in ' . number_format($n) . ' known data breaches. Choose another.';
            }
        }
    }

    if (!$errors) {
        $hash = password_hash($pass, PASSWORD_ARGON2ID);
        $stmt = $conn->prepare(
            'INSERT INTO users (fullname, email, username, password, course) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $old['fullname'], $old['email'], $old['username'], $hash, $old['course']);
        if ($stmt->execute()) {
            $success = 'Account created for ' . e($old['username']) . '. You can now log in.';
            $old = ['fullname' => '', 'email' => '', 'username' => '', 'course' => ''];
        } else {
            if ($conn->errno === 1062) {
                $errors[] = 'That username or email is already registered.';
            } else {
                error_log('register insert failed: ' . $conn->error);
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
        $stmt->close();
    }
}
include __DIR__ . '/partials/header.php';
?>
<div class="auth-card">
    <div class="card-head"><h4>Create your account</h4></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger py-2"><?php echo e($err); ?></div>
        <?php endforeach; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2"><?php echo $success; ?>
                <a href="login.php" class="alert-link">Go to login</a>.</div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Full name</label>
                <input type="text" name="fullname" class="form-control" required minlength="2" maxlength="100"
                       value="<?php echo e($old['fullname']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required maxlength="120"
                       value="<?php echo e($old['email']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required
                       pattern="[A-Za-z0-9_]{3,30}" title="3-30 letters, numbers or underscore"
                       value="<?php echo e($old['username']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Course / Class</label>
                <input type="text" name="course" class="form-control" placeholder="e.g. CNS 3A" maxlength="40"
                       pattern="[A-Za-z0-9 ]{0,40}" value="<?php echo e($old['course']); ?>">
            </div>
            <div class="row">
                <div class="col mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="12"
                           maxlength="128" autocomplete="new-password">
                </div>
                <div class="col mb-3">
                    <label class="form-label">Confirm</label>
                    <input type="password" name="confirm" class="form-control" required minlength="12"
                           maxlength="128" autocomplete="new-password">
                </div>
            </div>
            <p class="small text-muted">12+ characters with upper and lower case, a digit and a symbol.</p>
            <button class="btn btn-primary w-100">Sign Up</button>
            <p class="text-center mt-3 mb-0">Already have an account?
                <a href="login.php">Log in</a></p>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
