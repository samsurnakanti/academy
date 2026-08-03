<?php
$title = 'Enrollments';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
ensure_enrollment_detail_columns();
ensure_course_detail_columns();
ensure_certificate_requests_table();
ensure_learning_progress_table();
ensure_live_session_attendance_table();
$dateFilter = admin_date_filter();
$selectedCourseId = max(0, (int) ($_GET['course_id'] ?? 0));
$selectedActivity = (string) ($_GET['activity'] ?? '');
$allowedActivities = ['paid_course', 'attempted_fee', 'started_classes', 'applied_certificate', 'downloaded_certificate'];
if (!in_array($selectedActivity, $allowedActivities, true)) {
    $selectedActivity = '';
}
$courses = db()->query('SELECT id, title FROM courses ORDER BY title ASC')->fetchAll();

if (!function_exists('enrollment_filter_where')) {
    function enrollment_filter_where(array $dateFilter, int $courseId, string $activity, array &$params): string
    {
        $conditions = [];
        $dateCondition = admin_date_condition('e.created_at', $dateFilter, $params);

        if ($dateCondition !== '') {
            $conditions[] = $dateCondition;
        }

        if ($courseId > 0) {
            $conditions[] = 'e.course_id = ?';
            $params[] = $courseId;
        }

        if ($activity === 'paid_course') {
            $conditions[] = "e.status IN ('paid', 'completed')";
        } elseif ($activity === 'attempted_fee') {
            $conditions[] = 'e.program_payment_attempted_at IS NOT NULL';
        } elseif ($activity === 'started_classes') {
            $conditions[] = "(EXISTS (SELECT 1 FROM learning_progress lp_filter WHERE lp_filter.enrollment_id = e.id) OR EXISTS (SELECT 1 FROM live_session_attendance lsa_filter WHERE lsa_filter.enrollment_id = e.id))";
        } elseif ($activity === 'applied_certificate') {
            $conditions[] = 'EXISTS (SELECT 1 FROM certificate_requests cr_filter WHERE cr_filter.enrollment_id = e.id AND cr_filter.applied_at IS NOT NULL)';
        } elseif ($activity === 'downloaded_certificate') {
            $conditions[] = 'EXISTS (SELECT 1 FROM certificate_requests cr_filter WHERE cr_filter.enrollment_id = e.id AND cr_filter.download_count > 0)';
        }

        return $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    }
}

if (!function_exists('csv_excel_text')) {
    function csv_excel_text(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : "\t" . $value;
    }
}

