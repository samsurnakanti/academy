<?php
require_once __DIR__ . '/../includes/functions.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'presign-upload') {
    require_admin();
    header('Content-Type: application/json');
    verify_csrf();

    $settings = s3_settings();
    $fileName = trim((string) ($_POST['file_name'] ?? ''));
    $contentType = trim((string) ($_POST['content_type'] ?? ''));

    if ($settings['access_key_id'] === '' || $settings['secret_access_key'] === '' || $settings['bucket_name'] === '') {
        http_response_code(422);
        echo json_encode(['error' => 'S3 settings are incomplete.']);
        exit;
    }

    if ($fileName === '' || !is_allowed_material_mime($contentType)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please select a valid video, image, PDF, Word, PowerPoint, or Excel file.']);
        exit;
    }

    $objectKey = s3_new_material_object_key($fileName);
    echo json_encode([
        'upload_url' => s3_presigned_put_url($objectKey, $contentType),
        'file_url' => s3_object_url($settings, $objectKey),
    ]);
    exit;
}

$title = 'Materials';
require __DIR__ . '/_admin_header.php';
ensure_course_structure_tables();
ensure_s3_settings_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save_material');
    $fileUrl = trim($_POST['file_url'] ?? '');
    $materialId = (int) ($_POST['material_id'] ?? 0);
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $moduleId = (int) ($_POST['module_id'] ?? 0);
    $topicId = (int) ($_POST['topic_id'] ?? 0);
    $sortOrderInput = trim((string) ($_POST['sort_order'] ?? ''));
    $sortOrder = $sortOrderInput === '' ? null : max(0, (int) $sortOrderInput);

    if ($action === 'save_module') {
        $moduleTitle = trim((string) ($_POST['module_title'] ?? ''));
        $moduleSortOrder = max(0, (int) ($_POST['module_sort_order'] ?? 0));

        if ($courseId <= 0 || $moduleTitle === '') {
            flash('error', 'Choose a program and enter a module name.');
        } else {
            $stmt = db()->prepare('INSERT INTO course_modules (course_id, title, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$courseId, $moduleTitle, $moduleSortOrder]);
            flash('success', 'Module added.');
        }

        redirect('materials.php');
    }

    if ($action === 'save_topic') {
        $topicTitle = trim((string) ($_POST['topic_title'] ?? ''));
        $topicSortOrder = max(0, (int) ($_POST['topic_sort_order'] ?? 0));
        $moduleStmt = db()->prepare('SELECT course_id FROM course_modules WHERE id = ?');
        $moduleStmt->execute([$moduleId]);
        $moduleCourseId = (int) ($moduleStmt->fetchColumn() ?: 0);

        if ($moduleCourseId <= 0 || $topicTitle === '') {
            flash('error', 'Choose a module and enter a topic name.');
        } else {
            $stmt = db()->prepare('INSERT INTO course_topics (course_id, module_id, title, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->execute([$moduleCourseId, $moduleId, $topicTitle, $topicSortOrder]);
            flash('success', 'Topic added.');
        }

        redirect('materials.php');
    }

    if ($courseId <= 0) {
        flash('error', 'Choose a program before publishing a learning item.');
        redirect('materials.php');
    }

    if ($topicId <= 0) {
        $topicId = default_topic_for_course($courseId);
    }

    try {
        if (!empty($_FILES['material_file']['name'])) {
            $fileUrl = upload_material_to_s3($_FILES['material_file']);
        }
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
        redirect('materials.php');
    }

    if ($materialId > 0) {
        $stmt = db()->prepare('UPDATE materials SET course_id = ?, topic_id = ?, title = ?, description = ?, material_type = ?, file_url = ?, sort_order = ? WHERE id = ?');
        $stmt->execute([
            $courseId,
            $topicId,
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            $_POST['material_type'] ?? 'video',
            $fileUrl,
            $sortOrder ?? 0,
            $materialId,
        ]);
        flash('success', 'Learning item updated.');
    } else {
        if ($sortOrder === null) {
            $nextOrderStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM materials WHERE course_id = ?');
            $nextOrderStmt->execute([$courseId]);
            $sortOrder = (int) $nextOrderStmt->fetchColumn();
        }

        $stmt = db()->prepare('INSERT INTO materials (course_id, topic_id, title, description, material_type, file_url, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $courseId,
            $topicId,
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            $_POST['material_type'] ?? 'video',
            $fileUrl,
            $sortOrder,
        ]);
        flash('success', 'Program learning item added.');
    }
    redirect('materials.php');
}

