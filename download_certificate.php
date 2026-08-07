<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? 0);
$stmt = db()->prepare(
    "SELECT cr.*, e.id AS enrollment_id, e.user_id, e.status AS enrollment_status, u.name,
            c.title, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee, c.certificate_title, c.certificate_details
     FROM certificate_requests cr
     JOIN enrollments e ON e.id = cr.enrollment_id
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     WHERE cr.enrollment_id = ? AND e.user_id = ?"
);
$stmt->execute([$enrollmentId, $user['id']]);
$certificate = $stmt->fetch();

if (!$certificate) {
    http_response_code(404);
    exit('Certificate not found.');
}

if (!in_array($certificate['enrollment_status'], ['paid', 'completed'], true) && course_requires_payment($certificate)) {
    http_response_code(403);
    exit('Please complete program payment before downloading your certificate.');
}

if (certificate_fee_amount($certificate) > 0 && trim((string) ($certificate['payment_note'] ?? '')) === '') {
    http_response_code(403);
    exit('Please complete certificate payment before downloading your certificate.');
}

if (!certificate_dashboard_is_approved($certificate)) {
    http_response_code(403);
    exit('Please submit a public Elldy dashboard link and wait for admin review before downloading your certificate.');
}

if (($certificate['status'] ?? '') !== 'issued') {
    http_response_code(403);
    exit('Your certificate is not issued yet.');
}

$expectedPath = __DIR__ . '/assets/certificates/issued/certificate-' . $enrollmentId . '.pdf';
if (!is_file($expectedPath)) {
    issue_certificate_for_enrollment($certificate);
}

if (!is_file($expectedPath)) {
    http_response_code(404);
    exit('Certificate file not found.');
}

$downloadUpdate = db()->prepare(
    "UPDATE certificate_requests
     SET downloaded_at = NOW(), download_count = download_count + 1
     WHERE enrollment_id = ?"
);
$downloadUpdate->execute([$enrollmentId]);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="certificate-' . $enrollmentId . '.pdf"');
readfile($expectedPath);
exit;
