<?php
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = (int) $admin['id'];
        flash('success', 'Welcome back, admin.');
        redirect('index.php');
    }

    flash('error', 'Invalid admin login.');
}

$title = 'Admin Login';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | Elldy Academy</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(public_url('assets/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(public_url('assets/css/style.css')) ?>">
</head>
<body>
<main>
<?php foreach (flashes() as $item): ?>
    <div class="flash <?= e($item['type']) ?>"><?= e($item['message']) ?></div>
<?php endforeach; ?>
<section class="auth-box">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h1>Admin Login</h1>
        <fieldset>
            <legend>Secure Access</legend>
            <label>Username <input name="username" placeholder="Admin username" required></label>
            <label>Password <input type="password" name="password" placeholder="Admin password" required></label>
        </fieldset>
        <button class="button primary" type="submit">Login</button>
        <p><a href="<?= e(public_url()) ?>">Back to website</a></p>
    </form>
</section>
</main>
</body>
</html>
