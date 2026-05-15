<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? 0);
$stmt = db()->prepare(
    "SELECT cr.*, e.id AS enrollment_id, e.user_id, e.status AS enrollment_status, u.name, c.title
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

$expectedPath = __DIR__ . '/assets/certificates/issued/certificate-' . $enrollmentId . '.svg';
if (!is_file($expectedPath)) {
    issue_certificate_for_enrollment($certificate);
}

if (!is_file($expectedPath)) {
    http_response_code(404);
    exit('Certificate file not found.');
}

header('Content-Type: image/svg+xml');
header('Content-Disposition: attachment; filename="certificate-' . $enrollmentId . '.svg"');
readfile($expectedPath);
exit;
