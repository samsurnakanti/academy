<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($type === 'program') {
    $stmt = db()->prepare(
        "SELECT e.id, c.fee, c.discount_fee
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
    );
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        exit('Payment not found.');
    }

    if (course_fee_amount($row) <= 0) {
        redirect('dashboard.php');
    }

    redirect('razorpay_checkout.php?type=program&id=' . (int) $row['id']);
}

if ($type === 'certificate') {
    $stmt = db()->prepare(
        "SELECT e.id, e.course_id, e.status, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
    );
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        exit('Payment not found.');
    }

    if (!in_array($row['status'], ['paid', 'completed'], true) && course_fee_amount($row) > 0) {
        redirect('pay_redirect.php?type=program&id=' . (int) $row['id']);
    }

    $request = db()->prepare(
        "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = IF(status = 'issued', status, VALUES(status))"
    );
    $request->execute([
        (int) $row['id'],
        (int) $user['id'],
        (int) $row['course_id'],
        certificate_fee_amount($row) > 0 ? 'payment_pending' : 'requested',
    ]);

    if (certificate_fee_amount($row) <= 0) {
        ensure_instant_certificate_for_enrollment((int) $row['id']);
        redirect('dashboard.php');
    }

    redirect('razorpay_checkout.php?type=certificate&id=' . (int) $row['id']);
}

http_response_code(404);
exit('Payment type not found.');
