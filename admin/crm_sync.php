<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$title = 'CRM Sync';
ensure_crm_settings_table();

$lastResponse = null;
$groupsResponse = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_settings') {
            save_crm_settings($_POST);
            flash('success', 'CRM API connection saved.');
            redirect('crm_sync.php');
        }

        if ($action === 'load_groups') {
            $groupsResponse = crm_groups();
            $lastResponse = $groupsResponse;
            flash('success', 'CRM groups loaded.');
        }

        if ($action === 'create_parent_group') {
            $lastResponse = crm_create_group(trim((string) ($_POST['group_name'] ?? '')));
            $parentGroupId = crm_group_id_from_response($lastResponse);

            if ($parentGroupId !== '') {
                $settings = crm_settings();
                $settings['default_parent_group_id'] = $parentGroupId;
                save_crm_settings($settings);
            }

            flash('success', $parentGroupId !== '' ? 'Parent group created and saved as default.' : 'Parent group request sent.');
        }

        if ($action === 'sync_program') {
            $lastResponse = crm_sync_program_contacts(
                (int) ($_POST['course_id'] ?? 0),
                trim((string) ($_POST['parent_group_id'] ?? '')),
                trim((string) ($_POST['subgroup_name'] ?? ''))
            );
            flash('success', 'Programme contacts synced to CRM.');
        }
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
    }
}

$settings = crm_settings();
$courses = db()->query('SELECT id, title FROM courses ORDER BY title ASC')->fetchAll();
$maskedKey = $settings['api_key'] !== '' ? str_repeat('*', 18) . substr($settings['api_key'], -6) : 'Not configured';

if ($settings['api_key'] !== '' && $groupsResponse === null) {
    try {
        $groupsResponse = crm_groups();
    } catch (RuntimeException $exception) {
        $groupsResponse = null;
    }
}

$parentGroups = $groupsResponse ? crm_parent_groups($groupsResponse) : [];

require __DIR__ . '/_admin_header.php';
?>
<section class="page-title">
    <p class="eyebrow">Integration</p>
    <h1>CRM Contact Sync</h1>
    <p>Create CRM groups and push academy programme enrollments into a fresh subgroup for every campaign or batch.</p>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <h2>Step 1: Connection</h2>
        <fieldset>
            <legend>CRM API</legend>
            <label>Base URL
                <input name="base_url" value="<?= e($settings['base_url']) ?>" placeholder="https://elldy.com" required>
            </label>
            <label>Business API key
                <textarea name="api_key" rows="4" placeholder="Paste CRM business API key" required><?= e($settings['api_key']) ?></textarea>
            </label>
            <label>Business ID
                <input name="default_business_id" value="<?= e($settings['default_business_id']) ?>" placeholder="Optional when managing multiple workspaces">
            </label>
            <label>Default parent group ID
                <input name="default_parent_group_id" value="<?= e($settings['default_parent_group_id']) ?>" placeholder="Optional">
            </label>
        </fieldset>
        <button class="button primary" type="submit">Save CRM Connection</button>
    </form>

    <aside class="detail-aside">
        <h2>Status</h2>
        <div class="material-item"><strong>Base URL</strong><p><?= e($settings['base_url']) ?></p></div>
        <div class="material-item"><strong>API key</strong><p><?= e($maskedKey) ?></p></div>
        <div class="material-item"><strong>Business ID</strong><p><?= e($settings['default_business_id'] ?: 'Not set') ?></p></div>
        <div class="material-item"><strong>Default parent group</strong><p><?= e($settings['default_parent_group_id'] ?: 'Not selected') ?></p></div>
    </aside>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_parent_group">
        <h2>Step 2: Create Parent Group</h2>
        <p>Parent groups are containers only. Contacts will be imported into a subgroup under this parent group.</p>
        <label>Parent group name
            <input name="group_name" placeholder="Example: Academy Leads" required>
        </label>
        <button class="button primary" type="submit">Create Parent Group</button>
    </form>

    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="load_groups">
        <h2>Group Lookup</h2>
        <p>Load existing CRM parent groups before syncing contacts.</p>
        <button class="button primary" type="submit">Refresh CRM Groups</button>
    </form>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="sync_program">
        <h2>Step 3: Programme Sync</h2>
        <label>Programme
            <select name="course_id" required>
                <option value="">Choose programme</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"><?= e($course['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Parent group
            <select name="parent_group_id" required>
                <?php if (!$parentGroups && $settings['default_parent_group_id'] === ''): ?>
                    <option value="">Save API key and load groups first</option>
                <?php endif; ?>
                <?php if ($settings['default_parent_group_id'] !== ''): ?>
                    <option value="<?= e($settings['default_parent_group_id']) ?>">Default parent group #<?= e($settings['default_parent_group_id']) ?></option>
                <?php endif; ?>
                <?php foreach ($parentGroups as $group): ?>
                    <?php
                    $groupId = (string) ($group['id'] ?? $group['group_id'] ?? '');
                    $groupName = (string) ($group['group_name'] ?? $group['name'] ?? ('Group #' . $groupId));
                    ?>
                    <?php if ($groupId !== ''): ?>
                        <option value="<?= e($groupId) ?>" <?= $groupId === $settings['default_parent_group_id'] ? 'selected' : '' ?>>
                            <?= e($groupName) ?> (#<?= e($groupId) ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label>New subgroup name
            <input name="subgroup_name" placeholder="Example: July Power BI Batch Leads" required>
        </label>
        <button class="button primary" type="submit" data-confirm="Create this subgroup and push programme contacts to CRM?">Create Subgroup & Sync Contacts</button>
    </form>

    <aside class="detail-aside">
        <h2>Sync Rules</h2>
        <div class="material-item">
            <strong>Every sync creates a subgroup</strong>
            <p>The selected parent group stays as the container. Contacts are pushed only into the new subgroup.</p>
        </div>
        <div class="material-item">
            <strong>Contacts included</strong>
            <p>Active programme enrollments with phone numbers. Cancelled enrollments and missing phone numbers are skipped.</p>
        </div>
        <div class="material-item">
            <strong>CRM fields</strong>
            <p>Name, phone, email, lead stage, lead status, source, WhatsApp opt-in, and notes are sent.</p>
        </div>
    </aside>
</section>

<section class="section">
    <div class="detail-aside">
        <h2>CRM Response</h2>
        <?php if ($lastResponse): ?>
            <pre><?= e(json_encode($lastResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        <?php else: ?>
            <div class="material-item">
                <strong>Ready</strong>
                <p>Save your API key, create or select a parent group, choose a programme, enter a new subgroup name, then sync.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
