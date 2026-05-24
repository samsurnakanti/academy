<?php
$title = 'Insights';
require __DIR__ . '/_admin_header.php';
ensure_app_analytics_tables();

$view = (string) ($_GET['view'] ?? 'overview');
$viewOptions = [
    'overview' => 'Overview',
    'app_installs' => 'Installed apps',
    'installed_users' => 'Installed users',
    'installed_launches_today' => 'App opens today',
    'active_now' => 'Active now',
    'active_today' => 'Active today',
    'active_7_days' => 'Active 7 days',
    'return_users' => 'Return users',
    'repeat_login_users' => 'Repeat logins',
];

if (!isset($viewOptions[$view])) {
    $view = 'overview';
}

$stats = [
    'app_installs' => db()->query('SELECT COUNT(*) FROM app_installs')->fetchColumn(),
    'installed_users' => db()->query('SELECT COUNT(DISTINCT user_id) FROM app_installs WHERE user_id IS NOT NULL')->fetchColumn(),
    'installed_launches_today' => db()->query('SELECT COUNT(*) FROM app_installs WHERE DATE(last_seen_at) = CURDATE()')->fetchColumn(),
    'active_now' => db()->query("SELECT COUNT(*) FROM app_user_activity WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetchColumn(),
    'active_today' => db()->query('SELECT COUNT(*) FROM app_user_activity WHERE DATE(last_active_at) = CURDATE()')->fetchColumn(),
    'active_7_days' => db()->query("SELECT COUNT(*) FROM app_user_activity WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'return_users' => db()->query('SELECT COUNT(*) FROM app_user_activity WHERE return_count > 0')->fetchColumn(),
    'repeat_login_users' => db()->query('SELECT COUNT(*) FROM app_user_activity WHERE login_count > 1')->fetchColumn(),
];

$activityWhere = '';
$installWhere = '';

switch ($view) {
    case 'active_now':
        $activityWhere = "WHERE aua.last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        break;
    case 'active_today':
        $activityWhere = 'WHERE DATE(aua.last_active_at) = CURDATE()';
        break;
    case 'active_7_days':
        $activityWhere = "WHERE aua.last_active_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'return_users':
        $activityWhere = 'WHERE aua.return_count > 0';
        break;
    case 'repeat_login_users':
        $activityWhere = 'WHERE aua.login_count > 1';
        break;
    case 'installed_users':
        $activityWhere = 'WHERE aua.last_installed_app_at IS NOT NULL';
        break;
    case 'installed_launches_today':
        $installWhere = 'WHERE DATE(ai.last_seen_at) = CURDATE()';
        break;
}

$recentUsers = db()->query(
    "SELECT u.name, u.email, u.phone, aua.login_count, aua.return_count, aua.last_login_at, aua.last_active_at, aua.last_return_at, aua.last_installed_app_at
     FROM app_user_activity aua
     JOIN users u ON u.id = aua.user_id
     {$activityWhere}
     ORDER BY aua.last_active_at DESC
     LIMIT " . ($view === 'overview' ? '25' : '200')
)->fetchAll();

$recentInstalls = db()->query(
    "SELECT ai.*, u.name, u.email
     FROM app_installs ai
     LEFT JOIN users u ON u.id = ai.user_id
     {$installWhere}
     ORDER BY ai.last_seen_at DESC
     LIMIT " . ($view === 'overview' ? '25' : '200')
)->fetchAll();

$showUserTable = in_array($view, ['overview', 'active_now', 'active_today', 'active_7_days', 'return_users', 'repeat_login_users', 'installed_users'], true);
$showInstallTable = in_array($view, ['overview', 'app_installs', 'installed_launches_today'], true);
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>App & Login Insights</h1>
    <p>Track app installs, login activity, active trainees, and returning users.</p>
</section>

<section class="admin-stats">
    <a class="<?= $view === 'app_installs' ? 'active' : '' ?>" href="insights.php?view=app_installs"><strong><?= (int) $stats['app_installs'] ?></strong><span>Installed apps</span></a>
    <a class="<?= $view === 'installed_users' ? 'active' : '' ?>" href="insights.php?view=installed_users"><strong><?= (int) $stats['installed_users'] ?></strong><span>Installed users</span></a>
    <a class="<?= $view === 'installed_launches_today' ? 'active' : '' ?>" href="insights.php?view=installed_launches_today"><strong><?= (int) $stats['installed_launches_today'] ?></strong><span>App opens today</span></a>
    <a class="<?= $view === 'active_now' ? 'active' : '' ?>" href="insights.php?view=active_now"><strong><?= (int) $stats['active_now'] ?></strong><span>Active now</span></a>
    <a class="<?= $view === 'active_today' ? 'active' : '' ?>" href="insights.php?view=active_today"><strong><?= (int) $stats['active_today'] ?></strong><span>Active today</span></a>
    <a class="<?= $view === 'active_7_days' ? 'active' : '' ?>" href="insights.php?view=active_7_days"><strong><?= (int) $stats['active_7_days'] ?></strong><span>Active 7 days</span></a>
    <a class="<?= $view === 'return_users' ? 'active' : '' ?>" href="insights.php?view=return_users"><strong><?= (int) $stats['return_users'] ?></strong><span>Return users</span></a>
    <a class="<?= $view === 'repeat_login_users' ? 'active' : '' ?>" href="insights.php?view=repeat_login_users"><strong><?= (int) $stats['repeat_login_users'] ?></strong><span>Repeat logins</span></a>
</section>

<?php if ($showUserTable): ?>
<section class="section">
    <div class="section-heading">
        <h2><?= $view === 'overview' ? 'Recently Active Users' : e($viewOptions[$view]) ?></h2>
        <?php if ($view !== 'overview'): ?>
            <a href="insights.php">Show overview</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>S.No</th><th>Trainee</th><th>Logins</th><th>Returns</th><th>Last Login</th><th>Last Active</th><th>Installed App</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= (int) $row['login_count'] ?></td>
                        <td><?= (int) $row['return_count'] ?></td>
                        <td><?= $row['last_login_at'] ? e(date('d M Y, h:i A', strtotime($row['last_login_at']))) : '-' ?></td>
                        <td><?= $row['last_active_at'] ? e(date('d M Y, h:i A', strtotime($row['last_active_at']))) : '-' ?></td>
                        <td><?= $row['last_installed_app_at'] ? e(date('d M Y, h:i A', strtotime($row['last_installed_app_at']))) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentUsers): ?>
                    <tr><td colspan="7">No user activity recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($showInstallTable): ?>
<section class="section">
    <div class="section-heading">
        <h2><?= $view === 'overview' ? 'Recent App Installs & Launches' : e($viewOptions[$view]) ?></h2>
        <?php if ($view !== 'overview'): ?>
            <a href="insights.php">Show overview</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>S.No</th><th>User</th><th>Platform</th><th>Launches</th><th>First Seen</th><th>Last Seen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentInstalls as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= $row['name'] ? e($row['name']) . '<br><small>' . e($row['email']) . '</small>' : 'Unknown visitor' ?></td>
                        <td><?= e($row['platform'] ?: '-') ?></td>
                        <td><?= (int) $row['launch_count'] ?></td>
                        <td><?= e(date('d M Y, h:i A', strtotime($row['first_installed_at']))) ?></td>
                        <td><?= e(date('d M Y, h:i A', strtotime($row['last_seen_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentInstalls): ?>
                    <tr><td colspan="6">No app installs recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_admin_footer.php'; ?>
