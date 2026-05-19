<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = normalize_whatsapp_number(trim($_POST['phone'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        flash('error', 'Enter a valid name, email, and WhatsApp number.');
    } else {
        $existing = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $existing->execute([$email, (int) $user['id']]);

        if ($existing->fetch()) {
            flash('error', 'This email is already used by another trainee account.');
        } else {
            $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
            $stmt->execute([$name, $email, $phone, (int) $user['id']]);
            flash('success', 'Your trainee profile has been updated.');
            redirect('profile.php');
        }
    }

    $user = current_user() ?: $user;
}

$title = 'My Profile | Elldy Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="page-title">
    <p class="eyebrow">Trainee profile</p>
    <h1>My Profile</h1>
    <p>Update your name, email address, and WhatsApp number anytime.</p>
</section>

<section class="auth-box">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <fieldset>
            <legend>Profile Details</legend>
            <label>Name
                <input name="name" value="<?= e($user['name']) ?>" placeholder="Enter full name" required>
            </label>
            <label>Email
                <input type="email" name="email" value="<?= e($user['email']) ?>" placeholder="name@example.com" required>
            </label>
            <label>WhatsApp number
                <input name="phone" value="<?= e($user['phone']) ?>" inputmode="tel" placeholder="Mobile or WhatsApp number" required>
            </label>
        </fieldset>
        <button class="button primary" type="submit">Update Profile</button>
        <p><a href="dashboard.php">Back to my sessions</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
