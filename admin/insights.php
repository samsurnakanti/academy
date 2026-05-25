<?php
$title = 'Insights';
require __DIR__ . '/_admin_header.php';
ensure_app_analytics_tables();

$view = (string) ($_GET['view'] ?? 'overview');
$dateFilter = admin_date_filter();
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

function admin_count_query(string $sql, array $params): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

$installStatsParams = [];
$installStatsDate = admin_date_condition('last_seen_at', $dateFilter, $installStatsParams);
$installStatsWhere = $installStatsDate === '' ? '' : "WHERE {$installStatsDate}";
$activityStatsParams = [];
$activityStatsDate = admin_date_condition('last_active_at', $dateFilter, $activityStatsParams);
$activityStatsWhere = $activityStatsDate === '' ? '' : "WHERE {$activityStatsDate}";
$activityPrefix = $activityStatsWhere === '' ? 'WHERE' : $activityStatsWhere . ' AND';

$stats = [
    'app_installs' => admin_count_query("SELECT COUNT(*) FROM app_installs {$installStatsWhere}", $installStatsParams),
    'installed_users' => admin_count_query(
        "SELECT COUNT(DISTINCT user_id) FROM app_installs " . ($installStatsWhere === '' ? 'WHERE' : $installStatsWhere . ' AND') . " user_id IS NOT NULL",
        $installStatsParams
    ),
    'installed_launches_today' => admin_count_query(
        "SELECT COUNT(*) FROM app_installs " . ($installStatsWhere === '' ? 'WHERE DATE(last_seen_at) = CURDATE()' : $installStatsWhere),
        $installStatsParams
    ),
    'active_now' => admin_count_query(
        "SELECT COUNT(*) FROM app_user_activity {$activityPrefix} last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        $activityStatsParams
    ),
    'active_today' => admin_count_query(
        "SELECT COUNT(*) FROM app_user_activity " . ($activityStatsWhere === '' ? 'WHERE DATE(last_active_at) = CURDATE()' : $activityStatsWhere),
        $activityStatsParams
    ),
    'active_7_days' => admin_count_query(
        "SELECT COUNT(*) FROM app_user_activity {$activityPrefix} last_active_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        $activityStatsParams
    ),
    'return_users' => admin_count_query(
        "SELECT COUNT(*) FROM app_user_activity {$activityPrefix} return_count > 0",
        $activityStatsParams
    ),
    'repeat_login_users' => admin_count_query(
        "SELECT COUNT(*) FROM app_user_activity {$activityPrefix} login_count > 1",
        $activityStatsParams
    ),
];

$activityConditions = [];
$activityParams = [];
$activityDateCondition = admin_date_condition('aua.last_active_at', $dateFilter, $activityParams);
if ($activityDateCondition !== '') {
    $activityConditions[] = $activityDateCondition;
}

$installConditions = [];
$installParams = [];
$installDateCondition = admin_date_condition('ai.last_seen_at', $dateFilter, $installParams);
if ($installDateCondition !== '') {
    $installConditions[] = $installDateCondition;
}

switch ($view) {
    case 'active_now':
        $activityConditions[] = "aua.last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        break;
    case 'active_today':
        if ($activityDateCondition === '') {
            $activityConditions[] = 'DATE(aua.last_active_at) = CURDATE()';
        }
        break;
    case 'active_7_days':
        $activityConditions[] = "aua.last_active_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'return_users':
        $activityConditions[] = 'aua.return_count > 0';
        break;
    case 'repeat_login_users':
        $activityConditions[] = 'aua.login_count > 1';
        break;
    case 'installed_users':
        $activityConditions[] = 'aua.last_installed_app_at IS NOT NULL';
        break;
    case 'installed_launches_today':
        if ($installDateCondition === '') {
            $installConditions[] = 'DATE(ai.last_seen_at) = CURDATE()';
        }
        break;
}

