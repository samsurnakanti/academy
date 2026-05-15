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
            <article class="course-card">
                <div class="course-topline">
                    <span><?= e($course['duration']) ?></span>
                    <strong><?= money($course['fee']) ?></strong>
                </div>
                <h3><?= e($course['title']) ?></h3>
                <p><?= e($course['short_description']) ?></p>
                <a class="button small" href="<?= e(program_url($course)) ?>">View Program</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
