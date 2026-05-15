<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'Elldy Academy | Data Analytics & BI Training';
$canonicalUrl = public_url();
$courses = active_courses(6);
$latest = db()->query(
    "SELECT e.created_at, u.name, c.title, e.status
     FROM enrollments e
     JOIN users u ON u.id = e.user_id
     JOIN courses c ON c.id = e.course_id
     ORDER BY e.created_at DESC
     LIMIT 8"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Elevate with Elldy</p>
        <h1>Elldy Academy</h1>
        <p>Learn data analytics and business intelligence through Elldy-style business cases, dashboards, data storytelling, and decision-ready reporting.</p>
        <div class="hero-actions">
            <a class="button primary" href="<?= e(public_url('programs')) ?>">Start Free Analytics Session</a>
            <a class="button secondary" href="<?= e(public_url('certification')) ?>">View Certification</a>
        </div>
    </div>
    <div class="hero-panel">
        <div>
            <span>Analytics trial</span>
            <strong>Free</strong>
        </div>
        <div>
            <span>Business cases</span>
            <strong>Live Data</strong>
        </div>
        <div>
            <span>Elldy outcome</span>
            <strong>Dashboards</strong>
        </div>
    </div>
</section>

<section class="section certificate-band">
    <div>
        <p class="eyebrow">Official certification</p>
        <h2>Earn a BI platform certificate for your bright career</h2>
        <p>Elldy Academy provides certification from Arklytics Solutions and Innovations and Elldy Platform, showing your analytics learning is connected to a real BI platform ecosystem.</p>
    </div>
    <div class="certificate-mini">
        <span>Issued by</span>
        <strong>Arklytics Solutions and Innovations</strong>
        <span>with</span>
        <strong>Elldy Platform</strong>
        <a href="<?= e(public_url('certification')) ?>">See certificate details</a>
    </div>
</section>

<section class="section intelligence-strip">
    <div>
        <p class="eyebrow">Learning arm of Elldy</p>
        <h2>Learn analytics for the fast-growing world of business intelligence</h2>
    </div>
    <div class="capability-grid">
        <article>
            <strong>Data Expertise</strong>
            <p>Understand datasets, clean inputs, frame KPIs, and convert raw numbers into reliable business insights.</p>
        </article>
        <article>
            <strong>BI Delivery</strong>
            <p>Create dashboards, executive reports, and visual narratives inspired by how BI platforms support businesses.</p>
        </article>
        <article>
            <strong>Business Cases</strong>
            <p>Work on real-time business scenarios, diagnose problems, and recommend actions from data.</p>
        </article>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Advanced analytics programs</p>
            <h2>BI and Data Analytics Learning Tracks</h2>
        </div>
        <a href="<?= e(public_url('programs')) ?>">View all</a>
    </div>
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
        <?php if (!$courses): ?>
            <p class="empty">No courses are active yet. Add courses from the admin panel.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section split">
    <div>
        <p class="eyebrow">Production workflow</p>
        <h2>From data confusion to business clarity</h2>
        <p>Elldy Academy teaches the workflow behind modern BI platforms: identify the business problem, prepare data, build BI views, and communicate decisions.</p>
        <div class="process-list">
            <span>01. Understand business cases</span>
            <span>02. Analyse data patterns</span>
            <span>03. Build BI dashboards</span>
            <span>04. Present action-ready insights</span>
        </div>
    </div>
    <div class="activity-list">
        <?php foreach ($latest as $row): ?>
            <div class="activity-item">
                <div>
                    <strong><?= e($row['name']) ?></strong>
                    <span><?= e($row['title']) ?></span>
                </div>
                <em><?= e(enrollment_badge($row['status'])) ?></em>
            </div>
        <?php endforeach; ?>
        <?php if (!$latest): ?>
            <p class="empty">No enrollments yet.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
