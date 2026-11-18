<?php
require_once __DIR__ . '/includes/functions.php';
$title = 'Certified Solution Provider (CSP) Team Training | Elldy Academy';
$metaDescription = 'Train your team to become Elldy Certified Solution Providers with expertise in the Elldy Data Intelligence Platform, analytics solutions, and embedded analytics.';
$canonicalUrl = public_url('csp.php');
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">CSP TEAM TRAINING</p>
    <h1>Elldy Certified Solution Provider</h1>
    <p>Build the platform expertise to understand a business problem, solve data analytics issues, and deliver solutions using the Elldy Data Intelligence Platform.</p>
    <div class="hero-actions"><a class="button primary" href="<?= e(public_url('contact#csp-enquiries')) ?>">Discuss CSP team training &rarr;</a><a class="button secondary" href="<?= e(public_url('programs')) ?>">Explore Elldy courses</a></div>
</section>
<section class="section experience-section">
    <div class="experience-intro"><p class="eyebrow">WHAT IS A CSP?</p><h2>An Elldy expert.<br>A solution provider.</h2><p>CSP stands for <strong>Certified Solution Provider</strong>. An Elldy CSP has broad knowledge of the platform and uses that expertise to help businesses solve analytics problems, resolve issues, and put data intelligence to work.</p><p>CSP team training focuses on developing that expertise across your team, from understanding requirements to delivering solutions with Elldy.</p></div>
    <div class="journey-steps">
        <article><span>01</span><div><h3>Know the Elldy platform</h3><p>Develop a comprehensive understanding of Elldy Data Intelligence and how its capabilities support business analytics needs.</p></div></article>
        <article><span>02</span><div><h3>Investigate and solve issues</h3><p>Learn to understand data analytics challenges, identify the problem, and work toward a solution using Elldy.</p></div></article>
        <article><span>03</span><div><h3>Deliver intelligent applications</h3><p>Build analytics experiences and learn to embed Elldy analytics into applications so users can act on insights.</p></div></article>
    </div>
</section>
<section class="section split">
    <div><p class="eyebrow">TWO LEARNING PATHS</p><h2>Courses for skills.<br>CSP training for solution expertise.</h2><p>Elldy courses help learners develop specific skills on the platform. CSP training prepares teams for broader platform knowledge and practical problem solving as solution providers.</p><a class="text-link" href="<?= e(public_url('programs')) ?>">Browse courses and enrollment &rarr;</a></div>
    <div class="info-block"><h2>Plan your team's training</h2><p>Tell us about your team's experience with Elldy, the analytics problems you need to solve, and your application or business use cases.</p><p>Contact the Academy to discuss training scope, enrollment, and CSP certification requirements.</p><a class="button primary" href="<?= e(public_url('contact#csp-enquiries')) ?>">Talk to Elldy Academy &rarr;</a></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
