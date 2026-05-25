<?php
require_once __DIR__ . '/../includes/functions.php';

$title = 'Zoom';
require __DIR__ . '/_admin_header.php';
ensure_zoom_settings_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    save_zoom_settings($_POST);
    flash('success', 'Zoom Meeting SDK settings saved.');
    redirect('zoom.php');
}

$settings = zoom_settings();
?>
<section class="page-title">
    <p class="eyebrow">Settings</p>
    <h1>Zoom Meeting SDK</h1>
    <p>Add your Zoom Meeting SDK credentials so students can join Zoom classes inside this website.</p>
</section>

<section class="settings-layout">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <fieldset>
            <legend>SDK Credentials</legend>
            <label>Client ID
                <input name="client_id" value="<?= e($settings['client_id']) ?>" placeholder="Zoom Meeting SDK Client ID">
            </label>
            <label>Client Secret
                <input name="client_secret" value="<?= e($settings['client_secret']) ?>" placeholder="Zoom Meeting SDK Client Secret">
            </label>
            <label>Web SDK version
                <input name="sdk_version" value="<?= e($settings['sdk_version']) ?>" placeholder="5.1.4">
            </label>
        </fieldset>
        <button class="button primary" type="submit">Save Zoom Settings</button>
    </form>

    <aside class="detail-aside">
        <h2>How to Use</h2>
        <div class="material-item">
            <strong>1. Create SDK app</strong>
            <p>In Zoom App Marketplace, create a Meeting SDK app and copy the Client ID and Client Secret here.</p>
        </div>
        <div class="material-item">
            <strong>2. Add class link</strong>
            <p>In Materials, choose Live session / meeting and paste a Zoom meeting invite link.</p>
        </div>
        <div class="material-item">
            <strong>3. Student joins inside site</strong>
            <p>Students open the learning workspace and click Join Zoom Class.</p>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
