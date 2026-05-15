<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
header('Content-Type: application/json');

verify_csrf();
$type = $_POST['type'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$orderId = trim((string) ($_POST['order_id'] ?? ''));
$paymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string) ($_POST['razorpay_signature'] ?? ''));

if (!$orderId || !$paymentId || !$signature || !verify_razorpay_signature($orderId, $paymentId, $signature)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Payment verification failed.']);
    exit;
}

if ($type === 'program') {
    $stmt = db()->prepare("UPDATE enrollments SET status = 'paid', payment_note = ?, payment_requested_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute(['Razorpay payment: ' . $paymentId, $id, $user['id']]);

    $courseStmt = db()->prepare(
        "SELECT e.id, e.user_id, e.course_id, c.certification_fee
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ?"
    );
    $courseStmt->execute([$id, $user['id']]);
    $enrollment = $courseStmt->fetch();

    if ($enrollment && ((float) $enrollment['certification_fee']) <= 0) {
        $request = db()->prepare(
            "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status)
             VALUES (?, ?, ?, 'requested')
             ON DUPLICATE KEY UPDATE enrollment_id = enrollment_id"
        );
        $request->execute([
            (int) $enrollment['id'],
            (int) $enrollment['user_id'],
            (int) $enrollment['course_id'],
        ]);
        ensure_instant_certificate_for_enrollment($id);
    }

    echo json_encode(['ok' => true, 'redirect' => public_url('dashboard.php')]);
    exit;
}

if ($type === 'certificate') {
    $stmt = db()->prepare(
        "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status, payment_note)
         SELECT e.id, e.user_id, e.course_id, 'requested', ?
         FROM enrollments e
         WHERE e.id = ? AND e.user_id = ?
         ON DUPLICATE KEY UPDATE payment_note = VALUES(payment_note)"
    );
    $stmt->execute(['Razorpay payment: ' . $paymentId, $id, $user['id']]);
    ensure_instant_certificate_for_enrollment($id);
    echo json_encode(['ok' => true, 'redirect' => public_url('dashboard.php')]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'message' => 'Unknown payment type.']);
