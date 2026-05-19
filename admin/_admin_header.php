<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Admin') ?> | Elldy Academy</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(public_url('assets/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header admin-header">
    <a class="brand" href="index.php">
        <span class="brand-lockup">
            <img class="brand-logo" src="<?= e(public_url('assets/elldy-logo.png')) ?>" alt="Elldy">
            <span class="brand-label">Academy Admin</span>
        </span>
    </a>
    <nav class="main-nav">
        <a href="index.php">Dashboard</a>
        <a href="courses.php">Programs</a>
        <a href="materials.php">Materials</a>
        <a href="enrollments.php">Enrollments</a>
        <a href="progress.php">Progress</a>
        <a href="certificates.php">Certificates</a>
        <span class="nav-dropdown">
            <button type="button">Settings</button>
            <span class="nav-dropdown-menu">
                <a href="security.php">Security</a>
                <a href="s3.php">S3</a>
                <a href="razorpay.php">Razorpay</a>
                <a href="whatsapp.php">WhatsApp</a>
            </span>
        </span>
        <a href="../index.php">Website</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>
<main>
<?php foreach (flashes() as $item): ?>
    <div class="flash <?= e($item['type']) ?>"><?= e($item['message']) ?></div>
<?php endforeach; ?>
