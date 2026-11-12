<?php
/* feedback.php — the SECOND form required by Part B Q7.
   POSTs into CAT2.feedback, then retrieves and displays every row,
   proving we can both write to and read from the new table. */
require __DIR__ . '/config.php';
$pageTitle = 'Feedback';
$saved = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $rating  = (int)($_POST['rating'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $subject && $message) {
        $stmt = $conn->prepare(
            'INSERT INTO feedback (name, email, subject, rating, message)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssis', $name, $email, $subject, $rating, $message);
        $saved = $stmt->execute() ? 'Thank you! Your feedback was saved.'
                                  : 'Error: ' . $conn->error;
        $stmt->close();
    } else {
        $error = 'Please fill in all fields.';
    }
}

// Retrieve all feedback for display
$rows = $conn->query('SELECT * FROM feedback ORDER BY id DESC');
include __DIR__ . '/partials/header.php';
?>
<div class="wide-card">
    <h3>Course Feedback Form</h3>
    <p class="text-muted">Tell us about this Server Administration unit.</p>

    <?php if ($saved): ?><div class="alert alert-success py-2"><?php echo $saved; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo $error; ?></div><?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-8">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Rating</label>
            <select name="rating" class="form-select">
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Good</option>
                <option value="3">3 - Average</option>
                <option value="2">2 - Poor</option>
                <option value="1">1 - Very poor</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Message</label>
            <textarea name="message" rows="3" class="form-control" required></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Submit feedback</button>
        </div>
    </form>

    <h5 class="mt-4">Submitted feedback (retrieved from CAT2.feedback)</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead>
                <tr><th>#</th><th>Name</th><th>Subject</th>
                    <th>Rating</th><th>Message</th><th>When</th></tr>
            </thead>
            <tbody>
            <?php while ($f = $rows->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $f['id']; ?></td>
                    <td><?php echo htmlspecialchars($f['name']); ?></td>
                    <td><?php echo htmlspecialchars($f['subject']); ?></td>
                    <td><?php echo (int)$f['rating']; ?>/5</td>
                    <td><?php echo htmlspecialchars($f['message']); ?></td>
                    <td><?php echo $f['created_at']; ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
