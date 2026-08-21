<?php
require_once __DIR__ . '/../includes/functions.php';

$title = 'Connect Links';
require_admin();
ensure_connect_links_table();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM connect_links WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $stmt = db()->prepare('DELETE FROM connect_links WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Connect link deleted.');
        redirect('connect.php');
    }

    $itemTitle = trim((string) ($_POST['title'] ?? ''));
    $linkUrl = trim((string) ($_POST['link_url'] ?? ''));
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
    $isActive = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;

    if ($itemTitle === '' || $linkUrl === '') {
        flash('error', 'Title and link are required.');
        redirect($id > 0 ? 'connect.php?edit=' . $id : 'connect.php');
    }

    if ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE connect_links SET title = ?, link_url = ?, image_url = ?, sort_order = ?, is_active = ? WHERE id = ?'
        );
        $stmt->execute([$itemTitle, $linkUrl, $imageUrl, $sortOrder, $isActive, $id]);
        flash('success', 'Connect link updated.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO connect_links (title, link_url, image_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$itemTitle, $linkUrl, $imageUrl, $sortOrder, $isActive]);
        flash('success', 'Connect link added.');
    }

    redirect('connect.php');
}

$links = connect_links(false);
require __DIR__ . '/_admin_header.php';
?>
<section class="page-title">
    <p class="eyebrow">Admin</p>
    <h1>Connect Links</h1>
    <p>Manage the public Connect page for support numbers, social media, community channels, and resource links.</p>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <h2><?= $edit ? 'Edit Connect Link' : 'Add Connect Link' ?></h2>

        <fieldset>
            <legend>Link Details</legend>
            <label>Title
                <input name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="Example: WhatsApp Support" required>
            </label>
            <label>Link
                <input name="link_url" value="<?= e($edit['link_url'] ?? '') ?>" placeholder="https://..., mailto:..., tel:..." required>
            </label>
            <label>Image URL
                <input name="image_url" value="<?= e($edit['image_url'] ?? '') ?>" placeholder="https://... or assets/images/...">
            </label>
            <label>Order
                <input type="number" name="sort_order" min="0" value="<?= e((string) ($edit['sort_order'] ?? 0)) ?>">
            </label>
            <label>Status
                <select name="is_active">
                    <option value="1" <?= (int) ($edit['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int) ($edit['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Hidden</option>
                </select>
            </label>
        </fieldset>

        <button class="button primary" type="submit"><?= $edit ? 'Update Link' : 'Add Link' ?></button>
        <?php if ($edit): ?>
            <a class="button secondary" href="connect.php">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>S.No</th><th>Order</th><th>Image</th><th>Title</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($links as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= (int) $item['sort_order'] ?></td>
                        <td>
                            <?php if (trim((string) ($item['image_url'] ?? '')) !== ''): ?>
                                <img class="admin-thumb" src="<?= e((string) $item['image_url']) ?>" alt="">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($item['title']) ?></strong><br>
                            <small><a href="<?= e($item['link_url']) ?>" target="_blank" rel="noopener"><?= e($item['link_url']) ?></a></small>
                        </td>
                        <td><?= (int) $item['is_active'] === 1 ? 'Active' : 'Hidden' ?></td>
                        <td>
                            <a href="connect.php?edit=<?= (int) $item['id'] ?>">Edit</a>
                            <form method="post" class="inline-action-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="text-action danger-action" data-confirm="Delete this connect link?">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$links): ?>
                    <tr><td colspan="6">No connect links yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
