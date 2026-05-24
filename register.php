<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = normalize_whatsapp_number(trim($_POST['phone'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        flash('error', 'Enter a valid name, email, and WhatsApp number.');
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            record_user_login((int) $_SESSION['user_id']);
            flash('success', 'Welcome to Elldy Academy. You can choose an analytics program now.');
            redirect('courses.php');
        } catch (PDOException) {
            flash('error', 'This email is already registered.');
        }
    }
}

$title = 'Register | Elldy Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-box">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h1>Create Account</h1>
        <fieldset>
            <legend>Profile Details</legend>
            <label>Name <input name="name" placeholder="Enter full name" required></label>
            <label>Email <input type="email" name="email" placeholder="name@example.com" required></label>
            <label>Phone <input name="phone" placeholder="Mobile or WhatsApp number"></label>
        </fieldset>
        <button class="button primary" type="submit">Register</button>
        <p>Already registered? <a href="login.php">Login with WhatsApp OTP</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
