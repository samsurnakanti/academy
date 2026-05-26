<?php
require_once __DIR__ . '/includes/functions.php';
ensure_course_detail_columns();

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);

if ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM courses WHERE slug = ? AND is_active = 1');
    $stmt->execute([$slug]);
} else {
    $stmt = db()->prepare('SELECT * FROM courses WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
}

$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    exit('Program not found.');
}

if ($slug === '' && !empty($course['slug'])) {
    redirect(program_url($course));
}

ensure_material_columns();
$materials = db()->prepare('SELECT * FROM materials WHERE course_id = ? ORDER BY sort_order ASC, created_at ASC, id ASC');
$materials->execute([(int) $course['id']]);
$materialRows = $materials->fetchAll();
$videoRows = array_values(array_filter($materialRows, fn (array $row): bool => ($row['material_type'] ?? 'video') === 'video'));
$resourceRows = array_values(array_filter($materialRows, fn (array $row): bool => ($row['material_type'] ?? 'video') === 'material'));
$liveSessionRows = array_values(array_filter($materialRows, fn (array $row): bool => ($row['material_type'] ?? 'video') === 'live_session'));
$isFreeProgram = course_fee_amount($course) <= 0;
$isLiveSessionCourse = ($course['delivery_type'] ?? 'video') === 'live_session';
$regularFee = max(0, (float) ($course['fee'] ?? 0));
$hasCourseDiscount = $regularFee > 0 && course_fee_amount($course) < $regularFee;

$title = $course['title'] . ' | Elldy Academy';
$canonicalUrl = program_url($course);
require __DIR__ . '/includes/header.php';
?>
<section class="course-detail">
    <div>
        <p class="eyebrow">Analytics program details</p>
        <h1><?= e($course['title']) ?></h1>
        <p><?= nl2br(e($course['description'])) ?></p>
        <?php if (!empty($course['learning_plan'])): ?>
            <div class="info-block">
                <h2>What You Will Learn</h2>
                <?= detail_points($course['learning_plan']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($course['completion_benefits'])): ?>
            <div class="info-block">
                <h2>After Program Completion</h2>
                <?= detail_points($course['completion_benefits']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($course['expert_name']) || !empty($course['expert_bio'])): ?>
            <div class="expert-card">
                <?php if (!empty($course['expert_photo'])): ?>
                    <img src="<?= e($course['expert_photo']) ?>" alt="<?= e($course['expert_name'] ?: 'Elldy Expert') ?>">
                <?php endif; ?>
                <div>
                    <p class="eyebrow">Elldy Expert</p>
                    <h2><?= e($course['expert_name'] ?: 'Expert details') ?></h2>
                    <?php if (!empty($course['expert_title'])): ?>
                        <strong><?= e($course['expert_title']) ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($course['expert_bio'])): ?>
                        <p><?= nl2br(e($course['expert_bio'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($hasCourseDiscount): ?>
            <div class="offer-callout">
                <span>Limited Time Offer</span>
                <strong>Discounted enrollment is active for this program.</strong>
            </div>
        <?php endif; ?>
        <div class="stats-row">
            <span><strong>Duration</strong><?= e($course['duration']) ?></span>
            <span><strong>Fee</strong><?= price_html($course, 'fee', 'discount_fee') ?></span>
            <span><strong>Certification</strong><?= certificate_fee_amount($course) > 0 ? price_html($course, 'certification_fee', 'certificate_discount_fee') : 'Included' ?></span>
            <?php if ($isFreeProgram): ?>
                <span><strong>Access</strong>Entire program free</span>
            <?php elseif ($isLiveSessionCourse): ?>
                <span><strong>First session</strong>Free access</span>
            <?php else: ?>
                <span><strong>Access</strong>Full video course</span>
            <?php endif; ?>
        </div>
        <a class="button primary" href="<?= e(public_url('enroll.php?course_id=' . (int) $course['id'])) ?>">Enroll Now</a>
        <?php if ($videoRows): ?>
            <div class="info-block">
                <h2>Course Videos</h2>
                <div class="video-outline">
                    <?php foreach ($videoRows as $index => $video): ?>
                        <div>
                            <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <strong><?= e($video['title']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($resourceRows || $liveSessionRows): ?>
            <div class="info-block">
                <h2>Program Materials</h2>
                <p>These learning items are included in the program and become available inside the learning workspace after enrollment.</p>
                <div class="material-outline">
                    <?php foreach ($resourceRows as $resource): ?>
                        <article>
                            <span>Material</span>
                            <strong><?= e($resource['title']) ?></strong>
                            <small><?= e($resource['description'] ?: 'Supporting learning material') ?></small>
                        </article>
                    <?php endforeach; ?>
                    <?php foreach ($liveSessionRows as $session): ?>
                        <article>
                            <span>Live Session</span>
                            <strong><?= e($session['title']) ?></strong>
                            <small><?= e($session['description'] ?: 'Scheduled live session') ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <aside class="detail-aside">
        <h2>Platform Support</h2>
        <div class="material-item">
            <strong>Real business cases</strong>
            <p>Practice with case-style analytics scenarios across sales, finance, marketing, and operations.</p>
        </div>
        <div class="material-item">
            <strong>BI delivery mindset</strong>
            <p>Learn how dashboards, KPIs, and insight summaries support business decisions.</p>
        </div>
        <div class="material-item">
            <strong>Enrolled learner access</strong>
            <p>Video links, live-session links, and downloadable resources are opened from the protected learning workspace after enrollment.</p>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
