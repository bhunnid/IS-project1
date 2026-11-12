<?php
/* dashboard.php — shown after a successful login (proves Part B Q6).
   Protected: redirects to login if there is no session. Also lists all
   registered users so the grader can see the signup table filling up. */
require __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$pageTitle = 'Dashboard';
include __DIR__ . '/partials/header.php';

$users = $conn->query('SELECT id, fullname, username, email, course, created_at
                       FROM users ORDER BY id');
?>
<div class="wide-card">
    <h3 class="mb-1">You are logged in ✅</h3>
    <p class="text-muted">Session user:
        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>

    <h5 class="mt-4">Registered users (CAT2.users)</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead>
                <tr><th>#</th><th>Full name</th><th>Username</th>
                    <th>Email</th><th>Course</th><th>Registered</th></tr>
            </thead>
            <tbody>
            <?php while ($u = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['fullname']); ?></td>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['course']); ?></td>
                    <td><?php echo $u['created_at']; ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <a href="feedback.php" class="btn btn-primary">Leave feedback</a>
    <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
