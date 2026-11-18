<?php
/* feedback.php - second form: logged-in users only, CSRF token, server-side validation
   (whitelisted rating, length limits), prepared statements and escaped output.
   Name and e-mail come from the user's own account, not the form, so they cannot be spoofed. */
require __DIR__ . '/config.php';
require __DIR__ . '/security.php';
require_login();
$pageTitle = 'Feedback';
$saved  = '';
$errors = [];
$old = ['subject' => '', 'rating' => '5', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['subject'] = post_str('subject');
    $old['rating']  = post_str('rating');
    $old['message'] = post_str('message');

    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $sl = mb_strlen($old['subject']);
        if ($sl < 3 || $sl > 150) {
            $errors[] = 'Subject must be 3-150 characters.';
        }
        if (!in_array($old['rating'], ['1', '2', '3', '4', '5'], true)) {
            $errors[] = 'Choose a rating from 1 to 5.';
        }
        $ml = mb_strlen($old['message']);
        if ($ml < 10 || $ml > 2000) {
            $errors[] = 'Message must be 10-2000 characters.';
        }
    }

    if (!$errors) {
        $me = $conn->prepare('SELECT fullname, email FROM users WHERE id = ?');
        $me->bind_param('i', $_SESSION['user_id']);
        $me->execute();
        $acct = $me->get_result()->fetch_assoc();
        $me->close();

        $rating = (int) $old['rating'];
        $stmt = $conn->prepare('INSERT INTO feedback (name, email, subject, rating, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sssis', $acct['fullname'], $acct['email'], $old['subject'], $rating, $old['message']);
        if ($stmt->execute()) {
            $saved = 'Thank you! Your feedback was saved.';
            $old = ['subject' => '', 'rating' => '5', 'message' => ''];
        } else {
            error_log('feedback insert failed: ' . $conn->error);
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}

$rows = $conn->query('SELECT id, name, subject, rating, message, created_at FROM feedback ORDER BY id DESC LIMIT 50');
include __DIR__ . '/partials/header.php';
?>
<div class="wide-card">
    <h3>Course Feedback Form</h3>
    <p class="text-muted">Tell us about this Server Administration unit.</p>

    <?php if ($saved): ?><div class="alert alert-success py-2"><?php echo e($saved); ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?php echo e($err); ?></div><?php endforeach; ?>

    <form method="post" class="row g-3">
        <?php echo csrf_field(); ?>
        <div class="col-md-8">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control" required minlength="3" maxlength="150"
                   value="<?php echo e($old['subject']); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Rating</label>
            <select name="rating" class="form-select" required>
                <?php foreach ([5 => 'Excellent', 4 => 'Good', 3 => 'Average', 2 => 'Poor', 1 => 'Very poor'] as $v => $l): ?>
                    <option value="<?php echo $v; ?>"<?php echo $old['rating'] === (string) $v ? ' selected' : ''; ?>><?php echo $v . ' - ' . $l; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Message</label>
            <textarea name="message" rows="3" class="form-control" required minlength="10"
                      maxlength="2000"><?php echo e($old['message']); ?></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Submit feedback</button>
        </div>
    </form>

    <h5 class="mt-4">Recent feedback</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead>
                <tr><th>#</th><th>Name</th><th>Subject</th>
                    <th>Rating</th><th>Message</th><th>When</th></tr>
            </thead>
            <tbody>
            <?php while ($rows && ($f = $rows->fetch_assoc())): ?>
                <tr>
                    <td><?php echo (int) $f['id']; ?></td>
                    <td><?php echo e($f['name']); ?></td>
                    <td><?php echo e($f['subject']); ?></td>
                    <td><?php echo (int) $f['rating']; ?>/5</td>
                    <td><?php echo e($f['message']); ?></td>
                    <td><?php echo e($f['created_at']); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
