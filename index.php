<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'Elldy Cloud Data & BI Platform Training | Elldy Academy';
$metaDescription = 'Elldy Academy trains learners and teams to use Elldy Cloud Data and BI platform thinking for dashboards, business cases, KPI reporting, and practical data expertise.';
$canonicalUrl = public_url();
$courses = active_courses(6);
$blogPosts = published_blog_posts(3);
require __DIR__ . '/includes/header.php';
?>
<section class="academy-hero">
    <div class="academy-hero-copy">
        <p class="eyebrow"><span class="status-dot"></span> THE ELLDY LEARNING EXPERIENCE</p>
        <h1>Learn the skills.<br>Build the confidence.<br><em>Make an impact.</em></h1>
        <p class="hero-lead">Turn your curiosity about data into practical expertise. Learn analytics, build BI dashboards, and solve business problems with Elldy Academy.</p>
        <div class="hero-actions">
            <a class="button primary" href="<?= e(public_url('programs')) ?>">Explore programs <span aria-hidden="true">&rarr;</span></a>
            <a class="text-link" href="#learning-experience">How you'll learn <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="hero-assurances"><span>&#10003; Practical learning</span><span>&#10003; Business-focused skills</span><span>&#10003; Elldy platform</span></div>
    </div>
    <div class="learning-preview" aria-label="Illustration of the practical learning journey">
        <div class="preview-top"><span class="preview-brand">e<span> / academy</span></span><span class="preview-label">YOUR NEXT CHAPTER</span></div>
        <div class="preview-body">
            <div class="preview-course"><span class="preview-tag">LEARN BY DOING</span><h2>From raw data<br>to real insights.</h2><p>A practical journey in analytics &amp; BI</p></div>
            <div class="sample-chart"><div class="chart-heading"><strong>Business performance</strong><span>Illustrative project</span></div><div class="chart-bars" aria-hidden="true"><i style="--bar:32%"></i><i style="--bar:48%"></i><i style="--bar:42%"></i><i style="--bar:65%"></i><i style="--bar:58%"></i><i style="--bar:79%"></i><i style="--bar:72%"></i><i style="--bar:95%"></i></div><div class="chart-axis"><span>Explore the data</span><span>Find the story &nearr;</span></div></div>
            <div class="preview-lesson"><span class="lesson-symbol">01</span><div><strong>Understand the business question</strong><span>Start with purpose. Build with data.</span></div><span aria-hidden="true">&rarr;</span></div>
        </div>
        <div class="preview-note"><span class="note-icon" aria-hidden="true">&#10003;</span><div><strong>Knowledge you can put to work.</strong><span>Learn. Practice. Apply.</span></div></div>
    </div>
</section>
<div class="learning-pillars"><span>BUILT AROUND REAL-WORLD LEARNING</span><strong>Data analytics</strong><span class="pillar-divider">/</span><strong>Business intelligence</strong><span class="pillar-divider">/</span><strong>Better decisions</strong></div>
<section class="section featured-programs">
    <div class="section-heading"><div><p class="eyebrow">FIND YOUR NEXT STEP</p><h2>Big ambitions. Practical programs.</h2><p>Explore learning that connects data skills with the way businesses work.</p></div><a class="text-link" href="<?= e(public_url('programs')) ?>">All programs <span aria-hidden="true">&rarr;</span></a></div>
    <div class="course-grid">
        <?php foreach ($courses as $course): ?>
            <?php require __DIR__ . '/includes/program-card.php'; ?>
        <?php endforeach; ?>
        <?php if (!$courses): ?><div class="empty"><h3>Your next learning opportunity is on its way.</h3><p>Contact our team for upcoming programs and guidance.</p><a href="<?= e(public_url('contact')) ?>">Talk to the Academy &rarr;</a></div><?php endif; ?>
    </div>
</section>
<section class="section experience-section" id="learning-experience">
    <div class="experience-intro"><p class="eyebrow">MORE THAN THEORY</p><h2>Don't just learn it.<br>Learn to use it.</h2><p>Connect every concept to a business question. Develop the thinking behind meaningful dashboards, reliable KPIs, and confident decisions.</p><a class="text-link" href="<?= e(public_url('about')) ?>">Get to know the Academy <span aria-hidden="true">&rarr;</span></a></div>
    <div class="journey-steps"><article><span>01</span><div><h3>Understand the question</h3><p>Explore business scenarios across sales, finance, marketing, and operations.</p></div></article><article><span>02</span><div><h3>Work with the data</h3><p>Prepare datasets, explore patterns, and build your analytics and BI skills.</p></div></article><article><span>03</span><div><h3>Turn insight into action</h3><p>Build dashboards and communicate what the numbers mean for a business.</p></div></article></div>
</section>
<section class="section credential-section"><div class="credential-art" aria-hidden="true"><div class="credential-sheet"><span>ELLDY ACADEMY</span><div class="credential-seal">&#10023;</div><strong>Learning with<br>recognition.</strong><div class="credential-rule"></div><small>ARKLYTICS &times; ELLDY PLATFORM</small></div></div><div><p class="eyebrow">RECOGNISE YOUR PROGRESS</p><h2>Skills worth building.<br>Learning worth showing.</h2><p>Explore certification from Arklytics Solutions and Innovations and Elldy Platform, connecting your learning to the Elldy Cloud Data and BI ecosystem.</p><a class="button secondary" href="<?= e(public_url('certification')) ?>">Explore certification <span aria-hidden="true">&rarr;</span></a><small class="credential-disclaimer">View program details for certification requirements and fees.</small></div></section>
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

<section class="academy-cta"><div><p class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</p><h2>Build a stronger future with data.</h2><p>Find your program. Take your first step. Put your learning to work.</p></div><a class="button primary" href="<?= e(public_url('programs')) ?>">Find your program <span aria-hidden="true">&rarr;</span></a></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
