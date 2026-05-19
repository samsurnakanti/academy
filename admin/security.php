<?php
$title = 'Admin Security';
require __DIR__ . '/_admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($username === '') {
        flash('error', 'Admin username is required.');
    } elseif (!password_verify($currentPassword, (string) $admin['password_hash'])) {
        flash('error', 'Current password is incorrect.');
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        flash('error', 'New password must be at least 8 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        flash('error', 'New password and confirmation do not match.');
    } else {
        $duplicate = db()->prepare('SELECT id FROM admins WHERE username = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$username, (int) $admin['id']]);

        if ($duplicate->fetch()) {
            flash('error', 'This admin username is already used.');
        } else {
            $passwordHash = $newPassword !== ''
                ? password_hash($newPassword, PASSWORD_DEFAULT)
                : (string) $admin['password_hash'];

            $stmt = db()->prepare('UPDATE admins SET username = ?, password_hash = ? WHERE id = ?');
            $stmt->execute([$username, $passwordHash, (int) $admin['id']]);
            $_SESSION['admin_id'] = (int) $admin['id'];

            flash('success', 'Admin login credentials updated.');
            redirect('security.php');
        }
    }
}
?>
<section class="page-title">
    <p class="eyebrow">Settings</p>
    <h1>Admin Security</h1>
    <p>Update the admin username and password used to access this panel.</p>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h2>Login Credentials</h2>
        <fieldset>
            <legend>Admin Account</legend>
            <label>Admin username
                <input name="username" value="<?= e($admin['username'] ?? '') ?>" required autocomplete="username">
            </label>
            <label>Current password
                <input type="password" name="current_password" required autocomplete="current-password" placeholder="Enter current password to save changes">
            </label>
        </fieldset>
        <fieldset>
            <legend>Change Password</legend>
            <label>New password
                <input type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current password">
            </label>
            <label>Confirm new password
                <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" placeholder="Repeat new password">
            </label>
            <small>Use at least 8 characters. A mix of letters, numbers, and symbols is recommended.</small>
        </fieldset>
        <button class="button primary" type="submit">Update Credentials</button>
    </form>

    <aside class="detail-aside">
        <h2>Security Notes</h2>
        <div class="material-item">
            <strong>Current password required</strong>
            <p>Changes are saved only after confirming the existing admin password.</p>
        </div>
        <div class="material-item">
            <strong>Password storage</strong>
            <p>The password is saved as a secure hash, not plain text.</p>
        </div>
        <div class="material-item">
            <strong>After updating</strong>
            <p>Use the new username and password the next time you login to the admin panel.</p>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
