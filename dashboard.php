<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();

$stmt = db()->prepare(
    "SELECT e.*, c.title, c.duration, c.fee, c.certification_fee, c.delivery_type,
            cr.status AS certificate_status, cr.certificate_url
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     LEFT JOIN certificate_requests cr ON cr.enrollment_id = e.id
     WHERE e.user_id = ?
     ORDER BY e.created_at DESC"
);
$stmt->execute([$user['id']]);
$enrollments = $stmt->fetchAll();

foreach ($enrollments as &$enrollmentRow) {
    if (($enrollmentRow['certificate_status'] ?? '') === 'issued') {
        $downloadUrl = public_url('download_certificate.php?enrollment_id=' . (int) $enrollmentRow['id']);
        if (($enrollmentRow['certificate_url'] ?? '') !== $downloadUrl) {
            $urlUpdate = db()->prepare('UPDATE certificate_requests SET certificate_url = ? WHERE enrollment_id = ?');
            $urlUpdate->execute([$downloadUrl, (int) $enrollmentRow['id']]);
            $enrollmentRow['certificate_url'] = $downloadUrl;
        }
    }

    if (in_array($enrollmentRow['status'], ['paid', 'completed'], true)) {
        if (!$enrollmentRow['certificate_status']) {
            $request = db()->prepare(
                "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE enrollment_id = enrollment_id"
            );
            $request->execute([
                (int) $enrollmentRow['id'],
                (int) $user['id'],
                (int) $enrollmentRow['course_id'],
                'requested',
            ]);
        }

        $certificate = ensure_instant_certificate_for_enrollment((int) $enrollmentRow['id']);
        if ($certificate) {
            $enrollmentRow['certificate_status'] = $certificate['status'];
            $enrollmentRow['certificate_url'] = $certificate['certificate_url'];
        }
    }
}
unset($enrollmentRow);

$title = 'My Analytics Classes | Elldy Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">Trainee dashboard</p>
    <h1>Welcome, <?= e($user['name']) ?></h1>
    <p>Track your live sessions, first session access, and payment status.</p>
</section>
<section class="section">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Program</th>
                    <th>Duration</th>
                    <th>Fee</th>
                    <th>Certification</th>
                    <th>Status</th>
                    <th>Learn</th>
                    <th>Certificate</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollments as $row): ?>
                    <tr>
                        <td><?= e($row['title']) ?></td>
                        <td><?= e($row['duration']) ?></td>
                        <td><?= money($row['fee']) ?></td>
                        <td><?= ((float) ($row['certification_fee'] ?? 0)) > 0 ? money($row['certification_fee']) : 'Included' ?></td>
                        <td>
                            <span class="status">
                                <?php if ($row['status'] === 'free_access'): ?>
                                    <?= ($row['delivery_type'] ?? 'video') === 'live_session' ? 'First Session Free' : 'First Video Free' ?>
                                <?php else: ?>
                                    <?= e(enrollment_badge($row['status'])) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['status'] !== 'cancelled'): ?>
                                <a class="button tiny" href="learn.php?enrollment_id=<?= (int) $row['id'] ?>">
                                    <?= ($row['delivery_type'] ?? 'video') === 'live_session' ? 'Join Live Sessions' : 'Watch Videos' ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['certificate_url'] && $row['certificate_status'] === 'issued'): ?>
                                <a class="button tiny" href="<?= e($row['certificate_url']) ?>" target="_blank" rel="noopener">Download</a>
                            <?php elseif (($row['certificate_status'] ?? '') === 'payment_pending'): ?>
                                <a class="button tiny" href="pay_redirect.php?type=certificate&id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">Pay Certificate Fee</a>
                            <?php elseif ($row['certificate_status']): ?>
                                <?= e(certificate_badge($row['certificate_status'])) ?>
                            <?php elseif ($row['status'] !== 'cancelled'): ?>
                                <a class="button tiny" href="certificate_apply.php?enrollment_id=<?= (int) $row['id'] ?>">Certificate</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((float) $row['fee'] <= 0): ?>
                                Included
                            <?php elseif (in_array($row['status'], ['paid', 'completed'], true)): ?>
                                Paid
                            <?php elseif ($row['status'] === 'payment_pending'): ?>
                                <a class="button tiny" href="pay_redirect.php?type=program&id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">Pay Program Fee</a>
                            <?php elseif ($row['status'] === 'free_access'): ?>
                                <?= ($row['delivery_type'] ?? 'video') === 'live_session' ? 'Due after first session' : 'Due after first video' ?>
                            <?php elseif ($row['status'] !== 'cancelled'): ?>
                                -
                            <?php else: ?>
                                Cancelled
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$enrollments): ?>
                    <tr><td colspan="8">No enrollments yet. <a href="<?= e(public_url('programs')) ?>">Choose an analytics program</a>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
