<?php
$title = 'Enrollments';
require __DIR__ . '/_admin_header.php';
ensure_enrollment_detail_columns();
ensure_course_detail_columns();
$dateFilter = admin_date_filter();

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

    if ($action === 'bulk_send_reminders') {
        $recipientMode = $_POST['recipient_mode'] ?? 'selected';
        $selectedIds = array_values(array_filter(array_map('intval', $_POST['enrollment_ids'] ?? [])));
        $params = [];
        $idCondition = '';

        if ($recipientMode !== 'all') {
            if (!$selectedIds) {
                flash('error', 'Select at least one enrollment or choose all students.');
                redirect('enrollments.php');
            }

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $idCondition = " AND e.id IN ({$placeholders})";
            $params = $selectedIds;
        }

        $stmt = db()->prepare(
            "SELECT e.id, u.name, u.phone, c.title
             FROM enrollments e
             JOIN users u ON u.id = e.user_id
             JOIN courses c ON c.id = e.course_id
             WHERE e.status NOT IN ('cancelled', 'completed'){$idCondition}
             ORDER BY e.created_at DESC"
        );
        $stmt->execute($params);
        $reminderRows = $stmt->fetchAll();

        $sent = 0;
        $failed = 0;
        $missingPhone = 0;
        foreach ($reminderRows as $reminderRow) {
            if (trim((string) $reminderRow['phone']) === '') {
                $missingPhone++;
                continue;
            }

            if (send_class_reminder_whatsapp($reminderRow)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $summary = "{$sent} reminder(s) sent.";
        if ($failed > 0) {
            $summary .= " {$failed} failed.";
        }
        if ($missingPhone > 0) {
            $summary .= " {$missingPhone} skipped without phone.";
        }
        flash($sent > 0 ? 'success' : 'error', $summary);
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

$params = [];
$dateCondition = admin_date_condition('e.created_at', $dateFilter, $params);
$where = $dateCondition === '' ? '' : "WHERE {$dateCondition}";
$stmt = db()->prepare(
    "SELECT e.*, u.name, u.email, u.phone, c.title, c.fee, c.discount_fee
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     {$where}
     ORDER BY e.created_at DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Elldy Enrollments</h1>
</section>
<section class="section compact-section">
    <?php require __DIR__ . '/_date_filter.php'; ?>
</section>
<section class="section compact-section">
    <form method="post" class="form-card" id="bulk-reminder-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="bulk_send_reminders">
        <h2>Send Class Reminders</h2>
        <fieldset>
            <legend>Recipients</legend>
            <label>Send to
                <select name="recipient_mode">
                    <option value="selected">Selected students only</option>
                    <option value="all">All active students</option>
                </select>
            </label>
        </fieldset>
        <div class="materials-form-actions">
            <button class="button primary" type="submit" data-confirm="Send class reminders to the chosen students?">Send Reminders</button>
        </div>
    </form>
</section>
<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th><input type="checkbox" data-select-all="bulk-reminder-form" aria-label="Select all students"></th><th>S.No</th><th>Trainee</th><th>Program</th><th>Fee</th><th>Payment Note</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td>
                            <?php if ($row['status'] !== 'cancelled' && $row['status'] !== 'completed'): ?>
                                <input type="checkbox" name="enrollment_ids[]" value="<?= (int) $row['id'] ?>" form="bulk-reminder-form" aria-label="Select <?= e($row['name']) ?>">
                            <?php endif; ?>
                        </td>
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
<script>
document.querySelectorAll('[data-select-all]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
        const formId = toggle.dataset.selectAll;
        document.querySelectorAll('input[type="checkbox"][form="' + formId + '"]').forEach((box) => {
            box.checked = toggle.checked;
        });
    });
});
</script>
<?php require __DIR__ . '/_admin_footer.php'; ?>
