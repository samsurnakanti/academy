<?php
require_once __DIR__ . '/includes/functions.php';
$title = 'Elldy Platform Courses & Enrollment | Elldy Academy';
$canonicalUrl = public_url('programs');
$courses = active_courses(50);
require __DIR__ . '/includes/header.php';
?>
<section class="page-title catalog-title"><p class="eyebrow">EXPLORE. LEARN. APPLY.</p><h1>Learn analytics. Work with Elldy.</h1><p>Explore courses taught using the Elldy Data Intelligence Platform.<br>Review each course and enroll to build practical Elldy skills.</p></section>
<div class="learning-pillars"><span>TRAINING YOUR TEAM?</span><strong>Explore the Certified Solution Provider path</strong><a class="text-link" href="<?= e(public_url('csp.php')) ?>">CSP team training &rarr;</a></div>
<section class="section program-catalog" data-program-catalog>
    <div class="catalog-toolbar" hidden><div class="catalog-filters" role="group" aria-label="Program format"><button type="button" class="is-active" data-program-filter="all" aria-pressed="true">All programs</button><button type="button" data-program-filter="video" aria-pressed="false">Video learning</button><button type="button" data-program-filter="live" aria-pressed="false">Live sessions</button></div><label class="program-search"><span class="sr-only">Search programs</span><input type="search" placeholder="Search programs..." data-program-search></label></div>
    <p class="catalog-count" aria-live="polite" data-program-count><?= count($courses) ?> programs to explore</p>
    <div class="course-grid"><?php foreach ($courses as $course): ?><?php require __DIR__ . '/includes/program-card.php'; ?><?php endforeach; ?></div>
    <div class="empty" data-program-empty <?= $courses ? 'hidden' : '' ?>><h2>No programs found</h2><p>Try a different search or contact us for upcoming learning opportunities.</p><a href="<?= e(public_url('contact')) ?>">Get program guidance &rarr;</a></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
