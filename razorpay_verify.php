<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();
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
        "SELECT e.id, e.user_id, e.course_id, c.certification_fee, c.certificate_discount_fee, cr.status AS certificate_status
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN certificate_requests cr ON cr.enrollment_id = e.id
         WHERE e.id = ? AND e.user_id = ?"
    );
    $courseStmt->execute([$id, $user['id']]);
    $enrollment = $courseStmt->fetch();

    if ($enrollment) {
        if (certificate_fee_amount($enrollment) <= 0) {
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
        }

        if (certificate_fee_amount($enrollment) <= 0 || in_array($enrollment['certificate_status'] ?? '', ['requested', 'issued'], true)) {
            ensure_instant_certificate_for_enrollment($id);
        }
    }

    echo json_encode(['ok' => true, 'redirect' => public_url('dashboard.php')]);
    exit;
}

if ($type === 'certificate') {
    $enrollmentStmt = db()->prepare(
        "SELECT e.id, e.user_id, e.course_id, e.status, c.fee, c.discount_fee
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
    );
    $enrollmentStmt->execute([$id, $user['id']]);
    $enrollment = $enrollmentStmt->fetch();

    if (!$enrollment) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Certificate payment not found.']);
        exit;
    }

    $stmt = db()->prepare(
        "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status, payment_note)
         VALUES (?, ?, ?, 'requested', ?)
         ON DUPLICATE KEY UPDATE status = IF(status = 'issued', status, VALUES(status)), payment_note = VALUES(payment_note)"
    );
    $stmt->execute([
        (int) $enrollment['id'],
        (int) $enrollment['user_id'],
        (int) $enrollment['course_id'],
        'Razorpay payment: ' . $paymentId,
    ]);
    if (in_array($enrollment['status'], ['paid', 'completed'], true) || course_fee_amount($enrollment) <= 0) {
        ensure_instant_certificate_for_enrollment($id);
    }

    echo json_encode(['ok' => true, 'redirect' => public_url('dashboard.php')]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'message' => 'Unknown payment type.']);
