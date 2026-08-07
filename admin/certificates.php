<?php
$title = 'Certificates';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
ensure_certificate_requests_table();
ensure_course_detail_columns();
$selectedList = $_GET['list'] ?? 'all';
$allowedLists = ['all', 'request', 'submitted'];
if (!in_array($selectedList, $allowedLists, true)) {
    $selectedList = 'all';
}

if (!function_exists('certificate_list_filter_where')) {
    function certificate_list_filter_where(string $selectedList): string
    {
        return match ($selectedList) {
            'request' => "WHERE (cr.dashboard_url IS NULL OR cr.dashboard_url = '') AND cr.dashboard_review_status = 'not_submitted'",
            'submitted' => "WHERE cr.dashboard_url IS NOT NULL AND cr.dashboard_url != '' AND cr.dashboard_review_status != 'not_submitted'",
            default => '',
        };
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['id'] ?? 0);
    $returnList = $_POST['list'] ?? $selectedList;
    if (!in_array($returnList, $allowedLists, true)) {
        $returnList = 'all';
    }

    $stmt = db()->prepare(
        "SELECT cr.*, e.status AS enrollment_status, u.name, c.title, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee, c.certificate_title, c.certificate_details
         FROM certificate_requests cr
         JOIN enrollments e ON e.id = cr.enrollment_id
         JOIN users u ON u.id = cr.user_id
         JOIN courses c ON c.id = cr.course_id
         WHERE cr.id = ?"
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        flash('error', 'Certificate request not found.');
        redirect('certificates.php?list=' . urlencode($returnList));
    }

    $note = trim((string) ($_POST['dashboard_review_note'] ?? ''));

    if ($action === 'issue_certificate') {
        $dashboardUrl = normalize_elldy_dashboard_url((string) ($request['dashboard_url'] ?? ''));
        $programPaid = in_array($request['enrollment_status'], ['paid', 'completed'], true) || !course_requires_payment($request);
        $certificatePaid = certificate_fee_amount($request) <= 0 || trim((string) ($request['payment_note'] ?? '')) !== '';

        if ($dashboardUrl === '' || !is_elldy_dashboard_url($dashboardUrl)) {
            flash('error', 'Only public Elldy dashboard links from elldy.com can be issued.');
        } elseif (!$programPaid) {
            flash('error', 'Program payment must be completed before issuing this certificate.');
        } elseif (!$certificatePaid) {
            flash('error', 'Certificate payment must be completed before issuing this certificate.');
        } else {
            $update = db()->prepare(
                "UPDATE certificate_requests
                 SET dashboard_url = ?,
                     dashboard_review_status = 'approved',
                     dashboard_review_note = ?,
                     dashboard_reviewed_at = NOW(),
                     status = 'approved'
                 WHERE id = ?"
            );
            $update->execute([$dashboardUrl, $note, $requestId]);
            $issuedUrl = issue_certificate_for_enrollment(array_merge($request, [
                'dashboard_url' => $dashboardUrl,
                'dashboard_review_status' => 'approved',
                'dashboard_review_note' => $note,
            ]));
            flash($issuedUrl ? 'success' : 'error', $issuedUrl ? 'Certificate issued. Student can download it now.' : 'Unable to issue certificate.');
        }
    } elseif ($action === 'cancel_certificate') {
        $update = db()->prepare(
            "UPDATE certificate_requests
             SET dashboard_review_status = 'rejected',
                 dashboard_review_note = ?,
                 dashboard_reviewed_at = NOW(),
                 status = 'rejected',
                 certificate_url = NULL,
                 certificate_code = NULL,
                 issued_at = NULL
             WHERE id = ?"
        );
        $update->execute([$note !== '' ? $note : 'Certificate request cancelled by admin.', $requestId]);
        flash('success', 'Certificate request cancelled.');
    }

    redirect('certificates.php?list=' . urlencode($returnList));
}

$where = certificate_list_filter_where($selectedList);
$rows = db()->query(
    "SELECT cr.*, u.name, u.email, u.phone, c.title, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee, c.certificate_title, c.certificate_details, e.status AS enrollment_status
     FROM certificate_requests cr
     JOIN users u ON u.id = cr.user_id
     JOIN courses c ON c.id = cr.course_id
     JOIN enrollments e ON e.id = cr.enrollment_id
     {$where}
     ORDER BY cr.requested_at DESC"
)->fetchAll();
require __DIR__ . '/_admin_header.php';
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Certificate Control</h1>
    <p>Review new Elldy dashboard submissions, then issue or cancel certificate requests.</p>
</section>

<section class="section compact-section">
    <form class="date-filter-form" method="get" action="certificates.php">
        <label>List
            <select name="list">
                <option value="all" <?= $selectedList === 'all' ? 'selected' : '' ?>>All certificate requests</option>
                <option value="request" <?= $selectedList === 'request' ? 'selected' : '' ?>>Request list</option>
                <option value="submitted" <?= $selectedList === 'submitted' ? 'selected' : '' ?>>Submitted dashboard list</option>
            </select>
        </label>
        <button class="button small" type="submit">View</button>
        <a class="button small" href="certificates.php">Clear</a>
    </form>
</section>

<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>S.No</th><th>Trainee</th><th>Program</th><th>Course Progress</th><th>Dashboard</th><th>Certificate Fee</th><th>Program Payment</th><th>Certificate Payment</th><th>Certificate</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <?php $completion = enrollment_learning_completion((int) $row['enrollment_id']); ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= e($row['title']) ?></td>
                        <td>
                            <span class="progress-chip <?= $completion['is_complete'] ? 'complete' : '' ?>">
                                <?= $completion['is_complete'] ? 'Completed' : 'In progress' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($row['dashboard_url'])): ?>
                                <a href="<?= e($row['dashboard_url']) ?>" target="_blank" rel="noopener">Open dashboard</a><br>
                            <?php endif; ?>
                            <small><?= e(dashboard_review_badge($row['dashboard_review_status'] ?? 'not_submitted')) ?></small>
                            <?php if (!empty($row['dashboard_review_note'])): ?>
                                <br><small><?= e($row['dashboard_review_note']) ?></small>
                            <?php endif; ?>
                            <?php if (($row['dashboard_review_status'] ?? '') === 'pending'): ?>
                                <form method="post" class="inline-form certificate-review-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="list" value="<?= e($selectedList) ?>">
                                    <input name="dashboard_review_note" placeholder="Admin note">
                                    <button class="button tiny" type="submit" name="action" value="issue_certificate">Issue</button>
                                    <button class="button tiny danger-action" type="submit" name="action" value="cancel_certificate">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?= certificate_fee_amount($row) > 0 ? price_html($row, 'certification_fee', 'certificate_discount_fee') : 'Included' ?></td>
                        <td><?= e(enrollment_badge($row['enrollment_status'])) ?></td>
                        <td><?= nl2br(e($row['payment_note'] ?: '-')) ?></td>
                        <td>
                            <?php if ($row['certificate_url']): ?>
                                <a href="<?= e($row['certificate_url']) ?>" target="_blank" rel="noopener">Open certificate</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= e(certificate_badge($row['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="10">No certificate requests yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
