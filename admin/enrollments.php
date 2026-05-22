<?php
$title = 'Enrollments';
require __DIR__ . '/_admin_header.php';
ensure_enrollment_detail_columns();
ensure_course_detail_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'update';

    if ($action === 'send_reminder') {
        $stmt = db()->prepare(
            "SELECT e.id, u.name, u.phone, c.title
             FROM enrollments e
             JOIN users u ON u.id = e.user_id
             JOIN courses c ON c.id = e.course_id
             WHERE e.id = ? AND e.status != 'cancelled'"
        );
        $stmt->execute([(int) $_POST['id']]);
        $reminderRow = $stmt->fetch();

        if ($reminderRow && send_class_reminder_whatsapp($reminderRow)) {
            flash('success', 'Today class reminder sent.');
        } else {
            flash('error', $_SESSION['whatsapp_send_error'] ?? 'Unable to send class reminder.');
        }

        redirect('enrollments.php');
    }

    if ($action === 'mark_first_session_completed') {
        $stmt = db()->prepare(
            "UPDATE enrollments
             SET status = 'payment_pending'
             WHERE id = ? AND status = 'free_access'"
        );
        $stmt->execute([(int) $_POST['id']]);

        flash('success', 'First session completed. Enrollment moved to payment pending.');
        redirect('enrollments.php');
    }

    $status = $_POST['status'] ?? 'free_access';
    $stmt = db()->prepare('UPDATE enrollments SET status = ? WHERE id = ?');
    $stmt->execute([$status, (int) $_POST['id']]);

    if (in_array($status, ['paid', 'completed'], true)) {
        $request = db()->prepare(
            "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status)
             SELECT id, user_id, course_id, 'requested'
             FROM enrollments
             WHERE id = ?
             ON DUPLICATE KEY UPDATE enrollment_id = enrollment_id"
        );
        $request->execute([(int) $_POST['id']]);
        ensure_instant_certificate_for_enrollment((int) $_POST['id']);
    }

    flash('success', 'Enrollment status updated.');
    redirect('enrollments.php');
}

$rows = db()->query(
    "SELECT e.*, u.name, u.email, u.phone, c.title, c.fee, c.discount_fee
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     ORDER BY e.created_at DESC"
)->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Elldy Enrollments</h1>
</section>
<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>S.No</th><th>Trainee</th><th>Program</th><th>Fee</th><th>Payment Note</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= price_html($row, 'fee', 'discount_fee') ?></td>
                        <td><?= e($row['payment_note'] ?: '-') ?></td>
                        <td><?= e(enrollment_badge($row['status'])) ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="update">
                                <select name="status">
                                    <?php foreach (['free_access', 'payment_pending', 'paid', 'completed', 'cancelled'] as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>>
                                            <?= e(enrollment_badge($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button tiny" type="submit">Save</button>
                            </form>
                            <?php if ($row['status'] !== 'cancelled' && $row['status'] !== 'completed'): ?>
                                <form method="post" class="inline-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="action" value="send_reminder">
                                    <button class="button tiny" type="submit">Send Today Reminder</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'free_access' && course_fee_amount($row) > 0): ?>
                                <form method="post" class="inline-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="action" value="mark_first_session_completed">
                                    <button class="button tiny" type="submit">Mark First Session Completed</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
