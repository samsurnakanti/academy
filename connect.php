<?php
require_once __DIR__ . '/includes/functions.php';

$title = 'Connect | Elldy Academy';
$canonicalUrl = public_url('connect');
$connectLinks = connect_links(true);
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">Connect</p>
    <h1>Reach Elldy Academy</h1>
    <p>Choose the channel that works best for support, updates, resources, and community links.</p>
</section>

<section class="section connect-grid">
    <?php foreach ($connectLinks as $item): ?>
        <a class="connect-card" href="<?= e($item['link_url']) ?>" target="_blank" rel="noopener">
            <span class="connect-card-media">
                <?php if (trim((string) ($item['image_url'] ?? '')) !== ''): ?>
                    <img src="<?= e((string) $item['image_url']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                <?php else: ?>
                    <span><?= e(strtoupper(substr((string) $item['title'], 0, 1))) ?></span>
                <?php endif; ?>
            </span>
            <span class="connect-card-copy">
                <strong><?= e($item['title']) ?></strong>
                <small><?= e(preg_replace('~^https?://(www\.)?~i', '', (string) $item['link_url'])) ?></small>
            </span>
        </a>
    <?php endforeach; ?>
    <?php if (!$connectLinks): ?>
        <div class="empty-state">
            <h2>Connect links will appear here soon</h2>
            <p>Admin can add social links and support numbers from the Connect Links page.</p>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
