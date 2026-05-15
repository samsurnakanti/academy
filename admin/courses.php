<?php
$title = 'Analytics Programs';
require __DIR__ . '/_admin_header.php';
ensure_course_detail_columns();

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

    $data = [
        trim($_POST['title'] ?? ''),
        trim($_POST['short_description'] ?? ''),
        trim($_POST['description'] ?? ''),
        trim($_POST['learning_plan'] ?? ''),
        trim($_POST['completion_benefits'] ?? ''),
        trim($_POST['expert_name'] ?? ''),
        trim($_POST['expert_title'] ?? ''),
        trim($_POST['expert_bio'] ?? ''),
        trim($_POST['expert_photo'] ?? ''),
        trim($_POST['duration'] ?? ''),
        (float) ($_POST['fee'] ?? 0),
        (float) ($_POST['certification_fee'] ?? 0),
        trim($_POST['first_class_link'] ?? ''),
        isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($data[0] === '' || $data[9] === '') {
        flash('error', 'Program title and duration are required.');
    } elseif ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE courses SET title=?, short_description=?, description=?, learning_plan=?, completion_benefits=?, expert_name=?, expert_title=?, expert_bio=?, expert_photo=?, duration=?, fee=?, certification_fee=?, first_class_link=?, is_active=? WHERE id=?'
        );
        $stmt->execute([...$data, $id]);
        flash('success', 'Program updated.');
        redirect('courses.php');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO courses (title, short_description, description, learning_plan, completion_benefits, expert_name, expert_title, expert_bio, expert_photo, duration, fee, certification_fee, first_class_link, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
    <form method="post" class="form-card">
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
            <label>Expert photo URL <input name="expert_photo" value="<?= e($edit['expert_photo'] ?? '') ?>" placeholder="https://..."></label>
        </fieldset>

        <fieldset>
            <legend>Schedule & Access</legend>
            <label>Duration <input name="duration" value="<?= e($edit['duration'] ?? '') ?>" placeholder="6 weeks" required></label>
            <label>Program video/course fee <input type="number" step="0.01" name="fee" value="<?= e((string) ($edit['fee'] ?? '0')) ?>"></label>
            <label>Certification charge <input type="number" step="0.01" name="certification_fee" value="<?= e((string) ($edit['certification_fee'] ?? '0')) ?>"></label>
            <label>Live session / meeting link <input name="first_class_link" value="<?= e($edit['first_class_link'] ?? '') ?>" placeholder="https://meet.google.com/..."></label>
            <label class="check"><input type="checkbox" name="is_active" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Active</label>
        </fieldset>
        <button class="button primary" type="submit"><?= $edit ? 'Update Program' : 'Add Program' ?></button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Program</th><th>Duration</th><th>Fee</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?= e($course['title']) ?></td>
                        <td><?= e($course['duration']) ?></td>
                        <td><?= money($course['fee']) ?></td>
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
<?php require __DIR__ . '/_admin_footer.php'; ?>
