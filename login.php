<?php
require_once __DIR__ . '/includes/functions.php';
ensure_login_otp_table();
ensure_user_remember_tokens_table();

if (current_user()) {
    redirect('dashboard.php');
}

if (isset($_GET['reset'])) {
    unset($_SESSION['otp_user_id'], $_SESSION['otp_phone']);
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'send_otp';

    if ($action === 'send_otp') {
        $phone = trim($_POST['phone'] ?? '');
        $user = find_user_by_whatsapp($phone);

        if (!$user) {
            flash('error', 'No trainee account found with this WhatsApp number.');
        } elseif (create_login_otp($user)) {
            $_SESSION['otp_user_id'] = (int) $user['id'];
            $_SESSION['otp_phone'] = normalize_whatsapp_number($phone);
            flash('success', 'OTP sent to your WhatsApp number.');
        } else {
            flash('error', $_SESSION['whatsapp_send_error'] ?? 'Unable to send WhatsApp OTP. Please check WhatsApp API configuration.');
        }
    } elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        $userId = (int) ($_SESSION['otp_user_id'] ?? 0);
        $phone = (string) ($_SESSION['otp_phone'] ?? '');

        if ($userId && $phone !== '' && verify_login_otp($userId, $phone, $otp)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            create_remembered_device($userId);
            unset($_SESSION['otp_user_id'], $_SESSION['otp_phone']);
            flash('success', 'You are logged in.');
            redirect('dashboard.php');
        }

        flash('error', 'Invalid or expired OTP.');
    }
}

$title = 'Login | Elldy Academy';
require __DIR__ . '/includes/header.php';
$awaitingOtp = !empty($_SESSION['otp_user_id']) && !empty($_SESSION['otp_phone']);
?>
<section class="auth-box">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h1>WhatsApp OTP Login</h1>
        <?php if ($awaitingOtp): ?>
            <input type="hidden" name="action" value="verify_otp">
            <fieldset>
                <legend>Verify OTP</legend>
                <label>Enter OTP
                    <input name="otp" inputmode="numeric" maxlength="6" placeholder="6-digit OTP" required>
                </label>
            </fieldset>
            <button class="button primary" type="submit">Verify & Login</button>
            <p><a href="login.php?reset=1">Use another WhatsApp number</a></p>
        <?php else: ?>
            <input type="hidden" name="action" value="send_otp">
            <fieldset>
                <legend>Passwordless Access</legend>
                <label>WhatsApp number
                    <input name="phone" inputmode="tel" placeholder="Enter registered WhatsApp number" required>
                </label>
            </fieldset>
            <button class="button primary" type="submit">Send WhatsApp OTP</button>
        <?php endif; ?>
        <p>New trainee? <a href="register.php">Create account</a></p>
        <p>Admin? <a href="admin/login.php">Open admin login</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
