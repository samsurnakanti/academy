<?php
$title = 'Enrollments';
require __DIR__ . '/_admin_header.php';
ensure_enrollment_detail_columns();
ensure_course_detail_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = $_POST['status'] ?? 'free_access';
    $dailyRemindersEnabled = isset($_POST['daily_reminders_enabled']) && $status !== 'completed' ? 1 : 0;
    $stmt = db()->prepare('UPDATE enrollments SET status = ?, daily_reminders_enabled = ? WHERE id = ?');
    $stmt->execute([$status, $dailyRemindersEnabled, (int) $_POST['id']]);

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
    "SELECT e.*, u.name, u.email, u.phone, c.title, c.fee, c.learning_plan, c.completion_benefits
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
                <tr><th>Trainee</th><th>Program</th><th>BI Learning Plan</th><th>Fee</th><th>Payment Note</th><th>Status</th><th>Daily Alerts</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= e($row['title']) ?></td>
                        <td class="detail-cell">
                            <strong>Trainee details</strong>
                            <p><?= nl2br(e($row['student_background'] ?: '-')) ?></p>
                            <strong>What they will learn</strong>
                            <?= detail_points($row['learning_plan']) ?>
                            <strong>After completion</strong>
                            <?= detail_points($row['completion_benefits']) ?>
                        </td>
                        <td><?= money($row['fee']) ?></td>
                        <td><?= e($row['payment_note'] ?: '-') ?></td>
                        <td><?= e(enrollment_badge($row['status'])) ?></td>
                        <td><?= $row['daily_reminders_enabled'] ? 'On' : 'Off' ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <select name="status">
                                    <?php foreach (['free_access', 'payment_pending', 'paid', 'completed', 'cancelled'] as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>>
                                            <?= e(enrollment_badge($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="check compact-check">
                                    <input type="checkbox" name="daily_reminders_enabled" <?= $row['daily_reminders_enabled'] ? 'checked' : '' ?>>
                                    Daily alerts
                                </label>
                                <button class="button tiny" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
