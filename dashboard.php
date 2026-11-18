<?php
/* dashboard.php - shown after a successful login.
   Protected by require_login() (30-minute idle timeout). Lists registered users with
   e-mail addresses masked so one user cannot harvest another's contact details. */
require __DIR__ . '/config.php';
require __DIR__ . '/security.php';
require_login();
$pageTitle = 'Dashboard';

function mask_email(string $email): string
{
    $at = strpos($email, '@');
    if ($at === false || $at < 1) {
        return '***';
    }
    return substr($email, 0, 1) . str_repeat('*', max(2, $at - 1)) . substr($email, $at);
}

$users = $conn->query('SELECT id, fullname, username, email, course, created_at FROM users ORDER BY id');
include __DIR__ . '/partials/header.php';
?>
<div class="wide-card">
    <h3 class="mb-1">You are logged in</h3>
    <p class="text-muted">Session user:
        <strong><?php echo e($_SESSION['username']); ?></strong>
        &middot; signed in at <?php echo e(gmdate('H:i', (int) ($_SESSION['login_at'] ?? time()))); ?> UTC
        &middot; idle sessions expire after 30 minutes</p>

    <h5 class="mt-4">Registered users</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead>
                <tr><th>#</th><th>Full name</th><th>Username</th>
                    <th>Email</th><th>Course</th><th>Registered</th></tr>
            </thead>
            <tbody>
            <?php while ($users && ($u = $users->fetch_assoc())): ?>
                <tr>
                    <td><?php echo (int) $u['id']; ?></td>
                    <td><?php echo e($u['fullname']); ?></td>
                    <td><?php echo e($u['username']); ?></td>
                    <td><?php echo e(mask_email($u['email'])); ?></td>
                    <td><?php echo e((string) $u['course']); ?></td>
                    <td><?php echo e($u['created_at']); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <a href="feedback.php" class="btn btn-primary">Leave feedback</a>
    <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
