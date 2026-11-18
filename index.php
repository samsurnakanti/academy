<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'Elldy Data Intelligence Platform Training | Elldy Academy';
$metaDescription = 'Learn the Elldy Data Intelligence Platform through courses and Certified Solution Provider (CSP) team training in data analytics, embedded analytics, and intelligent applications.';
$canonicalUrl = public_url();
$courses = active_courses(6);
$blogPosts = published_blog_posts(3);
require __DIR__ . '/includes/header.php';
?>
<section class="academy-hero">
    <div class="academy-hero-copy">
        <p class="eyebrow"><span class="status-dot"></span> TRAINING ON THE ELLDY DATA INTELLIGENCE PLATFORM</p>
        <h1>Learn Elldy.<br>Build solutions.<br><em>Deliver intelligence.</em></h1>
        <p class="hero-lead">Elldy is a Data Intelligence Platform. At Elldy Academy, learn to use it for data analytics, dashboards, embedded analytics, and turning applications into intelligent experiences.</p>
        <div class="hero-actions">
            <a class="button primary" href="<?= e(public_url('programs')) ?>">Explore programs <span aria-hidden="true">&rarr;</span></a>
            <a class="text-link" href="#csp-training">Build a CSP business <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="hero-assurances"><span>&#10003; Elldy courses</span><span>&#10003; CSP team training</span><span>&#10003; Practical solutions</span></div>
    </div>
    <div class="learning-preview" aria-label="Illustration of the practical learning journey">
        <div class="preview-top"><span class="preview-brand">e<span> / academy</span></span><span class="preview-label">YOUR NEXT CHAPTER</span></div>
        <div class="preview-body">
            <div class="preview-course"><span class="preview-tag">LEARN BY DOING</span><h2>From raw data<br>to real insights.</h2><p>Hands-on learning with Elldy Data Intelligence</p></div>
            <div class="sample-chart"><div class="chart-heading"><strong>Business performance</strong><span>Illustrative project</span></div><div class="chart-bars" aria-hidden="true"><i style="--bar:32%"></i><i style="--bar:48%"></i><i style="--bar:42%"></i><i style="--bar:65%"></i><i style="--bar:58%"></i><i style="--bar:79%"></i><i style="--bar:72%"></i><i style="--bar:95%"></i></div><div class="chart-axis"><span>Explore the data</span><span>Find the story &nearr;</span></div></div>
            <div class="preview-lesson"><span class="lesson-symbol">01</span><div><strong>Understand the business question</strong><span>Start with purpose. Build with data.</span></div><span aria-hidden="true">&rarr;</span></div>
        </div>
        <div class="preview-note"><span class="note-icon" aria-hidden="true">&#10003;</span><div><strong>Knowledge you can put to work.</strong><span>Learn. Practice. Apply.</span></div></div>
    </div>
</section>
<div class="learning-pillars"><span>ALL LEARNING. ONE PLATFORM. ELLDY.</span><strong>Data analytics</strong><span class="pillar-divider">/</span><strong>Embedded analytics</strong><span class="pillar-divider">/</span><strong>Intelligent applications</strong></div>
<section class="section csp-opportunity" id="csp-training" aria-labelledby="csp-opportunity-title">
    <div class="section-heading">
        <div><p class="eyebrow">TRAIN AS A CSP. BUILD YOUR NEXT OPPORTUNITY.</p><h2 id="csp-opportunity-title">Start an analytics business.<br>Or add a new service to yours.</h2><p>Become an Elldy Certified Solution Provider (CSP). Use your expertise in the Elldy Data Intelligence Platform to serve clients, pursue projects, and create new revenue opportunities.</p></div>
    </div>
    <div class="csp-paths">
        <article class="csp-path">
            <span class="csp-path-label">FOR COMPANIES &amp; SERVICE PROVIDERS</span>
            <h3>Business CSP</h3>
            <p class="csp-path-lead">Add analytics to the services your clients already trust you for.</p>
            <p>For software companies, IT service providers, and vendors: train your team on Elldy and expand your client services with data analytics, analysis, embedded analytics, and application intelligence.</p>
            <ul><li>Offer analytics and dashboard services to clients.</li><li>Embed Elldy analytics into existing software.</li><li>Bring data intelligence into client applications.</li><li>Create opportunities for a new service revenue stream.</li></ul>
            <a class="button primary" href="<?= e(public_url('csp.php#business-csp')) ?>">Explore Business CSP <span aria-hidden="true">&rarr;</span></a>
        </article>
        <article class="csp-path">
            <span class="csp-path-label">FOR INDIVIDUALS &amp; ASPIRING FOUNDERS</span>
            <h3>Professional CSP</h3>
            <p class="csp-path-lead">Turn your Elldy expertise into an independent service or startup.</p>
            <p>Train as an Elldy technical expert. Build the skills to solve analytics problems, pursue client projects, and launch your own analytics startup or consulting service.</p>
            <ul><li>Develop practical Elldy platform expertise.</li><li>Provide analytics and embedded analytics solutions.</li><li>Pursue paid projects and client engagements.</li><li>Build your own analytics service business.</li></ul>
            <a class="button primary" href="<?= e(public_url('csp.php#professional-csp')) ?>">Explore Professional CSP <span aria-hidden="true">&rarr;</span></a>
        </article>
    </div>
    <p class="csp-opportunity-note">One platform. Two CSP paths. Build the expertise to create value for your clients.</p>
