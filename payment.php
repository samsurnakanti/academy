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

$title = 'Payment | Elldy Academy';
require __DIR__ . '/includes/header.php';
$paymentAppId = 'EA-PROGRAM-' . (int) $enrollment['id'];
$paymentUrl = 'pay_redirect.php?type=program&id=' . (int) $enrollment['id'];
?>
<section class="auth-box">
    <div class="form-card">
        <p class="eyebrow">Final payment</p>
        <h1><?= e($enrollment['title']) ?></h1>
        <p class="price-line"><?= money($enrollment['fee']) ?></p>
        <?php if ((float) $enrollment['fee'] <= 0): ?>
            <p>This program is free. No program payment is required.</p>
        <?php else: ?>
            <p>After your free first session, continue payment securely through Razorpay. Access updates automatically after successful payment verification.</p>
            <a class="button primary" href="<?= e($paymentUrl) ?>">Proceed to Pay</a>
            <p><small>Payment ID: <?= e($paymentAppId) ?> | Secure payment powered by Razorpay.</small></p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
