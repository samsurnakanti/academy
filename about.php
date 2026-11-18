<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'About | Elldy Academy';
$canonicalUrl = public_url('about');
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">About Elldy Academy</p>
    <h1>Learn and train on Elldy Data Intelligence</h1>
    <p>Elldy is a Data Intelligence Platform. Elldy Academy is its learning and training initiative, offering courses, enrollment, and Certified Solution Provider (CSP) team training focused on using Elldy.</p>
</section>

<section class="section split">
    <div>
        <p class="eyebrow">Our purpose</p>
        <h2>From platform learning to practical solutions</h2>
        <p>We train learners to work as data analysts using Elldy, build dashboards, apply data analytics, and embed analytics into applications. Every learning path connects Elldy platform knowledge with solving business problems and creating intelligent applications.</p>
    </div>
    <div class="info-block">
        <h2>What defines us</h2>
        <?= detail_points("Data analytics using Elldy\nDashboards and business intelligence on Elldy\nEmbedded analytics and intelligent applications\nCourses and enrollment for platform skills\nCertified Solution Provider team training") ?>
    </div>
</section>
<section class="academy-cta"><div><p class="eyebrow">CERTIFIED SOLUTION PROVIDER</p><h2>Train your team to solve with Elldy.</h2><p>Develop Elldy experts who can understand analytics challenges and deliver platform solutions.</p></div><a class="button primary" href="<?= e(public_url('csp.php')) ?>">Explore CSP training &rarr;</a></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
