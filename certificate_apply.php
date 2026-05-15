<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? $_POST['enrollment_id'] ?? 0);
$stmt = db()->prepare(
    "SELECT e.*, c.title, c.certification_fee
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
);
$stmt->execute([$enrollmentId, $user['id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    http_response_code(404);
    exit('Certificate access not found.');
}

$existingStmt = db()->prepare('SELECT * FROM certificate_requests WHERE enrollment_id = ?');
$existingStmt->execute([$enrollmentId]);
$certificate = $existingStmt->fetch();

if (!$certificate) {
    $insert = db()->prepare(
        'INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([
        (int) $enrollment['id'],
        (int) $user['id'],
        (int) $enrollment['course_id'],
        ((float) $enrollment['certification_fee']) > 0 ? 'payment_pending' : 'requested',
    ]);
    $existingStmt->execute([$enrollmentId]);
    $certificate = $existingStmt->fetch();
}

if (in_array($enrollment['status'], ['paid', 'completed'], true)) {
    $certificate = ensure_instant_certificate_for_enrollment($enrollmentId) ?? $certificate;
}

$title = 'Apply Certificate | Elldy Academy';
require __DIR__ . '/includes/header.php';
$certificateAppId = 'EA-CERT-' . (int) $enrollment['id'];
$certificateAmount = (float) $enrollment['certification_fee'];
$certificatePaymentUrl = 'pay_redirect.php?type=certificate&id=' . (int) $enrollment['id'];
?>
<section class="auth-box">
    <div class="form-card">
        <p class="eyebrow">Certification</p>
        <h1><?= e($enrollment['title']) ?></h1>
        <p class="price-line"><?= ((float) $enrollment['certification_fee']) > 0 ? money($enrollment['certification_fee']) : 'Included' ?></p>
        <p>Once your payment is confirmed, your official certificate from Arklytics Solutions and Innovations and Elldy Platform is generated instantly for download.</p>
        <?php if ($certificate && $certificate['certificate_url'] && $certificate['status'] === 'issued'): ?>
            <a class="button primary" href="<?= e($certificate['certificate_url']) ?>" target="_blank" rel="noopener">Download Certificate</a>
        <?php elseif ($certificateAmount > 0): ?>
            <a class="button primary" href="<?= e($certificatePaymentUrl) ?>" target="_blank" rel="noopener">Pay Certification Charge</a>
            <p><small>Certificate Payment ID: <?= e($certificateAppId) ?> | Secure payment powered by Razorpay via Elldy.</small></p>
        <?php else: ?>
            <p>Your certificate charge is included. Once your program payment status is paid, the certificate becomes available automatically.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
