<?php
$title = 'Learning Progress';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_learning_progress_table();

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

    redirect('progress.php');
}

require __DIR__ . '/_admin_header.php';

$rows = db()->query(
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
     WHERE e.status != 'cancelled'
     ORDER BY progress_counts.last_activity DESC, e.created_at DESC"
)->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Video Progress</h1>
    <p>Track watched videos, completed videos, and learners who finished all published course videos.</p>
</section>

<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
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
                    <tr><td colspan="8">No progress records yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
