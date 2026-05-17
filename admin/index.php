<?php
$title = 'Dashboard';
require __DIR__ . '/_admin_header.php';

$stats = [
    'courses' => db()->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'active_courses' => db()->query('SELECT COUNT(*) FROM courses WHERE is_active = 1')->fetchColumn(),
    'trainees' => db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'enrollments' => db()->query('SELECT COUNT(*) FROM enrollments')->fetchColumn(),
];

$latest = db()->query(
    "SELECT e.*, u.name, u.email, c.title
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     ORDER BY e.created_at DESC
     LIMIT 10"
)->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Control center</p>
    <h1>Elldy Academy Admin</h1>
    <p>Manage analytics programs, BI learning outcomes, materials, and trainee enrollments connected to the Elldy platform.</p>
</section>

<section class="admin-stats">
    <div><strong><?= (int) $stats['courses'] ?></strong><span>Total programs</span></div>
    <div><strong><?= (int) $stats['active_courses'] ?></strong><span>Active programs</span></div>
    <div><strong><?= (int) $stats['trainees'] ?></strong><span>Trainees</span></div>
    <div><strong><?= (int) $stats['enrollments'] ?></strong><span>Enrollments</span></div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Latest Enrollments</h2>
        <a href="enrollments.php">Manage all</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>S.No</th><th>Trainee</th><th>Program</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($latest as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?></small></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= e(enrollment_badge($row['status'])) ?></td>
                        <td><?= e(date('d M Y', strtotime($row['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
