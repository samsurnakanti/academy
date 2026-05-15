<?php
$title = 'WhatsApp Settings';
require __DIR__ . '/_admin_header.php';
ensure_whatsapp_settings_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    save_whatsapp_settings($_POST);
    flash('success', 'WhatsApp API settings saved.');
    redirect('whatsapp.php');
}

$settings = whatsapp_settings();
$maskedToken = $settings['access_token'] !== '' ? str_repeat('•', 18) . substr($settings['access_token'], -6) : 'Not configured';
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>WhatsApp Cloud API</h1>
    <p>Save the WhatsApp credentials used for trainee OTP login, enrollment confirmations, and class reminders.</p>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h2>API Configuration</h2>
        <fieldset>
            <legend>Meta WhatsApp Details</legend>
            <label>WhatsApp Business Account ID
                <input name="business_account_id" value="<?= e($settings['business_account_id']) ?>" required>
            </label>
            <label>Phone Number ID
                <input name="phone_number_id" value="<?= e($settings['phone_number_id']) ?>" required>
            </label>
            <label>Graph API Version
                <input name="graph_version" value="<?= e($settings['graph_version']) ?>" placeholder="v20.0" required>
            </label>
        </fieldset>

        <fieldset>
            <legend>OTP Message</legend>
            <label>Access Token
                <textarea name="access_token" rows="5" placeholder="Paste WhatsApp Cloud API access token" required><?= e($settings['access_token']) ?></textarea>
            </label>
            <label>Approved Template Name
                <input name="template_name" value="<?= e($settings['template_name']) ?>" placeholder="elldy_academy_otp">
            </label>
            <label>Enrollment Welcome Template
                <input name="enrollment_template_name" value="<?= e($settings['enrollment_template_name']) ?>" placeholder="elldy_academy_enrollment">
            </label>
            <label>Daily Class Reminder Template
                <input name="reminder_template_name" value="<?= e($settings['reminder_template_name']) ?>" placeholder="elldy_academy_class_reminder">
            </label>
            <label>Template Language Code
                <input name="template_language" value="<?= e($settings['template_language']) ?>" placeholder="en">
            </label>
        </fieldset>
        <button class="button primary" type="submit">Save WhatsApp Settings</button>
    </form>

    <aside class="detail-aside">
        <h2>Current Status</h2>
        <div class="material-item">
            <strong>Token</strong>
            <p><?= e($maskedToken) ?></p>
        </div>
        <div class="material-item">
            <strong>Template</strong>
            <p><?= e($settings['template_name'] ?: 'Plain text fallback / not recommended for production') ?> / <?= e($settings['template_language']) ?></p>
        </div>
        <div class="material-item">
            <strong>Enrollment / Reminder Templates</strong>
            <p><?= e($settings['enrollment_template_name'] ?: 'Not configured') ?> / <?= e($settings['reminder_template_name'] ?: 'Not configured') ?></p>
        </div>
        <div class="material-item">
            <strong>Important</strong>
            <p>For production, use approved Meta WhatsApp templates. Enrollment and reminder templates should accept 3 body values in this order: trainee name, program title, login URL.</p>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