if (isset($_GET['delete'])) {
    $stmt = db()->prepare('DELETE FROM materials WHERE id = ?');
    $stmt->execute([(int) $_GET['delete']]);
    flash('success', 'Learning item deleted.');
    redirect('materials.php');
}

$courses = db()->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$modules = db()->query(
    "SELECT cm.*, c.title AS course_title
     FROM course_modules cm
     JOIN courses c ON c.id = cm.course_id
     ORDER BY c.title ASC, cm.sort_order ASC, cm.id ASC"
)->fetchAll();
$topics = db()->query(
    "SELECT ct.*, cm.title AS module_title, c.title AS course_title
     FROM course_topics ct
     JOIN course_modules cm ON cm.id = ct.module_id
     JOIN courses c ON c.id = ct.course_id
     ORDER BY c.title ASC, cm.sort_order ASC, cm.id ASC, ct.sort_order ASC, ct.id ASC"
)->fetchAll();
$materials = db()->query(
    "SELECT m.*, c.title AS course_title, ct.title AS topic_title, cm.title AS module_title
     FROM materials m
     JOIN courses c ON c.id = m.course_id
     LEFT JOIN course_topics ct ON ct.id = m.topic_id
     LEFT JOIN course_modules cm ON cm.id = ct.module_id
     ORDER BY c.title ASC, cm.sort_order ASC, cm.id ASC, ct.sort_order ASC, ct.id ASC, m.sort_order ASC, m.created_at ASC, m.id ASC"
)->fetchAll();

$editingMaterial = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare(
        "SELECT m.*, ct.module_id
         FROM materials m
         LEFT JOIN course_topics ct ON ct.id = m.topic_id
         WHERE m.id = ?"
    );
    $stmt->execute([(int) $_GET['edit']]);
    $editingMaterial = $stmt->fetch() ?: null;
}
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Course Videos & Resources</h1>
</section>

