<?php
$title = 'Razorpay Settings';
require __DIR__ . '/_admin_header.php';
ensure_razorpay_settings_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    save_razorpay_settings($_POST);
    flash('success', 'Razorpay settings saved.');
    redirect('razorpay.php');
}

$settings = razorpay_settings();
$maskedSecret = $settings['key_secret'] !== '' ? str_repeat('•', 18) . substr($settings['key_secret'], -6) : 'Not configured';
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Razorpay</h1>
    <p>Configure direct Academy payment collection.</p>
</section>
<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h2>Payment Configuration</h2>
        <fieldset>
            <legend>API Credentials</legend>
            <label>Key ID <input name="key_id" value="<?= e($settings['key_id']) ?>" required></label>
            <label>Key Secret <textarea name="key_secret" rows="4" required><?= e($settings['key_secret']) ?></textarea></label>
            <label>Currency <input name="currency" value="<?= e($settings['currency']) ?>" required></label>
        </fieldset>
        <button class="button primary" type="submit">Save Razorpay Settings</button>
    </form>
    <aside class="detail-aside">
        <h2>Status</h2>
        <div class="material-item"><strong>Key ID</strong><p><?= e($settings['key_id'] ?: 'Not configured') ?></p></div>
        <div class="material-item"><strong>Secret</strong><p><?= e($maskedSecret) ?></p></div>
        <div class="material-item"><strong>Currency</strong><p><?= e($settings['currency']) ?></p></div>
    </aside>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
