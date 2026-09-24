<?php
$title = 'Dashboard';
$elldyAnalytics = true;
require __DIR__ . '/_admin_header.php';
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

<section class="section admin-analytics" aria-labelledby="elldy-dashboard-heading">
    <div class="section-heading admin-analytics-heading">
        <div>
            <p class="eyebrow">Performance overview</p>
            <h2 id="elldy-dashboard-heading">Elldy Dashboard</h2>
            <p class="admin-analytics-description">Key metrics at a glance. Values refresh every 60 seconds.</p>
        </div>
        <div class="admin-analytics-controls">
            <label for="elldy-columns">Cards per row
                <select id="elldy-columns" disabled>
                    <option value="auto">Automatic</option>
                    <option value="2">Two</option>
                    <option value="3" selected>Three</option>
                    <option value="4">Four</option>
                </select>
            </label>
            <button id="elldy-refresh" type="button" disabled>Refresh values</button>
        </div>
    </div>
    <p id="elldy-analytics-status" class="admin-analytics-status" role="status" aria-live="polite">Loading analytics...</p>
    <div id="elldy-analytics"></div>
    <script src="https://elldy.com/static/myapp/js/elldy-embed-sdk.js?v=1.1.0"></script>
    <script src="<?= e(asset_url('assets/js/admin-analytics.js')) ?>"></script>
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
