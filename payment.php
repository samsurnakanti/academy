<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? $_POST['enrollment_id'] ?? 0);
$stmt = db()->prepare(
    "SELECT e.*, c.title, c.fee
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     WHERE e.id = ? AND e.user_id = ?"
);
$stmt->execute([$enrollmentId, $user['id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    http_response_code(404);
    exit('Enrollment not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $note = trim($_POST['payment_note'] ?? '');
    $update = db()->prepare('UPDATE enrollments SET status = ?, payment_note = ?, payment_requested_at = NOW() WHERE id = ?');
    $update->execute(['payment_pending', $note, $enrollmentId]);
    flash('success', 'Payment request submitted. Admin will verify and approve.');
    redirect('dashboard.php');
}

$title = 'Payment | Elldy Academy';
require __DIR__ . '/includes/header.php';
$paymentAppId = 'EA-PROGRAM-' . (int) $enrollment['id'];
$paymentUrl = 'pay_redirect.php?type=program&id=' . (int) $enrollment['id'];
?>
<section class="auth-box">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
        <p class="eyebrow">Final payment</p>
        <h1><?= e($enrollment['title']) ?></h1>
        <p class="price-line"><?= money($enrollment['fee']) ?></p>
        <p>After your free first session, continue payment through Elldy secure payment. After payment, submit the payment note so admin can verify and update your live session/course access.</p>
        <a class="button primary" href="<?= e($paymentUrl) ?>" target="_blank" rel="noopener">Proceed to Pay</a>
        <p><small>Payment ID: <?= e($paymentAppId) ?> | Secure payment powered by Razorpay via Elldy.</small></p>
        <fieldset>
            <legend>Payment Verification</legend>
            <label>Transaction ID / notes
                <textarea name="payment_note" rows="4" placeholder="Enter UPI reference, bank transaction ID, or payment message"></textarea>
            </label>
        </fieldset>
        <button class="button primary" type="submit">Submit Payment Request</button>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