if (!function_exists('admin_enrollment_short_datetime')) {
    function admin_enrollment_short_datetime(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : date('d M Y, h:i A', strtotime($value));
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    $params = [];
    $where = enrollment_filter_where($dateFilter, $selectedCourseId, $selectedActivity, $params);
    $stmt = db()->prepare(
        "SELECT
            e.id AS enrollment_id,
            e.created_at AS enrolled_at,
            e.status AS enrollment_status,
            e.payment_note AS enrollment_payment_note,
            e.payment_requested_at,
            e.program_payment_attempted_at,
            e.student_background,
            e.learning_goals,
            e.completion_expectation,
            e.daily_reminders_enabled,
            e.last_reminder_sent_on,
            u.id AS user_id,
            u.name AS candidate_name,
            u.email AS candidate_email,
            u.phone AS candidate_phone,
            u.created_at AS candidate_registered_at,
            c.id AS course_id,
            c.title AS programme,
            c.duration,
            c.delivery_type,
            c.fee AS programme_fee,
            c.discount_fee AS programme_discount_fee,
            c.certification_fee,
            c.certificate_discount_fee,
            cr.status AS certificate_status,
            cr.payment_note AS certificate_payment_note,
            cr.dashboard_url,
            cr.dashboard_review_status,
            cr.dashboard_review_note,
            cr.dashboard_submitted_at,
            cr.certificate_url,
            cr.certificate_code,
            cr.issued_at,
            cr.applied_at AS certificate_applied_at,
            cr.requested_at AS certificate_requested_at,
            cr.downloaded_at AS certificate_downloaded_at,
            COALESCE(cr.download_count, 0) AS certificate_download_count,
            COALESCE(attendance.live_sessions_attended, 0) AS live_sessions_attended,
            attendance.last_live_session_at,
            COALESCE(progress.total_items, 0) AS learning_items_started,
            COALESCE(progress.completed_items, 0) AS learning_items_completed,
            COALESCE(progress.average_progress_percent, 0) AS average_progress_percent
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN certificate_requests cr ON cr.enrollment_id = e.id
         LEFT JOIN (
            SELECT enrollment_id,
                   COUNT(*) AS live_sessions_attended,
                   MAX(last_seen_at) AS last_live_session_at
            FROM live_session_attendance
            GROUP BY enrollment_id
         ) attendance ON attendance.enrollment_id = e.id
         LEFT JOIN (
            SELECT enrollment_id,
                   COUNT(*) AS total_items,
                   SUM(is_completed = 1) AS completed_items,
                   ROUND(AVG(progress_percent), 2) AS average_progress_percent
            FROM learning_progress
            GROUP BY enrollment_id
         ) progress ON progress.enrollment_id = e.id
         {$where}
         ORDER BY c.title ASC, e.created_at DESC"
    );
    $stmt->execute($params);
    $exportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filenameParts = ['enrollments'];
    if ($selectedCourseId > 0) {
        foreach ($courses as $course) {
            if ((int) $course['id'] === $selectedCourseId) {
                $filenameParts[] = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $course['title']));
                break;
            }
        }
    }
    if ($selectedActivity !== '') {
        $filenameParts[] = $selectedActivity;
    }
    if ($dateFilter['from'] !== '' && $dateFilter['to'] !== '') {
        $filenameParts[] = $dateFilter['from'] . '-to-' . $dateFilter['to'];
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . implode('-', array_filter($filenameParts)) . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $handle = fopen('php://output', 'w');
    fputcsv($handle, [
        'Enrollment ID',
        'Enrolled At',
        'Enrollment Status',
        'Enrollment Status Label',
        'Payment Note',
        'Payment Requested At',
        'Course Payment Attempted',
        'Candidate ID',
        'Candidate Name',
        'Candidate Email',
        'Candidate Phone',
        'Candidate Registered At',
        'Programme ID',
        'Programme',
        'Duration',
        'Delivery Type',
        'Programme Fee',
        'Programme Discount Fee',
        'Effective Programme Fee',
        'Certification Fee',
        'Certificate Discount Fee',
        'Effective Certificate Fee',
        'Student Background',
        'Learning Goals',
        'Completion Expectation',
        'Daily Reminders Enabled',
        'Last Reminder Sent On',
        'Certificate Status',
        'Certificate Payment Note',
        'Dashboard URL',
        'Dashboard Review Status',
        'Dashboard Review Note',
        'Dashboard Submitted At',
        'Certificate URL',
        'Certificate Code',
        'Certificate Issued At',
        'Certificate Applied At',
        'Certificate Requested At',
        'Certificate Downloaded At',
        'Certificate Download Count',
        'Live Sessions Attended',
        'Last Live Session At',
        'Learning Items Started',
        'Learning Items Completed',
        'Average Progress Percent',
    ]);

    foreach ($exportRows as $row) {
        fputcsv($handle, [
            $row['enrollment_id'],
            $row['enrolled_at'],
            $row['enrollment_status'],
            enrollment_badge((string) $row['enrollment_status']),
            $row['enrollment_payment_note'],
            $row['payment_requested_at'],
            $row['program_payment_attempted_at'],
            $row['user_id'],
            $row['candidate_name'],
            $row['candidate_email'],
            csv_excel_text($row['candidate_phone']),
            $row['candidate_registered_at'],
            $row['course_id'],
            $row['programme'],
            $row['duration'],
            $row['delivery_type'],
            $row['programme_fee'],
            $row['programme_discount_fee'],
            course_fee_amount(['fee' => $row['programme_fee'], 'discount_fee' => $row['programme_discount_fee']]),
            $row['certification_fee'],
            $row['certificate_discount_fee'],
            certificate_fee_amount(['certification_fee' => $row['certification_fee'], 'certificate_discount_fee' => $row['certificate_discount_fee']]),
            $row['student_background'],
            $row['learning_goals'],
            $row['completion_expectation'],
            ((int) $row['daily_reminders_enabled'] === 1) ? 'Yes' : 'No',
            $row['last_reminder_sent_on'],
            $row['certificate_status'],
            $row['certificate_payment_note'],
            $row['dashboard_url'],
            $row['dashboard_review_status'],
            $row['dashboard_review_note'],
            $row['dashboard_submitted_at'],
            $row['certificate_url'],
            $row['certificate_code'],
            $row['issued_at'],
            $row['certificate_applied_at'],
            $row['certificate_requested_at'],
            $row['certificate_downloaded_at'],
            $row['certificate_download_count'],
            $row['live_sessions_attended'],
            $row['last_live_session_at'],
            $row['learning_items_started'],
            $row['learning_items_completed'],
            $row['average_progress_percent'],
        ]);
    }

    fclose($handle);
    exit;
}

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

