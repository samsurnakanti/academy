<?php
require_once __DIR__ . '/../includes/functions.php';

$title = 'Live Attendance';
require __DIR__ . '/_admin_header.php';
ensure_material_columns();
ensure_live_session_attendance_table();

$rows = db()->query(
    "SELECT lsa.*, u.name, u.email, u.phone, c.title AS course_title, m.title AS session_title
     FROM live_session_attendance lsa
     JOIN users u ON u.id = lsa.user_id
     JOIN courses c ON c.id = lsa.course_id
     JOIN materials m ON m.id = lsa.material_id
     ORDER BY lsa.last_seen_at DESC, lsa.joined_at DESC"
)->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Live Session Attendance</h1>
    <p>Students appear here after they open an embedded live class from their learning workspace.</p>
</section>

<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Session</th>
                    <th>Joined</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <?= e($row['name']) ?><br>
                            <small><?= e($row['phone'] ?: $row['email']) ?></small>
                        </td>
                        <td><?= e($row['course_title']) ?></td>
                        <td><?= e($row['session_title']) ?></td>
                        <td><?= e(date('d M Y, h:i A', strtotime($row['joined_at']))) ?></td>
                        <td><?= e(date('d M Y, h:i A', strtotime($row['last_seen_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">No live attendance yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
