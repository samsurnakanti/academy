<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? $_POST['enrollment_id'] ?? 0);
$stmt = db()->prepare(
    "SELECT e.*, c.title, c.fee, c.discount_fee, c.payment_required, c.certification_fee, c.certificate_discount_fee
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
$programPaid = in_array($enrollment['status'], ['paid', 'completed'], true) || !course_requires_payment($enrollment);
$certificateAmount = certificate_fee_amount($enrollment);
$certificatePaid = $certificateAmount <= 0 || trim((string) ($certificate['payment_note'] ?? '')) !== '';

if (!$certificate) {
    $insert = db()->prepare(
        'INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status, applied_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $insert->execute([
        (int) $enrollment['id'],
        (int) $user['id'],
        (int) $enrollment['course_id'],
        $certificateAmount > 0 ? 'payment_pending' : 'requested',
    ]);
    $existingStmt->execute([$enrollmentId]);
    $certificate = $existingStmt->fetch();
    $certificatePaid = $certificateAmount <= 0 || trim((string) ($certificate['payment_note'] ?? '')) !== '';
} elseif (empty($certificate['applied_at'])) {
    $applied = db()->prepare('UPDATE certificate_requests SET applied_at = NOW() WHERE enrollment_id = ?');
    $applied->execute([(int) $enrollment['id']]);
    $existingStmt->execute([$enrollmentId]);
    $certificate = $existingStmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_dashboard') {
        $dashboardUrl = normalize_elldy_dashboard_url((string) ($_POST['dashboard_url'] ?? ''));

        if ($dashboardUrl === '' || !filter_var($dashboardUrl, FILTER_VALIDATE_URL)) {
            flash('error', 'Enter a valid public Elldy dashboard link.');
        } elseif (!is_elldy_dashboard_url($dashboardUrl)) {
            flash('error', 'Certificate review accepts only public Elldy dashboard links from elldy.com.');
        } elseif (($certificate['dashboard_review_status'] ?? '') === 'approved' && ($certificate['status'] ?? '') === 'issued') {
            flash('error', 'This certificate is already issued.');
        } else {
            $status = $certificateAmount > 0 && !$certificatePaid ? 'payment_pending' : 'requested';
            $update = db()->prepare(
                "UPDATE certificate_requests
                 SET dashboard_url = ?,
                     applied_at = COALESCE(applied_at, NOW()),
                     dashboard_review_status = 'pending',
                     dashboard_review_note = NULL,
                     dashboard_submitted_at = NOW(),
                     dashboard_reviewed_at = NULL,
                     status = ?,
                     certificate_url = NULL,
                     certificate_code = NULL,
                     issued_at = NULL
                 WHERE enrollment_id = ?"
            );
            $update->execute([$dashboardUrl, $status, (int) $enrollment['id']]);
            flash('success', 'Dashboard link submitted for review.');
        }

        redirect('certificate_apply.php?enrollment_id=' . (int) $enrollment['id']);
    }
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
        <p>Create an Elldy account, import your dataset, build a dashboard, and submit the public Elldy dashboard link here. After admin review and issue approval, your certificate can be downloaded.</p>
        <form method="post" class="certificate-dashboard-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="submit_dashboard">
            <label>Public Elldy dashboard link
                <input type="url" name="dashboard_url" value="<?= e((string) ($certificate['dashboard_url'] ?? '')) ?>" placeholder="https://elldy.com/..." required>
            </label>
            <button class="button primary" type="submit">Submit for Review</button>
            <p><small>Status: <?= e(dashboard_review_badge($certificate['dashboard_review_status'] ?? 'not_submitted')) ?></small></p>
            <?php if (!empty($certificate['dashboard_review_note'])): ?>
                <p><small>Review note: <?= e($certificate['dashboard_review_note']) ?></small></p>
            <?php endif; ?>
        </form>
        <?php if ($certificateAmount > 0 && !$certificatePaid): ?>
            <a class="button primary" href="<?= e($certificatePaymentUrl) ?>" target="_blank" rel="noopener">Pay Certification Charge</a>
            <p><small>Certificate payment is separate from program payment.</small></p>
        <?php elseif (!$programPaid): ?>
            <a class="button primary" href="<?= e($programPaymentUrl) ?>" target="_blank" rel="noopener">Pay Program Fee</a>
            <p><small>Program payment is required before certificate download.</small></p>
        <?php elseif (($certificate['status'] ?? '') === 'rejected'): ?>
            <p>Your certificate request was cancelled. Submit a corrected public Elldy dashboard link for review.</p>
        <?php elseif (!certificate_dashboard_is_approved($certificate)): ?>
            <p>Your public Elldy dashboard link must be reviewed before certificate download.</p>
        <?php elseif ($certificatePaid && $programPaid && $certificate && $certificate['certificate_url'] && $certificate['status'] === 'issued'): ?>
            <a class="button primary" href="<?= e($certificate['certificate_url']) ?>" target="_blank" rel="noopener">Download Certificate</a>
        <?php else: ?>
            <p>Your dashboard is approved. Please wait for admin to issue your certificate.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