require __DIR__ . '/_admin_header.php';

$params = [];
$where = enrollment_filter_where($dateFilter, $selectedCourseId, $selectedActivity, $params);
$stmt = db()->prepare(
    "SELECT e.*, u.name, u.email, u.phone, c.title, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee,
            cr.status AS certificate_status,
            cr.applied_at AS certificate_applied_at,
            cr.requested_at AS certificate_requested_at,
            cr.dashboard_submitted_at,
            cr.dashboard_review_status,
            cr.downloaded_at AS certificate_downloaded_at,
            COALESCE(cr.download_count, 0) AS certificate_download_count,
            COALESCE(attendance.live_sessions_attended, 0) AS live_sessions_attended,
            attendance.last_live_session_at,
            COALESCE(progress.total_items, 0) AS learning_items_started,
            COALESCE(progress.completed_items, 0) AS learning_items_completed,
            COALESCE(progress.average_progress_percent, 0) AS average_progress_percent
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     LEFT JOIN certificate_requests cr ON cr.enrollment_id = e.id
     LEFT JOIN (
        SELECT enrollment_id,
               COUNT(*) AS live_sessions_attended,
               MAX(last_seen_at) AS last_live_session_at
        FROM live_session_attendance
        GROUP BY enrollment_id
     ) attendance ON attendance.enrollment_id = e.id
     LEFT JOIN (
        SELECT enrollment_id,
               COUNT(*) AS total_items,
               SUM(is_completed = 1) AS completed_items,
               ROUND(AVG(progress_percent), 2) AS average_progress_percent
        FROM learning_progress
        GROUP BY enrollment_id
     ) progress ON progress.enrollment_id = e.id
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
    <?php
    $dateFilterHidden = [];
    if ($selectedCourseId > 0) {
        $dateFilterHidden['course_id'] = $selectedCourseId;
    }
    if ($selectedActivity !== '') {
        $dateFilterHidden['activity'] = $selectedActivity;
    }
    ?>
    <?php require __DIR__ . '/_date_filter.php'; ?>
</section>
<section class="section compact-section">
    <form class="date-filter-form" method="get" action="enrollments.php">
        <input type="hidden" name="range" value="<?= e($dateFilter['range']) ?>">
        <?php if ($dateFilter['range'] === 'custom'): ?>
            <input type="hidden" name="from" value="<?= e($dateFilter['from']) ?>">
            <input type="hidden" name="to" value="<?= e($dateFilter['to']) ?>">
        <?php endif; ?>
        <label>Programme
            <select name="course_id">
                <option value="0">All programmes</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>>
                        <?= e($course['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Filter
            <select name="activity">
                <option value="">All students</option>
                <option value="paid_course" <?= $selectedActivity === 'paid_course' ? 'selected' : '' ?>>Paid course</option>
                <option value="attempted_fee" <?= $selectedActivity === 'attempted_fee' ? 'selected' : '' ?>>Attempted fee payment</option>
                <option value="started_classes" <?= $selectedActivity === 'started_classes' ? 'selected' : '' ?>>Started classes</option>
                <option value="applied_certificate" <?= $selectedActivity === 'applied_certificate' ? 'selected' : '' ?>>Applied certificate</option>
                <option value="downloaded_certificate" <?= $selectedActivity === 'downloaded_certificate' ? 'selected' : '' ?>>Downloaded certificate</option>
            </select>
        </label>
        <button class="button small" type="submit">View</button>
        <a class="button small" href="<?= e(admin_date_filter_url('enrollments.php', ['course_id' => $selectedCourseId, 'activity' => $selectedActivity, 'export' => 'csv'], $dateFilter)) ?>">Export CSV</a>
    </form>
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
                <tr><th><input type="checkbox" data-select-all="bulk-reminder-form" aria-label="Select all students"></th><th>S.No</th><th>Trainee</th><th>Current Profile</th><th>Program</th><th>Fee</th><th>Class / Live</th><th>Course Payment</th><th>Certificate</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $coursePaid = in_array($row['status'], ['paid', 'completed'], true) || course_fee_amount($row) <= 0;
                    $courseAttempted = trim((string) ($row['program_payment_attempted_at'] ?? '')) !== '';
                    $certificateApplied = trim((string) ($row['certificate_applied_at'] ?? '')) !== '';
                    $certificateDownloaded = (int) ($row['certificate_download_count'] ?? 0) > 0;
                    ?>
                    <tr>
                        <td>
                            <?php if ($row['status'] !== 'cancelled' && $row['status'] !== 'completed'): ?>
                                <input type="checkbox" name="enrollment_ids[]" value="<?= (int) $row['id'] ?>" form="bulk-reminder-form" aria-label="Select <?= e($row['name']) ?>">
                            <?php endif; ?>
                        </td>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= nl2br(e($row['student_background'] ?: '-')) ?></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= price_html($row, 'fee', 'discount_fee') ?></td>
                        <td>
                            <?php if ((int) $row['live_sessions_attended'] > 0): ?>
                                Attended <?= (int) $row['live_sessions_attended'] ?> live session(s)<br>
                                <small><?= e(admin_enrollment_short_datetime($row['last_live_session_at'])) ?></small>
                            <?php elseif ((int) $row['learning_items_started'] > 0): ?>
                                Started class / video<br>
                                <small><?= (int) $row['learning_items_completed'] ?> completed, <?= e((string) $row['average_progress_percent']) ?>% avg</small>
                            <?php else: ?>
                                Not attended
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($coursePaid): ?>
                                Paid<br>
                                <small><?= e(admin_enrollment_short_datetime($row['payment_requested_at'] ?? null)) ?></small>
                            <?php elseif ($courseAttempted): ?>
                                Attempted<br>
                                <small><?= e(admin_enrollment_short_datetime($row['program_payment_attempted_at'])) ?></small>
                            <?php else: ?>
                                Not paid
                            <?php endif; ?>
                            <?php if (!empty($row['payment_note'])): ?>
                                <br><small><?= e($row['payment_note']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($certificateDownloaded): ?>
                                Downloaded <?= (int) $row['certificate_download_count'] ?> time(s)<br>
                                <small><?= e(admin_enrollment_short_datetime($row['certificate_downloaded_at'])) ?></small>
                            <?php elseif ($certificateApplied): ?>
                                Applied<br>
                                <small><?= e(admin_enrollment_short_datetime($row['certificate_applied_at'])) ?> | <?= e(certificate_badge((string) ($row['certificate_status'] ?? 'requested'))) ?></small>
                            <?php else: ?>
                                Not applied
                            <?php endif; ?>
                            <?php if (!empty($row['dashboard_submitted_at'])): ?>
                                <br><small>Dashboard <?= e(dashboard_review_badge($row['dashboard_review_status'] ?? 'not_submitted')) ?></small>
                            <?php endif; ?>
                        </td>
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
