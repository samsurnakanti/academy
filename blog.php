<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug !== '') {
    ensure_blog_posts_table();
    $stmt = db()->prepare(
        "SELECT *
         FROM blog_posts
         WHERE slug = ? AND status = 'published' AND (published_at IS NULL OR published_at <= NOW())
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(404);
        exit('Blog post not found.');
    }

    $title = $post['title'] . ' | Elldy Academy Blog';
    $canonicalUrl = blog_url($post);
    $metaDescription = trim((string) ($post['meta_description'] ?: $post['excerpt']));
    require __DIR__ . '/includes/header.php';
    ?>
    <article class="blog-detail">
        <header class="blog-detail-header">
            <p class="eyebrow">Elldy Academy Blog</p>
            <h1><?= e($post['title']) ?></h1>
            <?php if (!empty($post['excerpt'])): ?>
                <p><?= e($post['excerpt']) ?></p>
            <?php endif; ?>
            <div class="blog-meta">
                <span><?= e($post['author_name'] ?: 'Elldy Academy') ?></span>
                <span><?= e(date('d M Y', strtotime((string) ($post['published_at'] ?: $post['created_at'])))) ?></span>
                <span><?= blog_reading_minutes((string) $post['body']) ?> min read</span>
            </div>
        </header>

        <?php if (!empty($post['featured_image_url'])): ?>
            <img class="blog-featured-image" src="<?= e(s3_display_url((string) $post['featured_image_url'])) ?>" alt="<?= e($post['title']) ?>">
        <?php endif; ?>

        <div class="blog-content">
            <?= nl2br(e($post['body'])) ?>
        </div>
    </article>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$title = 'Blog | Elldy Academy';
$canonicalUrl = public_url('blog');
$metaDescription = 'Read daily Elldy Academy articles about data analytics, business intelligence, dashboards, career learning, and practical BI workflows.';
$posts = published_blog_posts(60);
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">Elldy Academy Blog</p>
    <h1>Data Analytics, BI, and Career Insights</h1>
    <p>Daily learning notes, platform updates, analytics ideas, and practical guidance for students, analysts, and business teams.</p>
</section>

<section class="section">
    <div class="blog-grid">
        <?php foreach ($posts as $post): ?>
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
                    <h2><a href="<?= e(blog_url($post)) ?>"><?= e($post['title']) ?></a></h2>
                    <p><?= e($post['excerpt'] ?: substr(strip_tags((string) $post['body']), 0, 170)) ?></p>
                    <a class="button small" href="<?= e(blog_url($post)) ?>">Read Article</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$posts): ?>
            <p class="empty">No blog posts are published yet.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
