<?php
$title = 'Materials';
require __DIR__ . '/_admin_header.php';
ensure_material_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO materials (course_id, title, description, material_type, file_url) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        (int) $_POST['course_id'],
        trim($_POST['title'] ?? ''),
        trim($_POST['description'] ?? ''),
        $_POST['material_type'] ?? 'video',
        trim($_POST['file_url'] ?? ''),
    ]);
    flash('success', 'Program learning item added.');
    redirect('materials.php');
}

if (isset($_GET['delete'])) {
    $stmt = db()->prepare('DELETE FROM materials WHERE id = ?');
    $stmt->execute([(int) $_GET['delete']]);
    flash('success', 'Learning item deleted.');
    redirect('materials.php');
}

$courses = db()->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$materials = db()->query(
    "SELECT m.*, c.title AS course_title
     FROM materials m
     JOIN courses c ON c.id = m.course_id
     ORDER BY m.created_at DESC"
)->fetchAll();
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Course Videos & Resources</h1>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h2>Add Program Learning Item</h2>
        <fieldset>
            <legend>Material Details</legend>
            <label>Program
                <select name="course_id" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>"><?= e($course['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Item type
                <select name="material_type" required>
                    <option value="video">Course video</option>
                    <option value="live_session">Live session / meeting</option>
                    <option value="material">Download / material</option>
                </select>
            </label>
            <label>Title <input name="title" placeholder="Example: Video 1 - BI Foundations" required></label>
            <label>Description <textarea name="description" rows="4" placeholder="Short note for trainees"></textarea></label>
            <label>Video, meeting, or material URL <input name="file_url" placeholder="YouTube, Vimeo, Google Drive, Meet, PDF, or other URL"></label>
        </fieldset>
        <button class="button primary" type="submit">Publish Learning Item</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Program</th><th>Type</th><th>Learning Item</th><th>URL</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($materials as $material): ?>
                    <tr>
                        <td><?= e($material['course_title']) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $material['material_type'] ?? 'video'))) ?></td>
                        <td><?= e($material['title']) ?><br><small><?= e($material['description']) ?></small></td>
                        <td><?= $material['file_url'] ? '<a href="' . e($material['file_url']) . '" target="_blank">Open</a>' : '-' ?></td>
                        <td><a href="materials.php?delete=<?= (int) $material['id'] ?>" data-confirm="Delete this material?">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
