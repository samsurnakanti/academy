<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? 0);
$materialId = (int) ($_GET['material_id'] ?? 0);

$stmt = db()->prepare(
    "SELECT e.*, c.title, c.duration, c.fee, c.certification_fee, c.delivery_type
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

$hasFullAccess = in_array($enrollment['status'], ['paid', 'completed'], true) || (float) $enrollment['fee'] <= 0;
$materialsStmt = db()->prepare(
    "SELECT *
     FROM materials
     WHERE course_id = ?
     ORDER BY created_at ASC, id ASC"
);
$materialsStmt->execute([(int) $enrollment['course_id']]);
$materials = $materialsStmt->fetchAll();
$progressByMaterial = learning_progress_for_enrollment((int) $enrollment['id']);
$primaryType = ($enrollment['delivery_type'] ?? 'video') === 'live_session' ? 'live_session' : 'video';
$firstPrimaryItemId = null;
foreach ($materials as $material) {
    if (($material['material_type'] ?? 'video') === $primaryType) {
        $firstPrimaryItemId = (int) $material['id'];
        break;
    }
}

$canAccessMaterial = static function (array $material) use ($hasFullAccess, $firstPrimaryItemId, $primaryType): bool {
    return $hasFullAccess || (($material['material_type'] ?? 'video') === $primaryType && (int) $material['id'] === $firstPrimaryItemId);
};

$activeMaterial = null;
foreach ($materials as $material) {
    if ($canAccessMaterial($material)) {
        $activeMaterial = $material;
        break;
    }
}

$certificateStmt = db()->prepare('SELECT * FROM certificate_requests WHERE enrollment_id = ?');
$certificateStmt->execute([(int) $enrollment['id']]);
$certificate = $certificateStmt->fetch();

foreach ($materials as $material) {
    if ((int) $material['id'] === $materialId && $canAccessMaterial($material)) {
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
    <p><?= $primaryType === 'live_session' ? 'Join live sessions one by one and open supporting materials after enrollment.' : 'Watch course videos one by one and open supporting materials after enrollment.' ?></p>
</section>

<section class="learning-layout">
    <div class="video-panel">
        <?php if (!$activeMaterial): ?>
            <div class="empty-state">
                <h2>Learning items are locked</h2>
                <p>This is a paid program. The first <?= $primaryType === 'live_session' ? 'live session' : 'video' ?> is available free; complete payment to unlock the remaining sessions, videos, and materials.</p>
                <a class="button primary" href="payment.php?enrollment_id=<?= (int) $enrollment['id'] ?>">Continue Payment</a>
            </div>
        <?php elseif ($activeMaterial): ?>
            <?php $playbackUrl = playback_video_url($activeMaterial['file_url']); ?>
            <?php $materialType = $activeMaterial['material_type'] ?? 'video'; ?>
            <p class="eyebrow">Now viewing</p>
            <h2><?= e($activeMaterial['title']) ?></h2>
            <?php if (empty($activeMaterial['file_url'])): ?>
                <div class="empty-state">
                    <h2><?= $materialType === 'live_session' ? 'Live session not available' : ($materialType === 'video' ? 'Video not available' : 'Material not available') ?></h2>
                    <p>The description is available below. Admin will add the <?= $materialType === 'live_session' ? 'session link' : ($materialType === 'video' ? 'video' : 'material file') ?> soon.</p>
                </div>
            <?php elseif ($materialType === 'video'): ?>
                <div class="video-frame <?= should_use_native_video_player($activeMaterial['file_url']) ? 'native-player' : '' ?>">
                    <?php if (should_use_native_video_player($activeMaterial['file_url'])): ?>
                        <?php $activeProgress = $progressByMaterial[(int) $activeMaterial['id']] ?? null; ?>
                        <video
                            class="academy-video"
                            controls
                            controlsList="nodownload noremoteplayback"
                            disablePictureInPicture
                            preload="metadata"
                            playsinline
                            oncontextmenu="return false;"
                            data-progress-url="<?= e(public_url('save_progress.php')) ?>"
                            data-csrf-token="<?= e(csrf_token()) ?>"
                            data-enrollment-id="<?= (int) $enrollment['id'] ?>"
                            data-material-id="<?= (int) $activeMaterial['id'] ?>"
                            data-start-seconds="<?= e((string) min((float) ($activeProgress['watched_seconds'] ?? 0), max(0, (float) ($activeProgress['duration_seconds'] ?? 0) - 5))) ?>"
                        >
                            <source src="<?= e($playbackUrl) ?>">
                            Your browser does not support embedded video playback.
                        </video>
                        <button class="video-toggle" type="button" aria-label="Play video">
                            <span class="video-toggle-icon play"></span>
                        </button>
                    <?php else: ?>
                        <iframe src="<?= e(video_embed_url($activeMaterial['file_url'])) ?>" allowfullscreen loading="lazy"></iframe>
                    <?php endif; ?>
                </div>
                <aside class="elldy-promo" aria-label="Elldy Data Intelligence">
                    <div>
                        <p class="eyebrow">Start analysis and build dashboards</p>
                        <h3>Elldy Data Intelligence</h3>
                        <p>Turn what you learn into live analysis, dashboards, and sharper business decisions.</p>
                    </div>
                    <a class="button primary" href="https://elldy.com" target="_blank" rel="noopener">Get Free Access to Elldy</a>
                </aside>
            <?php elseif (is_image_material_url($activeMaterial['file_url'])): ?>
                <figure class="material-preview image-preview">
                    <img src="<?= e($playbackUrl) ?>" alt="<?= e($activeMaterial['title']) ?>" loading="lazy">
                </figure>
            <?php elseif (is_pdf_material_url($activeMaterial['file_url'])): ?>
                <div class="material-preview pdf-preview">
                    <iframe src="<?= e($playbackUrl) ?>" title="<?= e($activeMaterial['title']) ?>" loading="lazy"></iframe>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h2><?= $materialType === 'live_session' ? 'Live session link' : 'Learning material' ?></h2>
                    <p><?= $materialType === 'live_session' ? 'Open this session link in a new tab when you are ready.' : 'This file type opens best in a new tab.' ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($activeMaterial['description'])): ?>
                <p class="material-description"><?= text_with_links($activeMaterial['description']) ?></p>
            <?php endif; ?>
            <?php if (!empty($activeMaterial['file_url']) && ($materialType !== 'video' || !should_use_native_video_player($activeMaterial['file_url']))): ?>
                <a class="button small" href="<?= e($playbackUrl) ?>" target="_blank" rel="noopener">Open original link</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <h2>No video selected</h2>
                <p>Session videos and learning materials will appear here after admin publishes them.</p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="lesson-list">
        <h2>Program Learning Items</h2>
        <?php foreach ($materials as $index => $material): ?>
            <?php $isAccessible = $canAccessMaterial($material); ?>
            <?php if ($isAccessible): ?>
                <a class="lesson-item <?= $activeMaterial && (int) $activeMaterial['id'] === (int) $material['id'] ? 'active' : '' ?>" href="learn.php?enrollment_id=<?= (int) $enrollment['id'] ?>&material_id=<?= (int) $material['id'] ?>">
            <?php else: ?>
                <div class="lesson-item locked">
            <?php endif; ?>
                    <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div>
                        <strong><?= e($material['title']) ?></strong>
                        <?php $itemProgress = $progressByMaterial[(int) $material['id']] ?? null; ?>
                        <small>
                            <?= e(ucwords(str_replace('_', ' ', $material['material_type'] ?? 'video'))) ?><?= !$isAccessible ? ' - Locked until payment' : '' ?>
                            <?php if (($material['material_type'] ?? 'video') === 'video' && $itemProgress): ?>
                                <span class="progress-chip <?= (int) $itemProgress['is_completed'] === 1 ? 'complete' : '' ?>">
                                    <?= (int) $itemProgress['is_completed'] === 1 ? 'Completed' : (int) round((float) $itemProgress['progress_percent']) . '%' ?>
                                </span>
                            <?php endif; ?>
                        </small>
                    </div>
            <?= $isAccessible ? '</a>' : '</div>' ?>
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
