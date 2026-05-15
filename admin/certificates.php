<?php
$title = 'Certificates';
require __DIR__ . '/_admin_header.php';
ensure_certificate_requests_table();

$rows = db()->query(
    "SELECT cr.*, u.name, u.email, u.phone, c.title, c.certification_fee, e.status AS enrollment_status
     FROM certificate_requests cr
     JOIN users u ON u.id = cr.user_id
     JOIN courses c ON c.id = cr.course_id
     JOIN enrollments e ON e.id = cr.enrollment_id
     ORDER BY cr.requested_at DESC"
)->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Certificate Control</h1>
    <p>Certificates are now issued automatically once an eligible learner reaches paid/completed status.</p>
</section>

<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Trainee</th><th>Program</th><th>Certificate Fee</th><th>Program Payment</th><th>Certificate Payment</th><th>Certificate</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?> | <?= e($row['phone']) ?></small></td>
                        <td><?= e($row['title']) ?></td>
                        <td><?= ((float) $row['certification_fee']) > 0 ? money($row['certification_fee']) : 'Included' ?></td>
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
                    <tr><td colspan="7">No certificate requests yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
