<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'Certification | Elldy Academy';
$canonicalUrl = public_url('certification');
require __DIR__ . '/includes/header.php';
?>
<section class="page-title certification-hero">
    <p class="eyebrow">Official BI Platform Certificate</p>
    <h1>Certification from Arklytics Solutions and Innovations and Elldy Platform</h1>
    <p>Elldy Academy helps you learn data analytics and business intelligence through real business cases, and successful trainees receive an official certificate connected to the Elldy BI platform ecosystem.</p>
</section>

<section class="section certification-layout">
    <div class="certificate-preview">
        <p class="eyebrow">Career credential</p>
        <h2>Elldy BI Platform Certificate</h2>
        <p>Issued for your bright career in analytics, reporting, dashboards, and business intelligence.</p>
        <div class="certificate-stamp">
            <span>Official</span>
            <strong>Arklytics + Elldy</strong>
        </div>
    </div>

    <div class="certification-copy">
        <p class="eyebrow">Why this certificate matters</p>
        <h2>Not just learning from an institution. Learning with a BI platform.</h2>
        <p>Tools like Power BI and Tableau are platforms. They usually do not issue individual course certificates directly to learners. Training institutes teach those tools and issue their own certificates.</p>
        <p>With Elldy Academy, you learn analytics through the Elldy BI platform approach, and the certificate is officially provided by <strong>Arklytics Solutions and Innovations</strong> and <strong>Elldy Platform</strong>.</p>

        <div class="capability-grid cert-grid">
            <article>
                <strong>Official platform connection</strong>
                <p>Your certificate is associated with the Elldy BI platform learning ecosystem.</p>
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

        <a class="button primary" href="<?= e(public_url('programs')) ?>">Start Free Analytics Session</a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
