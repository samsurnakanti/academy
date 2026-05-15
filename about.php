<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'About | Elldy Academy';
$canonicalUrl = public_url('about');
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">About Elldy Academy</p>
    <h1>Learning data analytics through business reality</h1>
    <p>Elldy Academy is the official learning initiative of the Elldy platform, built to create awareness, educate learners, and connect analytics education with practical business-case thinking.</p>
</section>

<section class="section split">
    <div>
        <p class="eyebrow">Our purpose</p>
        <h2>From data literacy to decision intelligence</h2>
        <p>We help learners understand how business questions become KPIs, dashboards, reports, and action-ready insights. The Academy is designed for students, professionals, founders, and teams who want analytics learning grounded in real work.</p>
    </div>
    <div class="info-block">
        <h2>What defines us</h2>
        <?= detail_points("Data analytics and BI education\nBusiness-case-based learning\nConnection with the Elldy platform ecosystem\nCareer-focused practical outcomes\nAwareness, education, and applied intelligence") ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
