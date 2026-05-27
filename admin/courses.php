<?php
require_once __DIR__ . '/../includes/functions.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'presign-upload') {
    require_admin();
    header('Content-Type: application/json');
    verify_csrf();

    $settings = s3_settings();
    $fileName = trim((string) ($_POST['file_name'] ?? ''));
    $contentType = trim((string) ($_POST['content_type'] ?? ''));
    $uploadKind = $_POST['upload_kind'] ?? '';
    $isAllowed = $uploadKind === 'expert_photo'
        ? is_allowed_image_mime($contentType)
        : ($uploadKind === 'promo_video' && is_allowed_video_mime($contentType));

    if ($settings['access_key_id'] === '' || $settings['secret_access_key'] === '' || $settings['bucket_name'] === '') {
        http_response_code(422);
        echo json_encode(['error' => 'S3 settings are incomplete.']);
        exit;
    }

    if ($fileName === '' || !$isAllowed) {
        http_response_code(422);
        echo json_encode(['error' => $uploadKind === 'expert_photo' ? 'Please select a valid expert image.' : 'Please select a valid promo video.']);
        exit;
    }

    $objectKey = s3_new_material_object_key($fileName);
    echo json_encode([
        'upload_url' => s3_presigned_put_url($objectKey, $contentType),
        'file_url' => s3_object_url($settings, $objectKey),
    ]);
    exit;
}

$title = 'Analytics Programs';
require __DIR__ . '/_admin_header.php';
ensure_course_detail_columns();
ensure_s3_settings_table();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM courses WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);
    $discountFee = trim((string) ($_POST['discount_fee'] ?? ''));
    $certificateDiscountFee = trim((string) ($_POST['certificate_discount_fee'] ?? ''));
    $expertPhoto = trim((string) ($_POST['expert_photo'] ?? ''));
    $promoVideoUrl = trim((string) ($_POST['promo_video_url'] ?? ''));

    if ($action === 'deactivate' && $id > 0) {
        $stmt = db()->prepare('UPDATE courses SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Program deactivated.');
        redirect('courses.php');
    }

    if ($action === 'delete' && $id > 0) {
        try {
            $stmt = db()->prepare('DELETE FROM courses WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                flash('success', 'Program deleted permanently.');
            } else {
                flash('error', 'Program was not deleted. It may already be missing.');
            }
        } catch (PDOException $e) {
            flash('error', 'Program could not be deleted. Check whether production database foreign keys allow cascading deletes.');
        }
        redirect('courses.php');
    }

    try {
        if (!empty($_FILES['expert_photo_file']['name'])) {
            $expertPhoto = upload_image_to_s3($_FILES['expert_photo_file']);
        }

        if (!empty($_FILES['promo_video_file']['name'])) {
            $promoVideoUrl = upload_video_to_s3($_FILES['promo_video_file']);
        }
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
        redirect($id > 0 ? 'courses.php?edit=' . $id : 'courses.php');
    }

    $data = [
        trim($_POST['title'] ?? ''),
        trim($_POST['short_description'] ?? ''),
        trim($_POST['description'] ?? ''),
        trim($_POST['learning_plan'] ?? ''),
        trim($_POST['completion_benefits'] ?? ''),
        trim($_POST['expert_name'] ?? ''),
        trim($_POST['expert_title'] ?? ''),
        trim($_POST['expert_bio'] ?? ''),
        $expertPhoto,
        $promoVideoUrl,
        trim($_POST['duration'] ?? ''),
        (float) ($_POST['fee'] ?? 0),
        $discountFee === '' ? null : (float) $discountFee,
        (float) ($_POST['certification_fee'] ?? 0),
        $certificateDiscountFee === '' ? null : (float) $certificateDiscountFee,
        in_array(($_POST['delivery_type'] ?? 'video'), ['video', 'live_session'], true) ? $_POST['delivery_type'] : 'video',
        trim($_POST['certificate_details'] ?? ''),
        trim($_POST['certificate_title'] ?? ''),
        trim($_POST['first_class_link'] ?? ''),
        isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($data[0] === '' || $data[10] === '') {
        flash('error', 'Program title and duration are required.');
    } elseif ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE courses SET title=?, short_description=?, description=?, learning_plan=?, completion_benefits=?, expert_name=?, expert_title=?, expert_bio=?, expert_photo=?, promo_video_url=?, duration=?, fee=?, discount_fee=?, certification_fee=?, certificate_discount_fee=?, delivery_type=?, certificate_details=?, certificate_title=?, first_class_link=?, is_active=? WHERE id=?'
        );
        $stmt->execute([...$data, $id]);
        flash('success', 'Program updated.');
        redirect('courses.php');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO courses (title, short_description, description, learning_plan, completion_benefits, expert_name, expert_title, expert_bio, expert_photo, promo_video_url, duration, fee, discount_fee, certification_fee, certificate_discount_fee, delivery_type, certificate_details, certificate_title, first_class_link, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($data);
        flash('success', 'Program added.');
        redirect('courses.php');
    }
}

