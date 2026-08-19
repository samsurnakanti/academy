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

    if ($fileName === '' || !is_allowed_image_mime($contentType)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please select a valid blog image.']);
        exit;
    }

    $objectKey = s3_new_material_object_key($fileName);
    echo json_encode([
        'upload_url' => s3_presigned_put_url($objectKey, $contentType),
        'file_url' => s3_object_url($settings, $objectKey),
    ]);
    exit;
}

$title = 'Blog Manager';
require_admin();
ensure_blog_posts_table();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $stmt = db()->prepare('DELETE FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Blog post deleted.');
        redirect('blog.php');
    }

    $postTitle = trim((string) ($_POST['title'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $featuredImageUrl = trim((string) ($_POST['featured_image_url'] ?? ''));
    $authorName = trim((string) ($_POST['author_name'] ?? 'Elldy Academy'));
    $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
    $status = in_array(($_POST['status'] ?? 'published'), ['draft', 'published'], true) ? (string) $_POST['status'] : 'published';
    $publishedAtInput = trim((string) ($_POST['published_at'] ?? ''));
    $publishedAt = $publishedAtInput !== '' ? str_replace('T', ' ', $publishedAtInput) . ':00' : null;

    if ($postTitle === '' || $body === '') {
        flash('error', 'Blog title and content are required.');
        redirect($id > 0 ? 'blog.php?edit=' . $id : 'blog.php');
    }

    if (!empty($_FILES['featured_image_file']['name'])) {
        try {
            $featuredImageUrl = upload_image_to_s3($_FILES['featured_image_file']);
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect($id > 0 ? 'blog.php?edit=' . $id : 'blog.php');
        }
    }

    $slug = unique_blog_slug($slugInput !== '' ? $slugInput : $postTitle, $id);

    if ($status === 'published' && $publishedAt === null) {
        $publishedAt = date('Y-m-d H:i:s');
    }

    if ($id > 0) {
        $stmt = db()->prepare(
            "UPDATE blog_posts
             SET title = ?, slug = ?, excerpt = ?, body = ?, featured_image_url = ?, author_name = ?, meta_description = ?, status = ?, published_at = ?
             WHERE id = ?"
        );
        $stmt->execute([$postTitle, $slug, $excerpt, $body, $featuredImageUrl, $authorName, $metaDescription, $status, $publishedAt, $id]);
        flash('success', 'Blog post updated.');
    } else {
        $stmt = db()->prepare(
            "INSERT INTO blog_posts (title, slug, excerpt, body, featured_image_url, author_name, meta_description, status, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$postTitle, $slug, $excerpt, $body, $featuredImageUrl, $authorName, $metaDescription, $status, $publishedAt]);
        flash('success', 'Blog post created.');
    }

    redirect('blog.php');
}

$posts = db()->query('SELECT * FROM blog_posts ORDER BY COALESCE(published_at, created_at) DESC, id DESC')->fetchAll();
require __DIR__ . '/_admin_header.php';
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Blog Manager</h1>
    <p>Create, schedule, publish, and update daily academy content.</p>
</section>

<section class="admin-grid">
    <form method="post" enctype="multipart/form-data" class="form-card" id="blog-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <h2><?= $edit ? 'Edit Blog Post' : 'New Blog Post' ?></h2>

        <fieldset>
            <legend>Article</legend>
            <label>Title
                <input name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="Example: How BI Dashboards Help Sales Teams Every Day" required>
            </label>
            <label>Slug
                <input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="Auto-created from title if blank">
            </label>
            <label>Short excerpt
                <textarea name="excerpt" rows="3" maxlength="500" placeholder="One or two lines shown on the blog list."><?= e($edit['excerpt'] ?? '') ?></textarea>
            </label>
            <label>Content
                <textarea name="body" rows="14" placeholder="Write the full article. Use line breaks to separate paragraphs." required><?= e($edit['body'] ?? '') ?></textarea>
            </label>
        </fieldset>

        <fieldset>
            <legend>Publishing</legend>
            <label>Featured image URL
                <input name="featured_image_url" id="featured-image-url" value="<?= e($edit['featured_image_url'] ?? '') ?>" placeholder="https://...">
            </label>
            <label>Or upload featured image to S3
                <input type="file" name="featured_image_file" id="featured-image-file" accept="image/*">
            </label>
            <div class="upload-status" id="blog-upload-status" hidden>
                <div class="upload-status-row">
                    <strong id="blog-upload-status-text">Preparing upload...</strong>
                    <span id="blog-upload-percent">0%</span>
                </div>
                <progress id="blog-upload-progress" max="100" value="0"></progress>
            </div>
            <label>Author
                <input name="author_name" value="<?= e($edit['author_name'] ?? 'Elldy Academy') ?>">
            </label>
            <label>SEO description
                <input name="meta_description" value="<?= e($edit['meta_description'] ?? '') ?>" maxlength="255" placeholder="Optional short Google description">
            </label>
            <label>Status
                <select name="status">
                    <?php foreach (['published' => 'Published', 'draft' => 'Draft'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($edit['status'] ?? 'published') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Publish date and time
                <?php
                $publishedValue = '';
                if (!empty($edit['published_at'])) {
                    $publishedValue = date('Y-m-d\TH:i', strtotime((string) $edit['published_at']));
                }
                ?>
                <input type="datetime-local" name="published_at" value="<?= e($publishedValue) ?>">
            </label>
        </fieldset>

        <button class="button primary" id="blog-submit" type="submit"><?= $edit ? 'Update Post' : 'Create Post' ?></button>
        <?php if ($edit): ?>
            <a class="button secondary" href="blog.php">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>S.No</th><th>Title</th><th>Status</th><th>Publish Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($posts as $index => $post): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <strong><?= e($post['title']) ?></strong><br>
                            <small><?= e($post['slug']) ?></small>
                        </td>
                        <td><?= e(ucfirst((string) $post['status'])) ?></td>
                        <td><?= !empty($post['published_at']) ? e(date('d M Y, h:i A', strtotime((string) $post['published_at']))) : '-' ?></td>
                        <td>
                            <?php if (($post['status'] ?? '') === 'published'): ?>
                                <a href="<?= e(blog_url($post)) ?>" target="_blank" rel="noopener">View</a>
                            <?php endif; ?>
                            <a href="blog.php?edit=<?= (int) $post['id'] ?>">Edit</a>
                            <form method="post" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="text-action danger-action" data-confirm="Delete this blog post permanently?">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$posts): ?>
                    <tr><td colspan="5">No blog posts yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
(() => {
    const form = document.getElementById('blog-form');
    const fileInput = document.getElementById('featured-image-file');
    const fileUrlInput = document.getElementById('featured-image-url');
    const statusBox = document.getElementById('blog-upload-status');
    const statusText = document.getElementById('blog-upload-status-text');
    const percentText = document.getElementById('blog-upload-percent');
    const progress = document.getElementById('blog-upload-progress');
    const submit = document.getElementById('blog-submit');
    let uploadInProgress = false;
    let uploadComplete = false;

    if (!form || !fileInput || !fileUrlInput || !statusBox) {
        return;
    }

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

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        uploadComplete = false;

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            setUploadState('Please choose a valid blog image.', 0);
            return;
        }

        uploadInProgress = true;
        setBusy(true);
        setUploadState('Preparing blog image upload...', 0);

        const body = new FormData();
        body.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
        body.append('file_name', file.name);
        body.append('content_type', file.type);

        try {
            const response = await fetch('blog.php?ajax=presign-upload', { method: 'POST', body });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'Unable to prepare upload.');
            }

            await new Promise((resolve, reject) => {
                const request = new XMLHttpRequest();
                request.open('PUT', payload.upload_url);
                request.setRequestHeader('Content-Type', file.type);
                request.upload.addEventListener('progress', (event) => {
                    if (!event.lengthComputable) {
                        return;
                    }

                    setUploadState('Uploading blog image to S3...', Math.round((event.loaded / event.total) * 100));
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

            fileUrlInput.value = payload.file_url;
            fileInput.value = '';
            uploadComplete = true;
            setUploadState('Upload complete. Ready to publish.', 100);
        } catch (error) {
            setUploadState(error.message || 'Upload failed.', 0);
        } finally {
            uploadInProgress = false;
            setBusy(false);
        }
    });

    form.addEventListener('submit', (event) => {
        if (uploadInProgress) {
            event.preventDefault();
            setUploadState('Please wait for the image upload to finish.', progress.value || 0);
            return;
        }

        if (fileInput.files.length > 0 && !uploadComplete) {
            event.preventDefault();
            setUploadState('The selected image has not finished uploading yet.', progress.value || 0);
        }
    });
})();
</script>
<?php require __DIR__ . '/_admin_footer.php'; ?>
