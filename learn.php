<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? 0);
$materialId = (int) ($_GET['material_id'] ?? 0);

$stmt = db()->prepare(
    "SELECT e.*, c.title, c.duration, c.fee, c.certification_fee, c.first_class_link
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
);
$stmt->execute([$enrollmentId, $user['id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    http_response_code(404);
    exit('Learning access not found.');
}

$canWatchPaidVideos = $enrollment['status'] === 'paid' || (float) $enrollment['fee'] <= 0;
$materialsStmt = db()->prepare(
    "SELECT *
     FROM materials
     WHERE course_id = ?
     ORDER BY FIELD(material_type, 'video', 'live_session', 'material'), created_at ASC, id ASC"
);
$materialsStmt->execute([(int) $enrollment['course_id']]);
$materials = $materialsStmt->fetchAll();
$activeMaterial = $materials[0] ?? null;

$certificateStmt = db()->prepare('SELECT * FROM certificate_requests WHERE enrollment_id = ?');
$certificateStmt->execute([(int) $enrollment['id']]);
$certificate = $certificateStmt->fetch();

foreach ($materials as $material) {
    if ((int) $material['id'] === $materialId) {
        $activeMaterial = $material;
        break;
    }
}

$title = 'Learn | ' . $enrollment['title'] . ' | Elldy Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">Learning workspace</p>
    <h1><?= e($enrollment['title']) ?></h1>
    <p>Watch course videos one by one, join live sessions, and open supporting materials after enrollment.</p>
</section>

<section class="learning-layout">
    <div class="video-panel">
        <?php if (!$canWatchPaidVideos): ?>
            <div class="empty-state">
                <h2>Course videos are locked</h2>
                <p>This is a paid program. After your free session, complete payment to unlock all course videos on the website.</p>
                <a class="button primary" href="payment.php?enrollment_id=<?= (int) $enrollment['id'] ?>">Continue Payment</a>
            </div>
        <?php elseif ($activeMaterial && !empty($activeMaterial['file_url'])): ?>
            <p class="eyebrow">Now viewing</p>
            <h2><?= e($activeMaterial['title']) ?></h2>
            <p><?= e($activeMaterial['description']) ?></p>
            <?php if (($activeMaterial['material_type'] ?? 'video') === 'video'): ?>
                <div class="video-frame">
                    <iframe src="<?= e(video_embed_url($activeMaterial['file_url'])) ?>" allowfullscreen loading="lazy"></iframe>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h2><?= ($activeMaterial['material_type'] ?? '') === 'live_session' ? 'Live session link' : 'Learning material' ?></h2>
                    <p>Open this link in a new tab when you are ready.</p>
                </div>
            <?php endif; ?>
            <a class="button small" href="<?= e($activeMaterial['file_url']) ?>" target="_blank" rel="noopener">Open original link</a>
        <?php else: ?>
            <div class="empty-state">
                <h2>No video selected</h2>
                <p>Session videos and learning materials will appear here after admin publishes them.</p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="lesson-list">
        <h2>Course Videos</h2>
        <?php foreach ($materials as $index => $material): ?>
            <a class="lesson-item <?= $activeMaterial && (int) $activeMaterial['id'] === (int) $material['id'] ? 'active' : '' ?>" href="learn.php?enrollment_id=<?= (int) $enrollment['id'] ?>&material_id=<?= (int) $material['id'] ?>">
                <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <div>
                    <strong><?= e($material['title']) ?></strong>
                    <small><?= e(ucwords(str_replace('_', ' ', $material['material_type'] ?? 'video'))) ?><?= $material['description'] ? ' - ' . e($material['description']) : '' ?></small>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (!$materials): ?>
            <p class="empty">No session videos or materials published yet.</p>
        <?php endif; ?>
        <div class="certificate-action">
            <h2>Certificate</h2>
            <?php if ($certificate && $certificate['status'] === 'issued' && $certificate['certificate_url']): ?>
                <p>Your certificate is ready.</p>
                <a class="button primary" href="<?= e($certificate['certificate_url']) ?>" target="_blank" rel="noopener">Download Certificate</a>
            <?php elseif ($certificate): ?>
                <p>Status: <?= e(certificate_badge($certificate['status'])) ?></p>
            <?php else: ?>
                <p>After watching the course videos, pay for certification if applicable. Admin will upload your certificate.</p>
                <a class="button primary" href="certificate_apply.php?enrollment_id=<?= (int) $enrollment['id'] ?>">Certificate Details</a>
            <?php endif; ?>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
