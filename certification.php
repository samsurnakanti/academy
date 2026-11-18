<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'Certification | Elldy Academy';
$canonicalUrl = public_url('certification');
require __DIR__ . '/includes/header.php';
?>
<section class="page-title certification-hero">
    <p class="eyebrow">Elldy platform learning & certification</p>
    <h1>Certification from Arklytics Solutions and Innovations and Elldy Platform</h1>
    <p>Learn using the Elldy Data Intelligence Platform. Explore course certificates for eligible programs and the Certified Solution Provider (CSP) training path for teams developing broader Elldy expertise.</p>
</section>

<section class="section certification-layout">
    <div class="certificate-preview">
        <p class="eyebrow">Career credential</p>
        <h2>Elldy Platform Course Certificate</h2>
        <p>Recognition of learning in your completed Elldy program, subject to its certification requirements.</p>
        <div class="certificate-stamp">
            <span>Official</span>
            <strong>Arklytics + Elldy</strong>
        </div>
    </div>

    <div class="certification-copy">
        <p class="eyebrow">Why this certificate matters</p>
        <h2>Recognise your learning on Elldy.</h2>
        <p>Course certificates recognise learning in a specific Elldy program. Check the program details for eligibility, completion requirements, and any certificate fees.</p>
        <p>With Elldy Academy, you learn analytics using the Elldy Data Intelligence Platform, and the certificate is officially provided by <strong>Arklytics Solutions and Innovations</strong> and <strong>Elldy Platform</strong>.</p>

        <div class="capability-grid cert-grid">
            <article>
                <strong>Official platform connection</strong>
                <p>Your certificate is associated with the Elldy Data Intelligence Platform learning ecosystem.</p>
            </article>
            <article>
                <strong>Business case proof</strong>
                <p>Shows that you practiced analytics with dashboards, KPIs, and real business scenarios.</p>
            </article>
            <article>
                <strong>Career focused</strong>
                <p>Designed to support your profile for analytics, BI, MIS, reporting, and data roles.</p>
            </article>
        </div>

        <div class="info-block">
            <h2>Certificate Includes</h2>
            <?= detail_points("Trainee name and completed analytics program\nIssued by Arklytics Solutions and Innovations\nElldy Platform recognition\nBI and data analytics learning completion\nCareer-focused credential for your portfolio") ?>
        </div>

        <a class="button primary" href="<?= e(public_url('programs')) ?>">Explore Elldy courses</a>
    </div>
</section>
<section class="academy-cta"><div><p class="eyebrow">CSP: CERTIFIED SOLUTION PROVIDER</p><h2>Build broader Elldy solution expertise.</h2><p>CSP training is a separate path for teams. A course certificate does not automatically grant CSP status. Speak with the Academy about CSP requirements.</p></div><a class="button primary" href="<?= e(public_url('csp.php')) ?>">CSP team training &rarr;</a></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
