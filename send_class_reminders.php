<?php
require_once __DIR__ . '/includes/functions.php';
ensure_enrollment_detail_columns();
ensure_course_detail_columns();

$rows = db()->query(
    "SELECT e.id, e.last_reminder_sent_on, u.name, u.phone, c.title, c.fee, c.discount_fee
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     WHERE e.daily_reminders_enabled = 1
       AND e.status = 'paid'
       AND IF(c.discount_fee IS NOT NULL AND c.discount_fee < c.fee, c.discount_fee, c.fee) > 0
       AND (e.last_reminder_sent_on IS NULL OR e.last_reminder_sent_on < CURDATE())"
)->fetchAll();

$update = db()->prepare('UPDATE enrollments SET last_reminder_sent_on = CURDATE() WHERE id = ?');
$sent = 0;

foreach ($rows as $row) {
    if (send_class_reminder_whatsapp($row)) {
        $update->execute([(int) $row['id']]);
        $sent++;
    }
}

echo "Sent {$sent} reminder(s)." . PHP_EOL;
