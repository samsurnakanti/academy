<?php
require_once __DIR__ . '/includes/functions.php';
$title = 'Analytics Programs | Elldy Academy';
$canonicalUrl = public_url('programs');
$courses = active_courses(50);
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">Elldy Academy</p>
    <h1>Analytics Programs</h1>
    <p>Choose a BI, data analytics, or business case program and enroll without signup or login.</p>
</section>

<section class="section">
    <div class="course-grid">
        <?php foreach ($courses as $course): ?>
            <?php
            $isFreeProgram = course_fee_amount($course) <= 0;
            $regularFee = max(0, (float) ($course['fee'] ?? 0));
            $hasCourseDiscount = $regularFee > 0 && course_fee_amount($course) < $regularFee;
            ?>
            <article class="course-card <?= $isFreeProgram ? 'is-free' : 'is-paid' ?> <?= $hasCourseDiscount ? 'is-discounted' : '' ?>">
                <div class="course-topline">
                    <span><?= e($course['duration']) ?></span>
                    <?= price_html($course, 'fee', 'discount_fee') ?>
                </div>
                <div class="course-badges">
                    <?php if ($hasCourseDiscount): ?>
                        <span class="course-badge offer">Limited Time Offer</span>
                    <?php endif; ?>
                    <?php if ($isFreeProgram): ?>
                        <span class="course-badge free">Free</span>
                    <?php else: ?>
                        <span class="course-badge certificate">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 3 6 6v5c0 4.2 2.55 8.1 6 9.6 3.45-1.5 6-5.4 6-9.6V6l-6-3Zm0 2.2 4 2v3.8c0 3.1-1.7 6.06-4 7.45-2.3-1.39-4-4.35-4-7.45V7.2l4-2Zm-1 4.3v4l3.4 2 .8-1.38-2.7-1.57V9.5H11Z"/>
                            </svg>
                            Certificate
                        </span>
                        <span class="course-badge premium">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m12 3 2.47 5.01L20 8.82l-4 3.9.94 5.51L12 15.63l-4.94 2.6L8 12.72l-4-3.9 5.53-.81L12 3Z"/>
                            </svg>
                            Elldy Premium Access
                        </span>
                    <?php endif; ?>
                </div>
                <h3><?= e($course['title']) ?></h3>
                <p><?= e($course['short_description']) ?></p>
                <a class="button small" href="<?= e(program_url($course)) ?>">View Program</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