<section class="materials-layout">
    <div class="table-wrap materials-table">
        <table>
            <thead><tr><th>S.No</th><th>Order</th><th>Program</th><th>Module</th><th>Topic</th><th>Type</th><th>Learning Item</th><th>URL</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($materials as $index => $material): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= (int) ($material['sort_order'] ?? 0) ?></td>
                        <td><?= e($material['course_title']) ?></td>
                        <td><?= e($material['module_title'] ?: 'General Module') ?></td>
                        <td><?= e($material['topic_title'] ?: 'General Topic') ?></td>
                        <td>
                            <?php
                            $materialType = $material['material_type'] ?? 'video';
                            $materialLabel = material_display_label($material);
                            ?>
                            <span class="type-badge <?= e(str_replace('_', '-', $materialType)) ?>"><?= e($materialLabel) ?></span>
                        </td>
                        <td><?= e($material['title']) ?><br><small><?= e($material['description']) ?></small></td>
                        <td><?= $material['file_url'] ? '<a href="' . e($material['file_url']) . '" target="_blank">Open</a>' : '-' ?></td>
                        <td class="table-actions">
                            <a href="materials.php?edit=<?= (int) $material['id'] ?>">Edit</a>
                            <a href="materials.php?delete=<?= (int) $material['id'] ?>" data-confirm="Delete this material?">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <aside class="materials-sidebar">
        <form method="post" class="form-card materials-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_module">
            <h2>Add Module</h2>
            <fieldset>
                <label>Program
                    <select name="course_id" required>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>"><?= e($course['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Module name <input name="module_title" placeholder="Example: Module 1 - Excel Foundations" required></label>
                <label>Module order <input type="number" name="module_sort_order" min="0" step="1" value="10"></label>
            </fieldset>
            <button class="button primary" type="submit">Add Module</button>
        </form>

        <form method="post" class="form-card materials-form" id="topic-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_topic">
            <h2>Add Topic</h2>
            <fieldset>
                <label>Module
                    <select name="module_id" id="topic-module-select" required>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?= (int) $module['id'] ?>" data-course-id="<?= (int) $module['course_id'] ?>"><?= e($module['course_title'] . ' - ' . $module['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Topic name <input name="topic_title" placeholder="Example: Cleaning raw data" required></label>
                <label>Topic order <input type="number" name="topic_sort_order" min="0" step="1" value="10"></label>
            </fieldset>
            <button class="button primary" type="submit">Add Topic</button>
        </form>

        <form method="post" enctype="multipart/form-data" class="form-card materials-form" id="materials-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_material">
            <input type="hidden" name="material_id" value="<?= (int) ($editingMaterial['id'] ?? 0) ?>">
            <h2><?= $editingMaterial ? 'Edit Learning Item' : 'Add Program Learning Item' ?></h2>
            <fieldset>
            <?php
            $selectedMaterialType = $editingMaterial['material_type'] ?? 'video';
            $selectedMaterialLabel = $editingMaterial ? material_display_label($editingMaterial) : 'Video';
            ?>
            <legend class="legend-with-badge">
                Learning Item Details
                <span class="type-badge <?= e(str_replace('_', '-', $selectedMaterialType)) ?>" id="material-type-legend">
                    <?= e($selectedMaterialLabel) ?>
                </span>
            </legend>
            <label>Program
                <select name="course_id" id="material-course-select" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>" <?= (int) ($editingMaterial['course_id'] ?? 0) === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Module
                <select name="module_id" id="material-module-select" required>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= (int) $module['id'] ?>" data-course-id="<?= (int) $module['course_id'] ?>" <?= (int) ($editingMaterial['module_id'] ?? 0) === (int) $module['id'] ? 'selected' : '' ?>><?= e($module['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Topic
                <select name="topic_id" id="material-topic-select" required>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?= (int) $topic['id'] ?>" data-course-id="<?= (int) $topic['course_id'] ?>" data-module-id="<?= (int) $topic['module_id'] ?>" <?= (int) ($editingMaterial['topic_id'] ?? 0) === (int) $topic['id'] ? 'selected' : '' ?>><?= e($topic['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Item type
                <select name="material_type" id="material-type" required>
                    <option value="video" <?= ($editingMaterial['material_type'] ?? 'video') === 'video' ? 'selected' : '' ?>>Course video</option>
                    <option value="live_session" <?= ($editingMaterial['material_type'] ?? '') === 'live_session' ? 'selected' : '' ?>>Live session / meeting</option>
                    <option value="material" <?= ($editingMaterial['material_type'] ?? '') === 'material' ? 'selected' : '' ?>>Download / material</option>
                </select>
            </label>
            <label>Student display order <input type="number" name="sort_order" min="0" step="1" value="<?= e((string) ($editingMaterial['sort_order'] ?? '')) ?>" placeholder="Use 0 for free preview"></label>
            <small>Use order 0 to make a video or live session available before payment.</small>
            <label>Title <input name="title" value="<?= e($editingMaterial['title'] ?? '') ?>" placeholder="Example: Video 1 - BI Foundations" required></label>
            <label>Description <textarea name="description" rows="4" placeholder="Short note for trainees"><?= e($editingMaterial['description'] ?? '') ?></textarea></label>
            <label>Video, meeting, or material URL <input name="file_url" id="material-file-url" value="<?= e($editingMaterial['file_url'] ?? '') ?>" placeholder="Paste Zoom or Google Meet link, or leave blank for auto Jitsi room"></label>
            <div class="inline-actions">
                <button class="button small" type="button" id="create-zoom-meeting">Create Zoom Meeting</button>
                <button class="button small" type="button" id="create-google-meet">Create Google Meet</button>
                <button class="button small" type="button" id="generate-jitsi-room">Auto Jitsi Link</button>
            </div>
            <label>Or upload a file directly to S3
                <input type="file" name="material_file" id="material-file" accept="video/*,image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
            </label>
            <small>Upload videos, images, PDFs, Word, PowerPoint, or Excel files directly to S3.</small>
            <div class="upload-status" id="upload-status" hidden>
                <div class="upload-status-row">
                    <strong id="upload-status-text">Preparing upload...</strong>
                    <span id="upload-percent">0%</span>
                </div>
                <progress id="upload-progress" max="100" value="0"></progress>
            </div>
            </fieldset>
            <div class="materials-form-actions">
                <button class="button primary" id="materials-submit" type="submit"><?= $editingMaterial ? 'Update Learning Item' : 'Publish Learning Item' ?></button>
                <?php if ($editingMaterial): ?>
                    <a class="button small" href="materials.php">Cancel edit</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="detail-aside">
        <h2>Video Upload</h2>
        <?php $s3 = s3_settings(); ?>
        <div class="material-item">
            <strong>S3 status</strong>
            <p><?= $s3['bucket_name'] !== '' ? 'Configured for bucket ' . e($s3['bucket_name']) : 'Not configured yet' ?></p>
        </div>
        <div class="material-item">
            <strong>Need setup?</strong>
            <p><a href="s3.php">Open S3 settings</a> to add AWS credentials, bucket, and optional public/CDN URL.</p>
        </div>
        <div class="material-item">
            <strong>Live sessions</strong>
            <p>Choose Live session / meeting and paste your Zoom link for in-website Zoom classes. The Create Zoom Meeting button opens Zoom scheduling in a new tab.</p>
        </div>
        <div class="material-item">
            <strong>Large uploads</strong>
            <p>Course files now upload directly to S3 in the background while you keep filling this form. For browser uploads, your bucket must allow CORS PUT requests from this site.</p>
        </div>
        </div>
    </aside>
