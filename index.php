<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'Elldy Cloud Data & BI Platform Training | Elldy Academy';
$metaDescription = 'Elldy Academy trains learners and teams to use Elldy Cloud Data and BI platform thinking for dashboards, business cases, KPI reporting, and practical data expertise.';
$canonicalUrl = public_url();
$courses = active_courses(6);
$blogPosts = published_blog_posts(3);
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Elldy Cloud Data and BI Platform</p>
        <h1>Build industry-ready data expertise with Elldy.</h1>
        <p>Elldy Cloud helps industries turn business data into dashboards, KPI reports, and decision-ready BI. Elldy Academy trains learners and teams to work inside that data ecosystem with practical business cases.</p>
        <div class="hero-definition">
            <strong>What is Elldy Academy?</strong>
            <span>An industry-trained learning platform for data expertise, BI workflows, dashboard building, and real business problem solving with the Elldy Cloud Data and BI platform.</span>
        </div>
        <div class="hero-actions">
            <a class="button primary" href="<?= e(public_url('programs')) ?>">Explore Data Programs</a>
            <a class="button platform" href="https://elldy.com/" target="_blank" rel="noopener">Elldy Data &amp; BI</a>
            <a class="button secondary" href="<?= e(public_url('certification')) ?>">View BI Certification</a>
        </div>
    </div>
    <div class="hero-panel">
        <div>
            <span>Platform focus</span>
            <strong>Cloud Data</strong>
        </div>
        <div>
            <span>Industry outcome</span>
            <strong>BI Dashboards</strong>
        </div>
        <div>
            <span>Academy path</span>
            <strong>Trained Talent</strong>
        </div>
    </div>
</section>

<section class="section certificate-band">
    <div>
        <p class="eyebrow">Official certification</p>
        <h2>Train for Elldy Cloud Data and BI platform work</h2>
        <p>Elldy Academy provides certification from Arklytics Solutions and Innovations and Elldy Platform, showing that your learning is connected to a real cloud data intelligence and business intelligence ecosystem.</p>
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
        <p class="eyebrow">Education for the Elldy ecosystem</p>
        <h2>Build the data skills industries need from BI teams</h2>
    </div>
    <div class="capability-grid">
        <article>
            <strong>Data Expertise</strong>
            <p>Understand datasets, clean inputs, frame KPIs, and convert raw numbers into reliable business insights for teams and leaders.</p>
        </article>
        <article>
            <strong>BI Platform Thinking</strong>
            <p>Learn how Elldy Cloud Data and BI helps companies monitor performance, create dashboards, and act on data.</p>
        </article>
        <article>
            <strong>Business Cases</strong>
            <p>Practice analytics through sales, marketing, finance, operations, and leadership scenarios that businesses face every day.</p>
        </article>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Platform-led analytics programs</p>
            <h2>Learn data analytics, business analytics, and BI workflows</h2>
        </div>
        <a href="<?= e(public_url('programs')) ?>">View all</a>
    </div>
    <div class="course-grid">
        <?php foreach ($courses as $course): ?>
            <?php $isFreeProgram = course_fee_amount($course) <= 0; ?>
            <article class="course-card <?= $isFreeProgram ? 'is-free' : 'is-paid' ?>">
                <div class="course-topline">
                    <span><?= e($course['duration']) ?></span>
                    <?= price_html($course, 'fee', 'discount_fee') ?>
                </div>
                <div class="course-badges">
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
        <?php if (!$courses): ?>
            <p class="empty">No courses are active yet. Add courses from the admin panel.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($blogPosts): ?>
<section class="section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Latest articles</p>
            <h2>Daily analytics and BI learning notes</h2>
        </div>
        <a href="<?= e(public_url('blog.php')) ?>">View blog</a>
    </div>
    <div class="blog-grid compact">
        <?php foreach ($blogPosts as $post): ?>
            <article class="blog-card">
                <?php if (!empty($post['featured_image_url'])): ?>
                    <a href="<?= e(blog_url($post)) ?>" class="blog-card-image">
                        <img src="<?= e(s3_display_url((string) $post['featured_image_url'])) ?>" alt="<?= e($post['title']) ?>">
                    </a>
                <?php endif; ?>
                <div class="blog-card-body">
                    <div class="blog-meta">
                        <span><?= e(date('d M Y', strtotime((string) ($post['published_at'] ?: $post['created_at'])))) ?></span>
                        <span><?= blog_reading_minutes((string) $post['body']) ?> min read</span>
                    </div>
                    <h3><a href="<?= e(blog_url($post)) ?>"><?= e($post['title']) ?></a></h3>
                    <p><?= e($post['excerpt'] ?: substr(strip_tags((string) $post['body']), 0, 140)) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section faq-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Common questions</p>
            <h2>Before you start learning</h2>
        </div>
        <a href="<?= e(public_url('contact')) ?>">Need help?</a>
    </div>
    <div class="faq-grid">
        <?php foreach (academy_faqs() as $faq): ?>
            <details class="faq-item">
                <summary><?= e($faq['question']) ?></summary>
                <p><?= e($faq['answer']) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</section>

<section class="section split">
    <div>
        <p class="eyebrow">For learners and business teams</p>
        <h2>From business questions to data-driven decisions</h2>
        <p>Elldy Academy teaches the workflow behind modern BI platforms: understand a business problem, prepare data, build BI views, integrate analytics thinking into business operations, and communicate decisions clearly.</p>
        <div class="process-list">
            <span>01. Understand business cases</span>
            <span>02. Analyse data patterns</span>
            <span>03. Build BI dashboards</span>
            <span>04. Apply insights to business decisions</span>
        </div>
    </div>
    <div class="activity-list">
        <div class="activity-item">
            <div>
                <strong>For students and analysts</strong>
                <span>Build practical confidence in datasets, reporting, KPIs, dashboards, and analytics storytelling.</span>
            </div>
        </div>
        <div class="activity-item">
            <div>
                <strong>For CEOs and departments</strong>
                <span>Understand how sales, marketing, finance, and operations teams can use analytics in daily decisions.</span>
            </div>
        </div>
        <div class="activity-item">
            <div>
                <strong>For business integration</strong>
                <span>Learn how BI platforms can connect data, people, and decisions across a growing business.</span>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