$courses = db()->query('SELECT * FROM courses ORDER BY created_at DESC')->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Analytics Programs</h1>
</section>

<section class="admin-grid">
    <form method="post" enctype="multipart/form-data" class="form-card" id="program-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <h2><?= $edit ? 'Edit Program' : 'Add Program' ?></h2>
        <fieldset>
            <legend>Program Identity</legend>
            <label>Program title <input name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="Example: Data Analytics & BI Foundations" required></label>
            <label>Short description <input name="short_description" value="<?= e($edit['short_description'] ?? '') ?>" placeholder="One-line analytics program promise"></label>
            <label>Full description <textarea name="description" rows="5" placeholder="Describe the analytics cases, BI tools, audience, and training approach"><?= e($edit['description'] ?? '') ?></textarea></label>
        </fieldset>

        <fieldset>
            <legend>Learning Outcomes</legend>
            <label>What trainees will study during this program
                <textarea name="learning_plan" rows="6" placeholder="Example: KPI framing&#10;SQL reporting&#10;Power BI dashboard design"><?= e($edit['learning_plan'] ?? '') ?></textarea>
            </label>
            <label>What trainees get after program completion
                <textarea name="completion_benefits" rows="6" placeholder="Example: BI dashboard portfolio&#10;Program completion certificate&#10;Business case presentation confidence"><?= e($edit['completion_benefits'] ?? '') ?></textarea>
            </label>
        </fieldset>

        <fieldset>
            <legend>Elldy Expert Details</legend>
            <label>Expert name <input name="expert_name" value="<?= e($edit['expert_name'] ?? '') ?>" placeholder="Example: Priya Nair"></label>
            <label>Expert role <input name="expert_title" value="<?= e($edit['expert_title'] ?? '') ?>" placeholder="Example: Senior BI Consultant"></label>
            <label>Expert bio <textarea name="expert_bio" rows="4" placeholder="Short faculty/expert profile and business intelligence experience"><?= e($edit['expert_bio'] ?? '') ?></textarea></label>
            <label>Expert photo URL <input name="expert_photo" id="expert-photo-url" value="<?= e($edit['expert_photo'] ?? '') ?>" placeholder="https://..."></label>
            <label>Or upload expert photo to S3
                <input type="file" name="expert_photo_file" id="expert-photo-file" accept="image/*">
            </label>
        </fieldset>

        <?php $selectedDeliveryType = $edit['delivery_type'] ?? 'video'; ?>
        <fieldset>
            <legend class="legend-with-badge">
                Schedule & Access
                <span class="type-badge <?= $selectedDeliveryType === 'live_session' ? 'live-session' : 'video' ?>" id="course-type-legend">
                    <?= $selectedDeliveryType === 'live_session' ? 'Live Sessions' : 'Videos' ?>
                </span>
            </legend>
            <label>Duration <input name="duration" value="<?= e($edit['duration'] ?? '') ?>" placeholder="6 weeks" required></label>
            <label>Program video/course fee <input type="number" step="0.01" name="fee" value="<?= e((string) ($edit['fee'] ?? '0')) ?>"></label>
            <label>Discounted course fee <input type="number" step="0.01" name="discount_fee" value="<?= e($edit && $edit['discount_fee'] !== null ? (string) $edit['discount_fee'] : '') ?>" placeholder="Blank = no discount, 0 = free"></label>
            <label>Certification charge <input type="number" step="0.01" name="certification_fee" value="<?= e((string) ($edit['certification_fee'] ?? '0')) ?>"></label>
            <label>Discounted certification charge <input type="number" step="0.01" name="certificate_discount_fee" value="<?= e($edit && $edit['certificate_discount_fee'] !== null ? (string) $edit['certificate_discount_fee'] : '') ?>" placeholder="Blank = no discount, 0 = free"></label>
            <label>Program type
                <select name="delivery_type" id="course-delivery-type" required>
                    <option value="video" <?= ($edit['delivery_type'] ?? 'video') === 'video' ? 'selected' : '' ?>>Video course</option>
                    <option value="live_session" <?= ($edit['delivery_type'] ?? '') === 'live_session' ? 'selected' : '' ?>>Live session course</option>
                </select>
            </label>
            <label>Promo video URL
                <input name="promo_video_url" id="promo-video-url" value="<?= e($edit['promo_video_url'] ?? '') ?>" placeholder="Paste YouTube/Vimeo/S3 video URL">
            </label>
            <label>Or upload promo video to S3
                <input type="file" name="promo_video_file" id="promo-video-file" accept="video/*">
            </label>
            <small>Promo video appears on the public program page before enrollment.</small>
            <div class="upload-status" id="program-upload-status" hidden>
                <div class="upload-status-row">
                    <strong id="program-upload-status-text">Preparing upload...</strong>
                    <span id="program-upload-percent">0%</span>
                </div>
                <progress id="program-upload-progress" max="100" value="0"></progress>
            </div>
            <label>Certificate skill details
                <textarea name="certificate_details" rows="4" placeholder="Example: Data preparation&#10;Dashboard building&#10;Forecasting&#10;Business intelligence"><?= e($edit['certificate_details'] ?? '') ?></textarea>
            </label>
            <label>Certificate title
                <input name="certificate_title" value="<?= e($edit['certificate_title'] ?? '') ?>" placeholder="Example: Elldy Data Intelligence Platform">
            </label>
            <label class="check"><input type="checkbox" name="is_active" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Active</label>
        </fieldset>
        <button class="button primary" type="submit"><?= $edit ? 'Update Program' : 'Add Program' ?></button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>S.No</th><th>Program</th><th>Type</th><th>Duration</th><th>Fee</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($courses as $index => $course): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($course['title']) ?></td>
                        <td>
                            <?php $isLiveCourse = ($course['delivery_type'] ?? 'video') === 'live_session'; ?>
                            <span class="type-badge <?= $isLiveCourse ? 'live-session' : 'video' ?>">
                                <?= $isLiveCourse ? 'Live Sessions' : 'Videos' ?>
                            </span>
                        </td>
                        <td><?= e($course['duration']) ?></td>
                        <td><?= price_html($course, 'fee', 'discount_fee') ?></td>
                        <td><?= $course['is_active'] ? 'Active' : 'Inactive' ?></td>
                        <td>
                            <a href="courses.php?edit=<?= (int) $course['id'] ?>">Edit</a>
                            <form method="post" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="text-action" data-confirm="Deactivate this program?">Deactivate</button>
                            </form>
                            <form method="post" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="text-action danger-action" data-confirm="Delete this program permanently? Related enrollments, materials, and certificate requests will also be removed.">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