</section>
<script>
(() => {
    const form = document.getElementById('materials-form');
    const fileInput = document.getElementById('material-file');
    const fileUrlInput = document.getElementById('material-file-url');
    const statusBox = document.getElementById('upload-status');
    const statusText = document.getElementById('upload-status-text');
    const percentText = document.getElementById('upload-percent');
    const progress = document.getElementById('upload-progress');
    const submit = document.getElementById('materials-submit');
    let uploadInProgress = false;
    let uploadComplete = false;

    if (!form || !fileInput) return;

    const setUploadState = (text, percent = 0) => {
        statusBox.hidden = false;
        statusText.textContent = text;
        percentText.textContent = percent + '%';
        progress.value = percent;
    };

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        uploadComplete = false;
        if (!file) return;

        const allowedOfficeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        const isAllowedFile = file.type.startsWith('video/')
            || file.type.startsWith('image/')
            || allowedOfficeTypes.includes(file.type);

        if (!isAllowedFile) {
            setUploadState('Please choose a valid video, image, PDF, Word, PowerPoint, or Excel file.', 0);
            return;
        }

        uploadInProgress = true;
        submit.disabled = true;
        setUploadState('Preparing direct S3 upload...', 0);

        const body = new FormData();
        body.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
        body.append('file_name', file.name);
        body.append('content_type', file.type);

        try {
            const response = await fetch('materials.php?ajax=presign-upload', { method: 'POST', body });
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
                    const percent = Math.round((event.loaded / event.total) * 100);
                    setUploadState('Uploading directly to S3...', percent);
                });
                request.addEventListener('load', () => {
                    if (request.status >= 200 && request.status < 300) {
                        resolve();
                    } else {
                        reject(new Error('S3 upload failed with status ' + request.status + '.'));
                    }
                });
                request.addEventListener('error', () => reject(new Error('Browser could not reach S3. Most often this means S3 CORS is missing for PUT uploads from this site.')));
                request.send(file);
            });

            fileUrlInput.value = payload.file_url;
            fileInput.value = '';
            uploadComplete = true;
            setUploadState('Upload complete. Ready to publish.', 100);
        } catch (error) {
            setUploadState(error.message || 'Upload failed.', 0);
        } finally {
            uploadInProgress = false;
            submit.disabled = false;
        }
    });

    form.addEventListener('submit', (event) => {
        if (uploadInProgress) {
            event.preventDefault();
            setUploadState('Please wait for the video upload to finish.', progress.value || 0);
            return;
        }

        if (fileInput.files.length > 0 && !uploadComplete) {
            event.preventDefault();
            setUploadState('The selected file has not finished uploading yet.', progress.value || 0);
        }
    });
})();

