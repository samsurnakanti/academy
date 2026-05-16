<?php
$title = 'S3 Settings';
require __DIR__ . '/_admin_header.php';
ensure_s3_settings_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    save_s3_settings($_POST);
    flash('success', 'S3 settings saved.');
    redirect('s3.php');
}

$settings = s3_settings();
$maskedSecret = $settings['secret_access_key'] !== '' ? str_repeat('•', 18) . substr($settings['secret_access_key'], -6) : 'Not configured';
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>AWS S3</h1>
    <p>Configure direct course-video uploads from the admin panel to your S3 bucket.</p>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h2>Storage Configuration</h2>
        <fieldset>
            <legend>AWS Credentials</legend>
            <label>Access Key ID
                <input name="access_key_id" value="<?= e($settings['access_key_id']) ?>" required>
            </label>
            <label>Secret Access Key
                <textarea name="secret_access_key" rows="4" required><?= e($settings['secret_access_key']) ?></textarea>
            </label>
            <label>Region
                <input name="region" value="<?= e($settings['region']) ?>" placeholder="ap-south-1" required>
            </label>
            <label>Bucket name
                <input name="bucket_name" value="<?= e($settings['bucket_name']) ?>" required>
            </label>
            <label>Upload folder / prefix
                <input name="upload_prefix" value="<?= e($settings['upload_prefix']) ?>" placeholder="course-videos">
            </label>
            <label>Public base URL or CDN URL
                <input name="public_base_url" value="<?= e($settings['public_base_url']) ?>" placeholder="https://cdn.example.com">
            </label>
        </fieldset>
        <button class="button primary" type="submit">Save S3 Settings</button>
    </form>

    <aside class="detail-aside">
        <h2>Status</h2>
        <div class="material-item"><strong>Bucket</strong><p><?= e($settings['bucket_name'] ?: 'Not configured') ?></p></div>
        <div class="material-item"><strong>Region</strong><p><?= e($settings['region']) ?></p></div>
        <div class="material-item"><strong>Upload prefix</strong><p><?= e($settings['upload_prefix']) ?></p></div>
        <div class="material-item"><strong>Secret</strong><p><?= e($maskedSecret) ?></p></div>
        <div class="material-item">
            <strong>Playback note</strong>
            <p>Uploaded videos must be reachable by students. Use a public bucket/object policy or set a public CDN base URL such as CloudFront.</p>
        </div>
        <div class="material-item">
            <strong>Large upload note</strong>
            <p>Direct browser uploads need S3 CORS to allow <code>PUT</code> from your website origin and expose the response to the browser.</p>
        </div>
    </aside>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
