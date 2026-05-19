<?php
$user = current_user();
$admin = current_admin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Elldy Academy') ?></title>
    <?php if (!empty($canonicalUrl)): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(public_url('assets/favicon.svg')) ?>">
    <link rel="manifest" href="<?= e(public_url('manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(public_url('assets/icons/app-icon-192.png')) ?>">
    <meta name="theme-color" content="#0b6bcb">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>
<body data-service-worker-url="<?= e(public_url('service-worker.js')) ?>">
<header class="site-header">
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation" aria-label="Open menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <a class="brand" href="<?= e(public_url()) ?>">
        <span class="brand-lockup">
            <img class="brand-logo" src="<?= e(public_url('assets/elldy-logo.png')) ?>" alt="Elldy">
            <span class="brand-label">Academy</span>
        </span>
    </a>
    <nav class="main-nav" id="main-navigation">
        <a href="<?= e(public_url()) ?>">Home</a>
        <a href="<?= e(public_url('programs')) ?>">Programs</a>
        <a href="<?= e(public_url('certification')) ?>">Certification</a>
        <?php if ($user): ?>
            <a href="<?= e(public_url('dashboard.php')) ?>">My Sessions</a>
            <a href="<?= e(public_url('profile.php')) ?>">Profile</a>
            <a href="<?= e(public_url('logout.php')) ?>">Logout</a>
        <?php else: ?>
            <a href="<?= e(public_url('login.php')) ?>">Login</a>
            <a class="nav-cta" href="<?= e(public_url('programs')) ?>">Free Analytics Session</a>
        <?php endif; ?>
        <?php if ($admin): ?>
            <a href="<?= e(public_url('admin/index.php')) ?>">Admin</a>
        <?php endif; ?>
    </nav>
</header>
<main>
<?php foreach (flashes() as $item): ?>
    <div class="flash <?= e($item['type']) ?>"><?= e($item['message']) ?></div>
<?php endforeach; ?>
