<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();
ensure_certificate_requests_table();
ensure_course_detail_columns();
ensure_course_structure_tables();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? 0);
$materialId = (int) ($_GET['material_id'] ?? 0);

$stmt = db()->prepare(
    "SELECT e.*, c.title, c.short_description, c.description, c.learning_plan, c.duration, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee, c.delivery_type
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

$programPaid = in_array($enrollment['status'], ['paid', 'completed'], true) || !course_requires_payment($enrollment);
$certificateFeeDue = certificate_fee_amount($enrollment) > 0;
$hasFullAccess = $programPaid;
$materials = course_material_rows((int) $enrollment['course_id']);
$materialGroups = course_material_groups($materials);
$progressByMaterial = learning_progress_for_enrollment((int) $enrollment['id']);
$primaryType = ($enrollment['delivery_type'] ?? 'video') === 'live_session' ? 'live_session' : 'video';

$canAccessMaterial = static function (array $material) use ($hasFullAccess): bool {
    return $hasFullAccess;
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
$certificatePaid = !$certificateFeeDue || trim((string) ($certificate['payment_note'] ?? '')) !== '';

if ($programPaid && $certificatePaid) {
    if (!$certificate && !$certificateFeeDue) {
        $request = db()->prepare(
            "INSERT INTO certificate_requests (enrollment_id, user_id, course_id, status)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enrollment_id = enrollment_id"
        );
        $request->execute([
            (int) $enrollment['id'],
            (int) $user['id'],
            (int) $enrollment['course_id'],
            'requested',
        ]);
    }

    $certificate = ensure_instant_certificate_for_enrollment((int) $enrollment['id']) ?? $certificate;
}

foreach ($materials as $material) {
    if ((int) $material['id'] === $materialId && $canAccessMaterial($material)) {
        $activeMaterial = $material;
        break;
    }
}

$videoPlaylist = [];
$playlistIndex = 0;
$currentPlaylistIndex = null;
$nextVideoItem = null;

foreach ($materialGroups as $module) {
    foreach ($module['topics'] as $topic) {
        foreach ($topic['materials'] as $material) {
            $itemType = $material['material_type'] ?? 'video';
            $itemUrl = trim((string) ($material['file_url'] ?? ''));
            $itemIsVideoPlayback = $itemUrl !== '' && (
                $itemType === 'video' ||
                ($itemType === 'live_session' && is_playable_video_url($itemUrl))
            );

            if (!$canAccessMaterial($material) || !$itemIsVideoPlayback) {
                continue;
            }

            $playlistIndex++;
            $playlistItem = [
                'index' => $playlistIndex,
                'material' => $material,
                'module_title' => (string) ($module['title'] ?? 'General Module'),
                'topic_title' => (string) ($topic['title'] ?? 'General Topic'),
                'url' => 'learn.php?enrollment_id=' . (int) $enrollment['id'] . '&material_id=' . (int) $material['id'],
            ];
            $videoPlaylist[] = $playlistItem;

            if ($activeMaterial && (int) $activeMaterial['id'] === (int) $material['id']) {
                $currentPlaylistIndex = count($videoPlaylist) - 1;
            }
        }
    }
}

if ($currentPlaylistIndex !== null && isset($videoPlaylist[$currentPlaylistIndex + 1])) {
    $nextVideoItem = $videoPlaylist[$currentPlaylistIndex + 1];
}

$socialLinkLabel = static function (string $url): string {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?: '';

    return match (true) {
        str_contains($host, 'facebook.com') || str_contains($host, 'fb.com') => 'Facebook',
        str_contains($host, 'instagram.com') => 'Instagram',
        str_contains($host, 'linkedin.com') => 'LinkedIn',
        str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be') => 'YouTube',
        str_contains($host, 'x.com') || str_contains($host, 'twitter.com') => 'X',
        str_contains($host, 'wa.me') || str_contains($host, 'whatsapp.com') => 'WhatsApp',
        str_contains($host, 'telegram.me') || str_contains($host, 't.me') => 'Telegram',
        default => 'Website',
    };
};

$socialLinksFromText = static function (string $text) use ($socialLinkLabel): array {
    preg_match_all('~https?://[^\s<>"\']+~i', $text, $matches);
    $links = [];

    foreach ($matches[0] ?? [] as $url) {
        $url = rtrim($url, ".,);]");
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            continue;
        }

        $isSocial = str_contains($host, 'facebook.com') ||
            str_contains($host, 'fb.com') ||
            str_contains($host, 'instagram.com') ||
            str_contains($host, 'linkedin.com') ||
            str_contains($host, 'youtube.com') ||
            str_contains($host, 'youtu.be') ||
            str_contains($host, 'x.com') ||
            str_contains($host, 'twitter.com') ||
            str_contains($host, 'wa.me') ||
            str_contains($host, 'whatsapp.com') ||
            str_contains($host, 'telegram.me') ||
            str_contains($host, 't.me');

        if (!$isSocial || isset($links[$url])) {
            continue;
        }

        $links[$url] = [
            'url' => $url,
            'label' => $socialLinkLabel($url),
        ];
    }

    return array_values($links);
};

$learningDescriptionHtml = static function (string $text, string $materialTitle = ''): string {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

    if ($text === '') {
        return '';
    }

    $text = preg_replace('~\s*#{1,6}\s*Stay Connected\b.*$~isu', '', $text) ?? $text;
    $text = preg_replace('~\s*\bStay Connected\b.*$~isu', '', $text) ?? $text;
    $text = trim($text);

    if ($materialTitle !== '') {
        $quotedTitle = preg_quote(trim($materialTitle), '~');
        $text = preg_replace('~^\s*' . $quotedTitle . '\s*(?:\n+|$)~iu', '', $text) ?? $text;
        $text = trim($text);
    }

    $paragraphs = preg_split('~\n{2,}~', $text) ?: [];
    $html = [];

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim(preg_replace('~\n+~', ' ', $paragraph) ?? $paragraph);
        $paragraph = preg_replace('~^\s*#{1,6}\s*~', '', $paragraph) ?? $paragraph;

        if ($paragraph === '') {
            continue;
        }

        $paragraphHtml = text_with_links($paragraph);
        $paragraphHtml = preg_replace('~\*([^*]+)\*~', '<strong>$1</strong>', $paragraphHtml) ?? $paragraphHtml;
        $html[] = '<p>' . $paragraphHtml . '</p>';
    }

    return implode("\n", $html);
};

