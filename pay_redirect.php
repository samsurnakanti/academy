<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();
ensure_enrollment_detail_columns();

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($type === 'program') {
    $stmt = db()->prepare(
        "SELECT e.id, u.phone, c.fee, c.discount_fee, c.international_currency, c.international_fee, c.international_discount_fee
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
    );
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        exit('Payment not found.');
    }

    if (payment_amount($row, 'program') <= 0) {
        redirect('dashboard.php');
    }

    $attempt = db()->prepare('UPDATE enrollments SET program_payment_attempted_at = NOW() WHERE id = ? AND user_id = ?');
    $attempt->execute([(int) $row['id'], (int) $user['id']]);

    redirect('razorpay_checkout.php?type=program&id=' . (int) $row['id']);
}

if ($type === 'certificate') {
    $stmt = db()->prepare(
        "SELECT e.id, e.course_id, e.status, u.phone, c.fee, c.discount_fee, c.international_currency, c.international_fee, c.international_discount_fee, c.certification_fee, c.certificate_discount_fee, c.international_certification_fee, c.international_certificate_discount_fee
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
    );
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        exit('Payment not found.');
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
        payment_amount($row, 'certificate') > 0 ? 'payment_pending' : 'requested',
    ]);

    if (payment_amount($row, 'certificate') <= 0) {
        if (!in_array($row['status'], ['paid', 'completed'], true) && payment_amount($row, 'program') > 0) {
            redirect('pay_redirect.php?type=program&id=' . (int) $row['id']);
        }

        ensure_instant_certificate_for_enrollment((int) $row['id']);
        redirect('dashboard.php');
    }

    redirect('razorpay_checkout.php?type=certificate&id=' . (int) $row['id']);
}

http_response_code(404);
exit('Payment type not found.');