</section>
<section class="section featured-programs">
    <div class="section-heading"><div><p class="eyebrow">FIND YOUR NEXT STEP</p><h2>Elldy courses. Practical expertise.</h2><p>Explore our launched courses, review the learning plan, and enroll to build your skills using Elldy.</p></div><a class="text-link" href="<?= e(public_url('programs')) ?>">All programs <span aria-hidden="true">&rarr;</span></a></div>
    <div class="course-grid">
        <?php foreach ($courses as $course): ?>
            <?php require __DIR__ . '/includes/program-card.php'; ?>
        <?php endforeach; ?>
        <?php if (!$courses): ?><div class="empty"><h3>Your next learning opportunity is on its way.</h3><p>Contact our team for upcoming programs and guidance.</p><a href="<?= e(public_url('contact')) ?>">Talk to the Academy &rarr;</a></div><?php endif; ?>
    </div>
</section>
<section class="section experience-section" id="learning-experience">
    <div class="experience-intro"><p class="eyebrow">MORE THAN THEORY</p><h2>Learn the platform.<br>Solve the problem.</h2><p>Every learning path is built around the Elldy Data Intelligence Platform. Train as a data analyst using Elldy, work through analytics challenges, and learn to deliver solutions for businesses and applications.</p><a class="text-link" href="<?= e(public_url('about')) ?>">Get to know the Academy <span aria-hidden="true">&rarr;</span></a></div>
    <div class="journey-steps"><article><span>01</span><div><h3>Understand the question</h3><p>Understand the analytics needs of a business and plan how to address them using Elldy.</p></div></article><article><span>02</span><div><h3>Analyse data with Elldy</h3><p>Work with data, explore patterns, and create dashboards and insights within the Elldy platform.</p></div></article><article><span>03</span><div><h3>Bring intelligence into applications</h3><p>Learn embedded analytics with Elldy and bring data intelligence into the applications people use.</p></div></article></div>
</section>
<section class="section credential-section"><div class="credential-art" aria-hidden="true"><div class="credential-sheet"><span>ELLDY ACADEMY</span><div class="credential-seal">&#10023;</div><strong>Learning with<br>recognition.</strong><div class="credential-rule"></div><small>ARKLYTICS &times; ELLDY PLATFORM</small></div></div><div><p class="eyebrow">RECOGNISE YOUR PROGRESS</p><h2>Skills worth building.<br>Learning worth showing.</h2><p>Explore certification from Arklytics Solutions and Innovations and Elldy Platform, connecting your learning to the Elldy Data Intelligence Platform.</p><a class="button secondary" href="<?= e(public_url('certification')) ?>">Explore certification <span aria-hidden="true">&rarr;</span></a><small class="credential-disclaimer">View program details for certification requirements and fees.</small></div></section>
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

<section class="academy-cta"><div><p class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</p><h2>Build your expertise with Elldy.</h2><p>Learn Elldy, train as a CSP, and explore opportunities to launch or expand your analytics services.</p></div><a class="button primary" href="<?= e(public_url('programs')) ?>">Find your program <span aria-hidden="true">&rarr;</span></a></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
