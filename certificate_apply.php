<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? $_POST['enrollment_id'] ?? 0);
$stmt = db()->prepare(
    "SELECT e.*, c.title, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee
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
$programPaid = in_array($enrollment['status'], ['paid', 'completed'], true) || course_fee_amount($enrollment) <= 0;
$certificateAmount = certificate_fee_amount($enrollment);
$certificatePaid = $certificateAmount <= 0 || in_array($certificate['status'] ?? '', ['requested', 'issued'], true);

if (!$certificate) {
    $insert = db()->prepare(
        'INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([
        (int) $enrollment['id'],
        (int) $user['id'],
        (int) $enrollment['course_id'],
        $certificateAmount > 0 ? 'payment_pending' : 'requested',
    ]);
    $existingStmt->execute([$enrollmentId]);
    $certificate = $existingStmt->fetch();
    $certificatePaid = $certificateAmount <= 0 || in_array($certificate['status'] ?? '', ['requested', 'issued'], true);
}

if ($programPaid && $certificatePaid) {
    $certificate = ensure_instant_certificate_for_enrollment($enrollmentId) ?? $certificate;
}

$title = 'Apply Certificate | Elldy Academy';
require __DIR__ . '/includes/header.php';
$certificateAppId = 'EA-CERT-' . (int) $enrollment['id'];
$certificatePaymentUrl = 'pay_redirect.php?type=certificate&id=' . (int) $enrollment['id'];
$programPaymentUrl = 'pay_redirect.php?type=program&id=' . (int) $enrollment['id'];
?>
<section class="auth-box">
    <div class="form-card">
        <p class="eyebrow">Certification</p>
        <h1><?= e($enrollment['title']) ?></h1>
        <p class="price-line"><?= $certificateAmount > 0 ? price_html($enrollment, 'certification_fee', 'certificate_discount_fee') : 'Included' ?></p>
        <div class="certificate-lock-preview is-blurred" data-certificate-preview>
            <div>
                <span>Certificate of Completion</span>
                <strong><?= e($enrollment['title']) ?></strong>
                <small><?= e($user['name']) ?></small>
            </div>
            <button class="button tiny" type="button" data-certificate-toggle>Show</button>
        </div>
        <p>Once payment is confirmed, your official certificate from Arklytics Solutions and Innovations and Elldy Platform is generated instantly as a downloadable PDF.</p>
        <?php if ($certificateAmount > 0 && !$certificatePaid): ?>
            <a class="button primary" href="<?= e($certificatePaymentUrl) ?>" target="_blank" rel="noopener">Pay Certification Charge</a>
            <p><small>Certificate payment is separate from program payment.</small></p>
        <?php elseif (!$programPaid): ?>
            <a class="button primary" href="<?= e($programPaymentUrl) ?>" target="_blank" rel="noopener">Pay Program Fee</a>
            <p><small>Program payment is required before certificate download.</small></p>
        <?php elseif ($certificate && $certificate['certificate_url'] && $certificate['status'] === 'issued'): ?>
            <a class="button primary" href="<?= e($certificate['certificate_url']) ?>" target="_blank" rel="noopener">Download Certificate</a>
        <?php else: ?>
            <p>Your certificate charge is included. Once your program payment status is paid, the certificate becomes available automatically.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
