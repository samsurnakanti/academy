<?php
require_once __DIR__ . '/includes/functions.php';
ensure_certificate_requests_table();

header('Content-Type: application/json');

$secret = payment_callback_secret();
$providedSecret = $_SERVER['HTTP_X_ELLDY_SECRET'] ?? '';

if ($secret === '' || !hash_equals($secret, $providedSecret)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
$appId = trim((string) ($payload['app_id'] ?? ''));
$paymentId = trim((string) ($payload['payment_id'] ?? ''));

if ($appId === '' || $paymentId === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Missing app_id or payment_id']);
    exit;
}

if (preg_match('/^EA-PROGRAM-(\d+)$/', $appId, $matches)) {
    $enrollmentId = (int) $matches[1];
    $stmt = db()->prepare(
        "SELECT e.*, c.certification_fee
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ?"
    );
    $stmt->execute([$enrollmentId]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Enrollment not found']);
        exit;
    }

    $update = db()->prepare(
        "UPDATE enrollments
         SET status = 'paid', payment_note = ?, payment_requested_at = NOW()
         WHERE id = ?"
    );
    $update->execute(['Razorpay payment: ' . $paymentId, $enrollmentId]);

    if (((float) $enrollment['certification_fee']) <= 0) {
        $request = db()->prepare(
            "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status)
             VALUES (?, ?, ?, 'requested')
             ON DUPLICATE KEY UPDATE enrollment_id = enrollment_id"
        );
        $request->execute([
            $enrollmentId,
            (int) $enrollment['user_id'],
            (int) $enrollment['course_id'],
        ]);
        ensure_instant_certificate_for_enrollment($enrollmentId);
    }

    echo json_encode(['ok' => true, 'type' => 'program', 'enrollment_id' => $enrollmentId]);
    exit;
}

if (preg_match('/^EA-CERT-(\d+)$/', $appId, $matches)) {
    $enrollmentId = (int) $matches[1];
    $stmt = db()->prepare(
        "SELECT e.*
         FROM enrollments e
         WHERE e.id = ?"
    );
    $stmt->execute([$enrollmentId]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Enrollment not found']);
        exit;
    }

    $request = db()->prepare(
        "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status, payment_note)
         VALUES (?, ?, ?, 'requested', ?)
         ON DUPLICATE KEY UPDATE payment_note = VALUES(payment_note)"
    );
    $request->execute([
        $enrollmentId,
        (int) $enrollment['user_id'],
        (int) $enrollment['course_id'],
        'Razorpay payment: ' . $paymentId,
    ]);
    $certificate = ensure_instant_certificate_for_enrollment($enrollmentId);

    echo json_encode([
        'ok' => true,
        'type' => 'certificate',
        'enrollment_id' => $enrollmentId,
        'certificate_url' => $certificate['certificate_url'] ?? null,
    ]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'message' => 'Unknown app_id']);
