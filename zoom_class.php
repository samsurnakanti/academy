<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_user();
ensure_course_detail_columns();
ensure_material_columns();
ensure_zoom_settings_table();

$enrollmentId = (int) ($_GET['enrollment_id'] ?? 0);
$materialId = (int) ($_GET['material_id'] ?? 0);

$stmt = db()->prepare(
    "SELECT e.*, c.title AS course_title, c.fee, c.discount_fee
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     WHERE e.id = ? AND e.user_id = ? AND e.status != 'cancelled'"
);
$stmt->execute([$enrollmentId, $user['id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    http_response_code(404);
    exit('Class access not found.');
}

$materialStmt = db()->prepare(
    "SELECT *
     FROM materials
     WHERE id = ? AND course_id = ? AND material_type = 'live_session'"
);
$materialStmt->execute([$materialId, (int) $enrollment['course_id']]);
$material = $materialStmt->fetch();

if (!$material || !live_session_is_zoom_url((string) $material['file_url'])) {
    http_response_code(404);
    exit('Zoom class not found.');
}

$details = zoom_meeting_details_from_url((string) $material['file_url']);
$settings = zoom_settings();
$signature = $details['meeting_number'] !== '' ? zoom_sdk_signature($details['meeting_number'], 0) : '';

if ($signature !== '') {
    record_live_session_attendance($enrollment, $material);
}

$title = 'Zoom Class | ' . $material['title'] . ' | Elldy Academy';
$zoomReady = $settings['client_id'] !== '' && $signature !== '' && $details['meeting_number'] !== '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(public_url('assets/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
    <?php if ($zoomReady): ?>
        <link type="text/css" rel="stylesheet" href="https://source.zoom.us/<?= e($settings['sdk_version']) ?>/css/bootstrap.css">
        <link type="text/css" rel="stylesheet" href="https://source.zoom.us/<?= e($settings['sdk_version']) ?>/css/react-select.css">
    <?php endif; ?>
</head>
<body class="zoom-class-page">
<?php if (!$zoomReady): ?>
    <main class="zoom-class-fallback">
        <div class="empty-state">
            <p class="eyebrow">Zoom class</p>
            <h1>Zoom setup is incomplete</h1>
            <p>Add Zoom Meeting SDK credentials in admin settings, and make sure this live session has a valid Zoom meeting link.</p>
            <a class="button small" href="<?= e(public_url('learn.php?enrollment_id=' . (int) $enrollment['id'] . '&material_id=' . (int) $material['id'])) ?>">Back to Learning</a>
        </div>
    </main>
<?php else: ?>
    <div class="zoom-class-topbar">
        <strong><?= e($material['title']) ?></strong>
        <a href="<?= e(public_url('learn.php?enrollment_id=' . (int) $enrollment['id'] . '&material_id=' . (int) $material['id'])) ?>">Back</a>
    </div>
    <script src="https://source.zoom.us/<?= e($settings['sdk_version']) ?>/lib/vendor/react.min.js"></script>
    <script src="https://source.zoom.us/<?= e($settings['sdk_version']) ?>/lib/vendor/react-dom.min.js"></script>
    <script src="https://source.zoom.us/<?= e($settings['sdk_version']) ?>/lib/vendor/redux.min.js"></script>
    <script src="https://source.zoom.us/<?= e($settings['sdk_version']) ?>/lib/vendor/redux-thunk.min.js"></script>
    <script src="https://source.zoom.us/<?= e($settings['sdk_version']) ?>/lib/vendor/lodash.min.js"></script>
    <script src="https://source.zoom.us/zoom-meeting-<?= e($settings['sdk_version']) ?>.min.js"></script>
    <script>
        ZoomMtg.setZoomJSLib('https://source.zoom.us/<?= e($settings['sdk_version']) ?>/lib', '/av');
        ZoomMtg.preLoadWasm();
        ZoomMtg.prepareWebSDK();
        ZoomMtg.i18n.load('en-US');
        ZoomMtg.i18n.reload('en-US');

        ZoomMtg.init({
            leaveUrl: <?= json_encode(site_url('learn.php?enrollment_id=' . (int) $enrollment['id'] . '&material_id=' . (int) $material['id'])) ?>,
            patchJsMedia: true,
            success: () => {
                ZoomMtg.join({
                    signature: <?= json_encode($signature) ?>,
                    sdkKey: <?= json_encode($settings['client_id']) ?>,
                    meetingNumber: <?= json_encode($details['meeting_number']) ?>,
                    passWord: <?= json_encode($details['password']) ?>,
                    userName: <?= json_encode($user['name'] ?: 'Student') ?>,
                    userEmail: <?= json_encode($user['email'] ?: '') ?>,
                    success: () => {},
                    error: (error) => {
                        console.error(error);
                        alert('Unable to join Zoom class. Please check the meeting link and Zoom SDK settings.');
                    }
                });
            },
            error: (error) => {
                console.error(error);
                alert('Unable to start Zoom class inside the website.');
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