if (
    $activeMaterial &&
    ($activeMaterial['material_type'] ?? 'video') === 'live_session' &&
    is_playable_video_url((string) ($activeMaterial['file_url'] ?? ''))
) {
    if (clear_attendance_progress_for_video_session($enrollment, $activeMaterial)) {
        unset($progressByMaterial[(int) $activeMaterial['id']]);
    }
} elseif (
    $activeMaterial &&
    ($activeMaterial['material_type'] ?? 'video') === 'live_session'
) {
    record_live_session_attendance($enrollment, $activeMaterial);
}

$title = 'Learn | ' . $enrollment['title'] . ' | Elldy Academy';
require __DIR__ . '/includes/header.php';
?>
<button class="learning-menu-toggle" type="button" data-learning-menu-toggle aria-expanded="false" aria-controls="learning-sidebar">
    <span></span>
    <span>Course Menu</span>
</button>
<div class="learning-menu-backdrop" data-learning-menu-close></div>

<section class="learning-layout">
    <div class="video-panel">
        <?php if (!$activeMaterial): ?>
            <div class="empty-state">
                <h2>Learning items are locked</h2>
                <p>Program payment is required before videos, live sessions, and materials can be opened.</p>
                <a class="button primary" href="payment.php?enrollment_id=<?= (int) $enrollment['id'] ?>">Continue Payment</a>
            </div>
        <?php elseif ($activeMaterial): ?>
            <?php $playbackUrl = playback_video_url($activeMaterial['file_url']); ?>
            <?php $materialType = $activeMaterial['material_type'] ?? 'video'; ?>
            <?php $isVideoPlayback = $materialType === 'video' || ($materialType === 'live_session' && is_playable_video_url((string) ($activeMaterial['file_url'] ?? ''))); ?>
            <p class="eyebrow">Now viewing</p>
            <h2><?= e($activeMaterial['title']) ?></h2>
            <?php if ($materialType === 'live_session' && !$isVideoPlayback): ?>
                <?php $configuredLiveUrl = trim((string) ($activeMaterial['file_url'] ?? '')); ?>
                <?php if (live_session_is_external_url($configuredLiveUrl)): ?>
                    <?php $providerName = live_session_provider_name($configuredLiveUrl); ?>
                    <div class="empty-state live-session-card">
                        <h2><?= e($providerName) ?> class</h2>
                        <?php if (live_session_is_zoom_url($configuredLiveUrl)): ?>
                            <p>Join from here when class starts. Zoom opens inside the academy website using the Zoom Meeting SDK.</p>
                            <a class="button primary" href="<?= e(public_url('zoom_class.php?enrollment_id=' . (int) $enrollment['id'] . '&material_id=' . (int) $activeMaterial['id'])) ?>">Join Zoom Class</a>
                        <?php else: ?>
                            <p>Join from here when class starts. <?= $providerName === 'Google Meet' ? 'Google Meet opens in a new tab so camera and microphone work correctly.' : 'The class opens in a new tab.' ?></p>
                            <a class="button primary" href="<?= e($configuredLiveUrl) ?>" target="_blank" rel="noopener">Join <?= e($providerName) ?></a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="live-session-frame">
                        <iframe
                            src="<?= e(live_session_embed_url($enrollment, $activeMaterial, $user)) ?>"
                            allow="camera; microphone; fullscreen; display-capture; autoplay; clipboard-write"
                            referrerpolicy="strict-origin-when-cross-origin"
                        ></iframe>
                    </div>
                    <div class="live-session-actions">
                        <a class="button small" href="https://meet.jit.si/<?= e(rawurlencode(live_session_room_name($enrollment, $activeMaterial))) ?>" target="_blank" rel="noopener">Open in New Tab</a>
                    </div>
                <?php endif; ?>
            <?php elseif (empty($activeMaterial['file_url'])): ?>
                <div class="empty-state">
                    <h2><?= $materialType === 'live_session' ? 'Live session not available' : ($materialType === 'video' ? 'Video not available' : 'Material not available') ?></h2>
                    <p>The description is available below. Admin will add the <?= $materialType === 'live_session' ? 'session link' : ($materialType === 'video' ? 'video' : 'material file') ?> soon.</p>
                </div>
            <?php elseif ($isVideoPlayback): ?>
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
                            data-next-url="<?= $nextVideoItem ? e(public_url($nextVideoItem['url'])) : '' ?>"
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
                <?php $materialSocialLinks = $socialLinksFromText((string) $activeMaterial['description']); ?>
                <?php $materialDescriptionHtml = $learningDescriptionHtml((string) $activeMaterial['description'], (string) $activeMaterial['title']); ?>
                <?php if ($materialDescriptionHtml !== ''): ?>
                    <div class="material-description" data-read-more>
                        <div class="material-description-copy" data-read-more-content><?= $materialDescriptionHtml ?></div>
                        <button class="read-more-toggle" type="button" data-read-more-toggle aria-expanded="false">Read more</button>
                    </div>
                <?php endif; ?>
                <?php if ($materialSocialLinks): ?>
                    <div class="video-social-links" aria-label="Social media links">
                        <?php foreach ($materialSocialLinks as $socialLink): ?>
                            <a class="video-social-link <?= e(strtolower($socialLink['label'])) ?>" href="<?= e($socialLink['url']) ?>" target="_blank" rel="noopener">
                                <?= e($socialLink['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (($materialType !== 'live_session' || $isVideoPlayback) && !empty($activeMaterial['file_url']) && (!$isVideoPlayback || !should_use_native_video_player($activeMaterial['file_url']))): ?>
                <a class="button small" href="<?= e($playbackUrl) ?>" target="_blank" rel="noopener">Open original link</a>
            <?php endif; ?>
            <?php if ($videoPlaylist): ?>
                <section class="up-next-list" aria-label="Course videos">
                    <div class="up-next-header">
                        <div>
                            <p class="eyebrow">Course videos</p>
                            <h2><?= $nextVideoItem ? 'Up next' : 'End of playlist' ?></h2>
                        </div>
                        <?php if ($nextVideoItem): ?>
                            <a class="button small" href="<?= e($nextVideoItem['url']) ?>">Play Next</a>
                        <?php endif; ?>
                    </div>
                    <div class="up-next-items">
                        <?php foreach ($videoPlaylist as $playlistItem): ?>
                            <?php
                                $playlistMaterial = $playlistItem['material'];
                                $playlistProgress = $progressByMaterial[(int) $playlistMaterial['id']] ?? null;
                                $isCurrentPlaylistItem = $activeMaterial && (int) $activeMaterial['id'] === (int) $playlistMaterial['id'];
                            ?>
                            <a class="up-next-item <?= $isCurrentPlaylistItem ? 'active' : '' ?>" href="<?= e($playlistItem['url']) ?>">
                                <span class="up-next-number"><?= str_pad((string) $playlistItem['index'], 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="up-next-copy">
                                    <strong><?= e($playlistMaterial['title']) ?></strong>
                                    <small><?= e($playlistItem['module_title']) ?> / <?= e($playlistItem['topic_title']) ?></small>
                                </span>
                                <?php if ($playlistProgress): ?>
                                    <span class="progress-chip <?= (int) $playlistProgress['is_completed'] === 1 ? 'complete' : '' ?>">
                                        <?= (int) $playlistProgress['is_completed'] === 1 ? 'Completed' : (int) round((float) $playlistProgress['progress_percent']) . '%' ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <section class="course-overview">
                <p class="eyebrow">Course overview</p>
                <h2><?= e($enrollment['title']) ?></h2>
                <?php if (!empty($enrollment['short_description'])): ?>
                    <p class="overview-lead"><?= e($enrollment['short_description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($enrollment['description'])): ?>
                    <div class="overview-copy"><?= text_with_links($enrollment['description']) ?></div>
                <?php endif; ?>
                <?php if (!empty($enrollment['learning_plan'])): ?>
                    <div class="overview-plan">
                        <h3>What you will learn</h3>
                        <?= detail_points($enrollment['learning_plan']) ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <div class="empty-state">
                <h2>No video selected</h2>
                <p>Session videos and learning materials will appear here after admin publishes them.</p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="lesson-list" id="learning-sidebar" aria-label="Course modules">
        <div class="lesson-list-header">
            <h2>Program Modules</h2>
            <button class="lesson-list-close" type="button" data-learning-menu-close aria-label="Close course menu"></button>
        </div>
        <?php $itemIndex = 0; ?>
        <?php foreach ($materialGroups as $module): ?>
            <section class="lesson-module">
                <h3><?= e($module['title']) ?></h3>
                <?php foreach ($module['topics'] as $topic): ?>
                    <div class="lesson-topic">
                        <strong><?= e($topic['title']) ?></strong>
                        <?php foreach ($topic['materials'] as $material): ?>
                            <?php $itemIndex++; ?>
                            <?php $isAccessible = $canAccessMaterial($material); ?>
                            <?php if ($isAccessible): ?>
                                <a class="lesson-item <?= $activeMaterial && (int) $activeMaterial['id'] === (int) $material['id'] ? 'active' : '' ?>" href="learn.php?enrollment_id=<?= (int) $enrollment['id'] ?>&material_id=<?= (int) $material['id'] ?>">
                            <?php else: ?>
                                <div class="lesson-item locked">
                            <?php endif; ?>
                                    <span><?= str_pad((string) $itemIndex, 2, '0', STR_PAD_LEFT) ?></span>
                                    <div>
                                        <strong><?= e($material['title']) ?></strong>
                                        <?php $itemProgress = $progressByMaterial[(int) $material['id']] ?? null; ?>
                                        <?php $itemType = $material['material_type'] ?? 'video'; ?>
                                        <?php $itemIsVideoPlayback = $itemType === 'video' || ($itemType === 'live_session' && is_playable_video_url((string) ($material['file_url'] ?? ''))); ?>
                                        <small>
                                            <?= e($itemIsVideoPlayback ? 'Video' : ucwords(str_replace('_', ' ', $itemType))) ?><?= !$isAccessible ? ' - Locked until payment' : '' ?>
                                            <?php if ($isAccessible && $itemIsVideoPlayback && $itemProgress): ?>
                                                <span class="progress-chip <?= (int) $itemProgress['is_completed'] === 1 ? 'complete' : '' ?>">
                                                    <?= (int) $itemProgress['is_completed'] === 1 ? 'Completed' : (int) round((float) $itemProgress['progress_percent']) . '%' ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                            <?= $isAccessible ? '</a>' : '</div>' ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <?php if (!$materials): ?>
            <p class="empty">No session videos or materials published yet.</p>
        <?php endif; ?>
        <div class="certificate-action">
            <h2>Certificate</h2>
            <?php if ($certificateFeeDue && !$certificatePaid): ?>
                <a class="button primary" href="pay_redirect.php?type=certificate&id=<?= (int) $enrollment['id'] ?>" target="_blank" rel="noopener">Pay Certification Charge</a>
            <?php elseif (!$programPaid): ?>
                <a class="button primary" href="pay_redirect.php?type=program&id=<?= (int) $enrollment['id'] ?>" target="_blank" rel="noopener">Pay Program Fee</a>
            <?php elseif (!$certificate || !certificate_dashboard_is_approved($certificate)): ?>
                <?php if ($certificate): ?>
                    <p>Status: <?= e(dashboard_review_badge($certificate['dashboard_review_status'] ?? 'not_submitted')) ?></p>
                <?php endif; ?>
                <a class="button primary" href="certificate_apply.php?enrollment_id=<?= (int) $enrollment['id'] ?>">Submit Dashboard Link</a>
            <?php elseif ($certificatePaid && $programPaid && $certificate && $certificate['status'] === 'issued' && $certificate['certificate_url']): ?>
                <a class="button primary" href="<?= e($certificate['certificate_url']) ?>" target="_blank" rel="noopener">Download Certificate</a>
            <?php elseif ($certificate): ?>
                <p>Status: <?= e(certificate_badge($certificate['status'])) ?></p>
            <?php else: ?>
                <a class="button primary" href="certificate_apply.php?enrollment_id=<?= (int) $enrollment['id'] ?>">Certificate Details</a>
            <?php endif; ?>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