(() => {
    const select = document.getElementById('material-type');
    const badge = document.getElementById('material-type-legend');
    const fileUrlInput = document.getElementById('material-file-url');
    const titleInput = document.querySelector('[name="title"]');
    const zoomButton = document.getElementById('create-zoom-meeting');
    const googleMeetButton = document.getElementById('create-google-meet');
    const jitsiButton = document.getElementById('generate-jitsi-room');
    const labels = {
        video: 'Video',
        live_session: 'Live Session',
        material: 'Material',
    };

    if (!select || !badge) {
        return;
    }

    const syncBadge = () => {
        const type = select.value;
        badge.textContent = labels[type] || 'Video';
        badge.classList.toggle('video', type === 'video');
        badge.classList.toggle('live-session', type === 'live_session');
        badge.classList.toggle('material', type === 'material');
    };

    select.addEventListener('change', syncBadge);
    syncBadge();

    zoomButton?.addEventListener('click', () => {
        select.value = 'live_session';
        syncBadge();
        window.open('https://zoom.us/meeting/schedule', '_blank', 'noopener');
    });

    googleMeetButton?.addEventListener('click', () => {
        select.value = 'live_session';
        syncBadge();
        window.open('https://meet.google.com/new', '_blank', 'noopener');
    });

    jitsiButton?.addEventListener('click', () => {
        const base = (titleInput?.value || 'Elldy Academy Live Class')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 50) || 'elldy-academy-live-class';
        const suffix = Math.random().toString(36).slice(2, 8);
        fileUrlInput.value = 'https://meet.jit.si/' + base + '-' + suffix;
        select.value = 'live_session';
        syncBadge();
    });
})();

(() => {
    const courseSelect = document.getElementById('material-course-select');
    const moduleSelect = document.getElementById('material-module-select');
    const topicSelect = document.getElementById('material-topic-select');

    if (!courseSelect || !moduleSelect || !topicSelect) {
        return;
    }

    const syncStructureSelects = () => {
        const courseId = courseSelect.value;
        let selectedModuleVisible = false;

        Array.from(moduleSelect.options).forEach((option) => {
            const visible = option.dataset.courseId === courseId;
            option.hidden = !visible;
            option.disabled = !visible;
            selectedModuleVisible = selectedModuleVisible || (visible && option.selected);
        });

        if (!selectedModuleVisible) {
            const firstModule = Array.from(moduleSelect.options).find((option) => !option.disabled);
            if (firstModule) {
                firstModule.selected = true;
            }
        }

        const moduleId = moduleSelect.value;
        let selectedTopicVisible = false;

        Array.from(topicSelect.options).forEach((option) => {
            const visible = option.dataset.courseId === courseId && option.dataset.moduleId === moduleId;
            option.hidden = !visible;
            option.disabled = !visible;
            selectedTopicVisible = selectedTopicVisible || (visible && option.selected);
        });

        if (!selectedTopicVisible) {
            const firstTopic = Array.from(topicSelect.options).find((option) => !option.disabled);
            if (firstTopic) {
                firstTopic.selected = true;
            }
        }
    };

    courseSelect.addEventListener('change', syncStructureSelects);
    moduleSelect.addEventListener('change', syncStructureSelects);
    syncStructureSelects();
})();
</script>
<?php require __DIR__ . '/_admin_footer.php'; ?>
