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

<section class="section" aria-labelledby="elldy-dashboard-heading">
    <div class="section-heading">
        <h2 id="elldy-dashboard-heading">Elldy Dashboard</h2>
    </div>
    <p id="elldy-analytics-status" role="status">Loading analytics…</p>
    <div id="elldy-analytics"></div>
    <script src="https://elldy.com/static/myapp/js/elldy-embed-sdk.js"></script>
    <script>
    (() => {
        const status = document.getElementById('elldy-analytics-status');
        const showError = () => {
            status.hidden = false;
            status.textContent = 'Analytics could not be loaded. Check the server configuration or try again later.';
        };
        try {
            window.ElldyEmbed.mount({
                container: '#elldy-analytics',
                frameUrl: 'https://elldy.com/secure-embed/2716092e-3b6d-4f26-a333-bc188a8f5cd1/frame/',
                width: '100%',
                height: 600,
                getToken: async () => {
                    const response = await fetch('elldy_token.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'X-CSRFToken': document.querySelector('meta[name="csrf-token"]').content}
                    });
                    if (!response.ok) {
                        showError();
                        throw new Error('Dashboard access denied');
                    }
                    const token = await response.json();
                    status.hidden = true;
                    return token;
                },
                onError: showError
            });
        } catch (error) {
            showError();
        }
    })();
    </script>
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
