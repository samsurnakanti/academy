<?php
require_once __DIR__ . '/includes/functions.php';
$title = 'Analytics Programs | Elldy Academy';
$canonicalUrl = public_url('programs');
$courses = active_courses(50);
require __DIR__ . '/includes/header.php';
?>
<section class="page-title catalog-title"><p class="eyebrow">EXPLORE. LEARN. APPLY.</p><h1>Your next skill starts here.</h1><p>Discover practical programs in data analytics and business intelligence.<br>Choose your path and make your next move with confidence.</p></section>
<section class="section program-catalog" data-program-catalog>
    <div class="catalog-toolbar" hidden><div class="catalog-filters" role="group" aria-label="Program format"><button type="button" class="is-active" data-program-filter="all" aria-pressed="true">All programs</button><button type="button" data-program-filter="video" aria-pressed="false">Video learning</button><button type="button" data-program-filter="live" aria-pressed="false">Live sessions</button></div><label class="program-search"><span class="sr-only">Search programs</span><input type="search" placeholder="Search programs..." data-program-search></label></div>
    <p class="catalog-count" aria-live="polite" data-program-count><?= count($courses) ?> programs to explore</p>
    <div class="course-grid"><?php foreach ($courses as $course): ?><?php require __DIR__ . '/includes/program-card.php'; ?><?php endforeach; ?></div>
    <div class="empty" data-program-empty <?= $courses ? 'hidden' : '' ?>><h2>No programs found</h2><p>Try a different search or contact us for upcoming learning opportunities.</p><a href="<?= e(public_url('contact')) ?>">Get program guidance &rarr;</a></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