(() => {
    const form = document.getElementById('program-form');
    const submit = form?.querySelector('button[type="submit"]');
    const statusBox = document.getElementById('program-upload-status');
    const statusText = document.getElementById('program-upload-status-text');
    const percentText = document.getElementById('program-upload-percent');
    const progress = document.getElementById('program-upload-progress');
    const uploads = [
        {
            input: document.getElementById('expert-photo-file'),
            target: document.getElementById('expert-photo-url'),
            kind: 'expert_photo',
            allowed: (file) => file.type.startsWith('image/'),
            invalid: 'Please choose a valid expert image.',
            label: 'expert photo',
        },
        {
            input: document.getElementById('promo-video-file'),
            target: document.getElementById('promo-video-url'),
            kind: 'promo_video',
            allowed: (file) => file.type.startsWith('video/'),
            invalid: 'Please choose a valid promo video.',
            label: 'promo video',
        },
    ];
    let activeUploads = 0;

    if (!form || !statusBox) return;

    const setUploadState = (text, percent = 0) => {
        statusBox.hidden = false;
        statusText.textContent = text;
        percentText.textContent = percent + '%';
        progress.value = percent;
    };

    const setBusy = (busy) => {
        if (submit) {
            submit.disabled = busy;
        }
    };

    const uploadDirectly = async (config, file) => {
        activeUploads++;
        setBusy(true);
        setUploadState('Preparing ' + config.label + ' upload...', 0);

        const body = new FormData();
        body.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
        body.append('file_name', file.name);
        body.append('content_type', file.type);
        body.append('upload_kind', config.kind);

        try {
            const response = await fetch('courses.php?ajax=presign-upload', { method: 'POST', body });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.error || 'Unable to prepare upload.');
            }

            await new Promise((resolve, reject) => {
                const request = new XMLHttpRequest();
                request.open('PUT', payload.upload_url);
                request.setRequestHeader('Content-Type', file.type);
                request.upload.addEventListener('progress', (event) => {
                    if (!event.lengthComputable) return;
                    setUploadState('Uploading ' + config.label + ' to S3...', Math.round((event.loaded / event.total) * 100));
                });
                request.addEventListener('load', () => {
                    if (request.status >= 200 && request.status < 300) {
                        resolve();
                    } else {
                        reject(new Error('S3 upload failed with status ' + request.status + '.'));
                    }
                });
                request.addEventListener('error', () => reject(new Error('Browser could not reach S3. Check your S3 CORS PUT settings.')));
                request.send(file);
            });

            config.target.value = payload.file_url;
            config.input.value = '';
            setUploadState('Upload complete. Ready to save program.', 100);
        } catch (error) {
            setUploadState(error.message || 'Upload failed.', 0);
        } finally {
            activeUploads--;
            setBusy(activeUploads > 0);
        }
    };

    uploads.forEach((config) => {
        config.input?.addEventListener('change', () => {
            const file = config.input.files[0];
            if (!file) return;

            if (!config.allowed(file)) {
                setUploadState(config.invalid, 0);
                return;
            }

            uploadDirectly(config, file);
        });
    });

    form.addEventListener('submit', (event) => {
        if (activeUploads > 0) {
            event.preventDefault();
            setUploadState('Please wait for the S3 upload to finish.', progress.value || 0);
        }
    });
})();

(() => {
    const select = document.getElementById('course-delivery-type');
    const badge = document.getElementById('course-type-legend');

    if (!select || !badge) {
        return;
    }

    const syncBadge = () => {
        const isLive = select.value === 'live_session';
        badge.textContent = isLive ? 'Live Sessions' : 'Videos';
        badge.classList.toggle('live-session', isLive);
        badge.classList.toggle('video', !isLive);
    };

    select.addEventListener('change', syncBadge);
    syncBadge();
})();
</script>
<?php require __DIR__ . '/_admin_footer.php'; ?>
