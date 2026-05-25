<?php
$title = 'Payments';
require __DIR__ . '/_admin_header.php';
ensure_certificate_requests_table();
ensure_course_detail_columns();

$dateFilter = admin_date_filter();
$courseParams = [];
$courseDateCondition = admin_date_condition('COALESCE(e.payment_requested_at, e.created_at)', $dateFilter, $courseParams);
$courseWhere = $courseDateCondition === '' ? '' : " AND {$courseDateCondition}";
$courseStmt = db()->prepare(
    "SELECT e.*, u.name, u.email, u.phone, c.title, c.fee, c.discount_fee
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     WHERE e.status IN ('paid', 'completed')
       AND IF(c.discount_fee IS NOT NULL AND c.discount_fee < c.fee, c.discount_fee, c.fee) > 0
       {$courseWhere}
     ORDER BY COALESCE(e.payment_requested_at, e.created_at) DESC, e.id DESC"
);
$courseStmt->execute($courseParams);
$paidCourses = $courseStmt->fetchAll();

$certificateParams = [];
$certificateDateCondition = admin_date_condition('COALESCE(cr.updated_at, cr.requested_at)', $dateFilter, $certificateParams);
$certificateWhere = $certificateDateCondition === '' ? '' : " AND {$certificateDateCondition}";
$certificateStmt = db()->prepare(
    "SELECT cr.*, u.name, u.email, u.phone, c.title, c.certification_fee, c.certificate_discount_fee, e.status AS enrollment_status
     FROM certificate_requests cr
     JOIN users u ON u.id = cr.user_id
     JOIN courses c ON c.id = cr.course_id
     JOIN enrollments e ON e.id = cr.enrollment_id
     WHERE cr.payment_note IS NOT NULL
       AND cr.payment_note != ''
       AND IF(c.certificate_discount_fee IS NOT NULL AND c.certificate_discount_fee < c.certification_fee, c.certificate_discount_fee, c.certification_fee) > 0
       {$certificateWhere}
     ORDER BY cr.updated_at DESC, cr.requested_at DESC"
);
$certificateStmt->execute($certificateParams);
$paidCertificates = $certificateStmt->fetchAll();

$courseTotal = 0.0;
foreach ($paidCourses as $row) {
    $courseTotal += course_fee_amount($row);
}

$certificateTotal = 0.0;
foreach ($paidCertificates as $row) {
    $certificateTotal += certificate_fee_amount($row);
}
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Paid Trainees</h1>
    <p>See who paid program fees and who paid certificate fees.</p>
</section>

<section class="section compact-section">
    <?php require __DIR__ . '/_date_filter.php'; ?>
</section>

<section class="admin-stats">
    <div><strong><?= count($paidCourses) ?></strong><span>Course payments</span></div>
    <div><strong><?= e(money($courseTotal)) ?></strong><span>Course amount</span></div>
    <div><strong><?= count($paidCertificates) ?></strong><span>Certificate payments</span></div>
    <div><strong><?= e(money($certificateTotal)) ?></strong><span>Certificate amount</span></div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Paid Course Fees</h2>
        <a href="enrollments.php">Manage enrollments</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>S.No</th><th>Trainee</th><th>Program</th><th>Amount</th><th>Payment Note</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($paidCourses as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= price_html($row, 'fee', 'discount_fee') ?></td>
                        <td><?= nl2br(e($row['payment_note'] ?: '-')) ?></td>
                        <td><?= e(enrollment_badge($row['status'])) ?></td>
                        <td><?= e(date('d M Y', strtotime($row['payment_requested_at'] ?: $row['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$paidCourses): ?>
                    <tr><td colspan="7">No paid course payments yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Paid Certificate Fees</h2>
        <a href="certificates.php">Manage certificates</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>S.No</th><th>Trainee</th><th>Program</th><th>Amount</th><th>Payment Note</th><th>Certificate Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($paidCertificates as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= price_html($row, 'certification_fee', 'certificate_discount_fee') ?></td>
                        <td><?= nl2br(e($row['payment_note'])) ?></td>
                        <td><?= e(certificate_badge($row['status'])) ?></td>
                        <td><?= e(date('d M Y', strtotime($row['updated_at'] ?: $row['requested_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$paidCertificates): ?>
                    <tr><td colspan="7">No paid certificate payments yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