$activityWhere = $activityConditions ? 'WHERE ' . implode(' AND ', $activityConditions) : '';
$installWhere = $installConditions ? 'WHERE ' . implode(' AND ', $installConditions) : '';

$recentUsersStmt = db()->prepare(
    "SELECT u.name, u.email, u.phone, aua.login_count, aua.return_count, aua.last_login_at, aua.last_active_at, aua.last_return_at, aua.last_installed_app_at
     FROM app_user_activity aua
     JOIN users u ON u.id = aua.user_id
     {$activityWhere}
     ORDER BY aua.last_active_at DESC
     LIMIT " . ($view === 'overview' ? '25' : '200')
);
$recentUsersStmt->execute($activityParams);
$recentUsers = $recentUsersStmt->fetchAll();

$recentInstallsStmt = db()->prepare(
    "SELECT ai.*, u.name, u.email
     FROM app_installs ai
     LEFT JOIN users u ON u.id = ai.user_id
     {$installWhere}
     ORDER BY ai.last_seen_at DESC
     LIMIT " . ($view === 'overview' ? '25' : '200')
);
$recentInstallsStmt->execute($installParams);
$recentInstalls = $recentInstallsStmt->fetchAll();

$showUserTable = in_array($view, ['overview', 'active_now', 'active_today', 'active_7_days', 'return_users', 'repeat_login_users', 'installed_users'], true);
$showInstallTable = in_array($view, ['overview', 'app_installs', 'installed_launches_today'], true);
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>App & Login Insights</h1>
    <p>Track app installs, login activity, active trainees, and returning users.</p>
</section>

<section class="section compact-section">
    <?php
    $dateFilterAction = 'insights.php';
    $dateFilterHidden = ['view' => $view];
    require __DIR__ . '/_date_filter.php';
    ?>
</section>

<section class="admin-stats">
    <a class="<?= $view === 'app_installs' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'app_installs'], $dateFilter)) ?>"><strong><?= (int) $stats['app_installs'] ?></strong><span>Installed apps</span></a>
    <a class="<?= $view === 'installed_users' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'installed_users'], $dateFilter)) ?>"><strong><?= (int) $stats['installed_users'] ?></strong><span>Installed users</span></a>
    <a class="<?= $view === 'installed_launches_today' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'installed_launches_today'], $dateFilter)) ?>"><strong><?= (int) $stats['installed_launches_today'] ?></strong><span>App opens</span></a>
    <a class="<?= $view === 'active_now' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'active_now'], $dateFilter)) ?>"><strong><?= (int) $stats['active_now'] ?></strong><span>Active now</span></a>
    <a class="<?= $view === 'active_today' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'active_today'], $dateFilter)) ?>"><strong><?= (int) $stats['active_today'] ?></strong><span>Active</span></a>
    <a class="<?= $view === 'active_7_days' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'active_7_days'], $dateFilter)) ?>"><strong><?= (int) $stats['active_7_days'] ?></strong><span>Active 7 days</span></a>
    <a class="<?= $view === 'return_users' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'return_users'], $dateFilter)) ?>"><strong><?= (int) $stats['return_users'] ?></strong><span>Return users</span></a>
    <a class="<?= $view === 'repeat_login_users' ? 'active' : '' ?>" href="<?= e(admin_date_filter_url('insights.php', ['view' => 'repeat_login_users'], $dateFilter)) ?>"><strong><?= (int) $stats['repeat_login_users'] ?></strong><span>Repeat logins</span></a>
</section>

<?php if ($showUserTable): ?>
<section class="section">
    <div class="section-heading">
        <h2><?= $view === 'overview' ? 'Recently Active Users' : e($viewOptions[$view]) ?></h2>
        <?php if ($view !== 'overview'): ?>
            <a href="<?= e(admin_date_filter_url('insights.php', ['view' => 'overview'], $dateFilter)) ?>">Show overview</a>
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
            <a href="<?= e(admin_date_filter_url('insights.php', ['view' => 'overview'], $dateFilter)) ?>">Show overview</a>
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
