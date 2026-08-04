<?php
$title = 'Learning Progress';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_learning_progress_table();
$dateFilter = admin_date_filter();
$selectedCourseId = max(0, (int) ($_GET['course_id'] ?? 0));
$courses = db()->query('SELECT id, title FROM courses ORDER BY title ASC')->fetchAll();

if (!function_exists('progress_filter_where')) {
    function progress_filter_where(array $dateFilter, int $courseId, array &$params): string
    {
        $conditions = ["e.status != 'cancelled'"];

        if ($courseId > 0) {
            $conditions[] = 'e.course_id = ?';
            $params[] = $courseId;
        }

        $dateCondition = admin_date_condition('progress_counts.last_activity', $dateFilter, $params);
        if ($dateCondition !== '') {
            $conditions[] = $dateCondition;
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);

    if ($action === 'send_certificate_whatsapp') {
        $stmt = db()->prepare(
            "SELECT
                e.id AS enrollment_id,
                u.name,
                u.phone,
                c.title AS course_title,
                COALESCE(video_counts.total_videos, 0) AS total_videos,
                COALESCE(progress_counts.completed_videos, 0) AS completed_videos
             FROM enrollments e
             JOIN users u ON u.id = e.user_id
             JOIN courses c ON c.id = e.course_id
             LEFT JOIN (
                SELECT course_id, COUNT(*) AS total_videos
                FROM materials
                WHERE material_type = 'video'
                GROUP BY course_id
             ) video_counts ON video_counts.course_id = e.course_id
             LEFT JOIN (
                SELECT enrollment_id, SUM(is_completed = 1) AS completed_videos
                FROM learning_progress
                GROUP BY enrollment_id
             ) progress_counts ON progress_counts.enrollment_id = e.id
             WHERE e.id = ? AND e.status != 'cancelled'"
        );
        $stmt->execute([$enrollmentId]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('error', 'WhatsApp was not sent. Enrollment was not found.');
        } elseif (trim((string) $row['phone']) === '') {
            flash('error', 'WhatsApp was not sent. This learner does not have a phone number.');
        } elseif (send_certificate_eligible_whatsapp($row)) {
            flash('success', 'Certificate eligibility WhatsApp sent.');
        } else {
            flash('error', $_SESSION['whatsapp_send_error'] ?? 'Unable to send certificate eligibility WhatsApp.');
        }
    }

    if ($action === 'bulk_send_certificate_whatsapp') {
        $recipientMode = $_POST['recipient_mode'] ?? 'selected';
        $selectedIds = array_values(array_filter(array_map('intval', $_POST['enrollment_ids'] ?? [])));
        $params = [];
        $where = progress_filter_where($dateFilter, $selectedCourseId, $params);

        if ($recipientMode !== 'all') {
            if (!$selectedIds) {
                flash('error', 'Select at least one learner or choose all learners.');
                redirect('progress.php');
            }

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $where .= " AND e.id IN ({$placeholders})";
            $params = array_merge($params, $selectedIds);
        }

        $stmt = db()->prepare(
            "SELECT
                e.id AS enrollment_id,
                u.name,
                u.phone,
                c.title AS course_title,
                COALESCE(video_counts.total_videos, 0) AS total_videos,
                COALESCE(progress_counts.completed_videos, 0) AS completed_videos
             FROM enrollments e
             JOIN users u ON u.id = e.user_id
             JOIN courses c ON c.id = e.course_id
             LEFT JOIN (
                SELECT course_id, COUNT(*) AS total_videos
                FROM materials
                WHERE material_type = 'video'
                GROUP BY course_id
             ) video_counts ON video_counts.course_id = e.course_id
             LEFT JOIN (
                SELECT enrollment_id, SUM(is_completed = 1) AS completed_videos
                FROM learning_progress
                GROUP BY enrollment_id
             ) progress_counts ON progress_counts.enrollment_id = e.id
             {$where}
             ORDER BY e.created_at DESC"
        );
        $stmt->execute($params);
        $certificateRows = $stmt->fetchAll();

        $sent = 0;
        $failed = 0;
        $missingPhone = 0;
        foreach ($certificateRows as $row) {
            if (trim((string) $row['phone']) === '') {
                $missingPhone++;
                continue;
            }

            if (send_certificate_eligible_whatsapp($row)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $summary = "{$sent} certificate alert(s) sent.";
        if ($failed > 0) {
            $summary .= " {$failed} failed.";
        }
        if ($missingPhone > 0) {
            $summary .= " {$missingPhone} skipped without phone.";
        }
        flash($sent > 0 ? 'success' : 'error', $summary);
    }

    redirect('progress.php');
}

require __DIR__ . '/_admin_header.php';

$params = [];
$where = progress_filter_where($dateFilter, $selectedCourseId, $params);
$stmt = db()->prepare(
    "SELECT
        e.id AS enrollment_id,
        e.status,
        u.name,
        u.email,
        u.phone,
        c.title AS course_title,
        COALESCE(video_counts.total_videos, 0) AS total_videos,
        COALESCE(progress_counts.completed_videos, 0) AS completed_videos,
        COALESCE(progress_counts.progress_sum, 0) AS progress_sum,
        progress_counts.last_activity
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     LEFT JOIN (
        SELECT course_id, COUNT(*) AS total_videos
        FROM materials
        WHERE material_type = 'video'
        GROUP BY course_id
     ) video_counts ON video_counts.course_id = e.course_id
     LEFT JOIN (
        SELECT enrollment_id,
            SUM(is_completed = 1) AS completed_videos,
            SUM(progress_percent) AS progress_sum,
            MAX(updated_at) AS last_activity
        FROM learning_progress
        GROUP BY enrollment_id
     ) progress_counts ON progress_counts.enrollment_id = e.id
     {$where}
     ORDER BY progress_counts.last_activity DESC, e.created_at DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Video Progress</h1>
    <p>Track watched videos, completed videos, and learners who finished all published course videos.</p>
</section>

<section class="section compact-section">
    <form class="date-filter-form" method="get" action="progress.php">
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
        <label>Range
            <select name="range" id="date-filter-range">
                <option value="all" <?= $dateFilter['range'] === 'all' ? 'selected' : '' ?>>All time</option>
                <option value="today" <?= $dateFilter['range'] === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="yesterday" <?= $dateFilter['range'] === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                <option value="this_week" <?= $dateFilter['range'] === 'this_week' ? 'selected' : '' ?>>This week</option>
                <option value="this_month" <?= $dateFilter['range'] === 'this_month' ? 'selected' : '' ?>>This month</option>
                <option value="custom" <?= $dateFilter['range'] === 'custom' ? 'selected' : '' ?>>Custom dates</option>
            </select>
        </label>
        <label>From
            <input type="date" name="from" value="<?= e($dateFilter['from']) ?>">
        </label>
        <label>To
            <input type="date" name="to" value="<?= e($dateFilter['to']) ?>">
        </label>
        <button class="button small" type="submit">Apply</button>
        <a class="button small" href="progress.php">Clear</a>
    </form>
</section>

<section class="section compact-section">
    <form method="post" class="date-filter-form admin-bulk-action-form" id="bulk-certificate-form" action="<?= e(admin_date_filter_url('progress.php', ['course_id' => $selectedCourseId], $dateFilter)) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="bulk_send_certificate_whatsapp">
        <h2>Send Certificate Alerts</h2>
        <fieldset>
            <legend>Recipients</legend>
            <label>Send to
                <select name="recipient_mode">
                    <option value="selected">Selected students only</option>
                    <option value="all">All listed learners</option>
                </select>
            </label>
        </fieldset>
        <div class="materials-form-actions">
            <button class="button primary" type="submit" data-confirm="Send certificate eligibility WhatsApp alerts to the chosen learners?">Send Certificate Alerts</button>
        </div>
    </form>
</section>

<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" data-select-all="bulk-certificate-form" aria-label="Select all learners"></th>
                    <th>S.No</th>
                    <th>Trainee</th>
                    <th>Program</th>
                    <th>Videos</th>
                    <th>Average</th>
                    <th>Status</th>
                    <th>Last Activity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $totalVideos = (int) $row['total_videos'];
                    $completedVideos = (int) $row['completed_videos'];
                    $avgProgress = $totalVideos > 0 ? ((float) $row['progress_sum'] / $totalVideos) : 0;
                    $completion = enrollment_learning_completion((int) $row['enrollment_id']);
                    $hasCompletedAll = $completion['is_complete'];
                    if ($totalVideos === 0 && $hasCompletedAll) {
                        $totalVideos = (int) $completion['total'];
                        $completedVideos = (int) $completion['completed'];
                        $avgProgress = 100;
                    }
                    ?>
                    <tr>
                        <td><input type="checkbox" name="enrollment_ids[]" value="<?= (int) $row['enrollment_id'] ?>" form="bulk-certificate-form" aria-label="Select <?= e($row['name']) ?>"></td>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= e($row['course_title']) ?><br><small><?= e(enrollment_badge($row['status'])) ?></small></td>
                        <td><?= $completedVideos ?> / <?= $totalVideos ?></td>
                        <td><?= (int) round($avgProgress) ?>%</td>
                        <td>
                            <span class="progress-chip <?= $hasCompletedAll ? 'complete' : '' ?>">
                                <?= $hasCompletedAll ? 'All videos completed' : 'In progress' ?>
                            </span>
                        </td>
                        <td><?= e($row['last_activity'] ? date('d M Y, h:i A', strtotime((string) $row['last_activity'])) : '-') ?></td>
                        <td>
                            <form method="post" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="send_certificate_whatsapp">
                                <input type="hidden" name="enrollment_id" value="<?= (int) $row['enrollment_id'] ?>">
                                <button class="button tiny" type="submit" data-confirm="Send certificate eligibility WhatsApp to this learner?">Send WhatsApp</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="9">No progress records yet.</td></tr>
                <?php endif; ?>
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
