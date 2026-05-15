<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($type === 'program') {
    $stmt = db()->prepare(
        "SELECT e.id, c.fee
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

    $update = db()->prepare('UPDATE enrollments SET status = ?, payment_requested_at = NOW() WHERE id = ? AND status != ?');
    $update->execute(['payment_pending', (int) $row['id'], 'paid']);

    redirect(elldy_payment_url('EA-PROGRAM-' . (int) $row['id'], $row['fee']));
}

if ($type === 'certificate') {
    $stmt = db()->prepare(
        "SELECT e.id, e.course_id, c.certification_fee
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

    $request = db()->prepare(
        "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = IF(status = 'issued', status, VALUES(status))"
    );
    $request->execute([
        (int) $row['id'],
        (int) $user['id'],
        (int) $row['course_id'],
        ((float) $row['certification_fee']) > 0 ? 'payment_pending' : 'requested',
    ]);

    redirect(elldy_payment_url('EA-CERT-' . (int) $row['id'], $row['certification_fee']));
}

http_response_code(404);
exit('Payment type not found.');
