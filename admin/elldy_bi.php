<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$title = 'Elldy BI';
ensure_elldy_bi_settings_table();

$lastResponse = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_settings') {
            save_elldy_bi_settings($_POST);
            flash('success', 'Elldy BI settings saved.');
            redirect('elldy_bi.php');
        }

        if ($action === 'create_department') {
            $lastResponse = elldy_bi_create_department(
                trim((string) ($_POST['department_name'] ?? '')),
                trim((string) ($_POST['business_name'] ?? '')),
                trim((string) ($_POST['business_id'] ?? ''))
            );
            flash('success', 'Department request sent to Elldy BI.');
        }

        if ($action === 'fetch_summary') {
            $lastResponse = [
                'summary' => elldy_bi_summary(),
                'departments' => elldy_bi_departments(trim((string) ($_POST['business_id'] ?? ''))),
                'baskets' => elldy_bi_baskets(trim((string) ($_POST['business_id'] ?? ''))),
            ];
            flash('success', 'Elldy BI workspace details loaded.');
        }

        if ($action === 'fetch_basket_data') {
            $lastResponse = elldy_bi_basket_data(trim((string) ($_POST['basket_id'] ?? '')));
            flash('success', 'Basket datasets loaded from Elldy BI.');
        }

        if ($action === 'resolve_basket') {
            $basketId = trim((string) ($_POST['basket_id'] ?? ''));
            $basketName = trim((string) ($_POST['basket_name'] ?? ''));
            $businessName = trim((string) ($_POST['business_name'] ?? ''));
            $businessId = trim((string) ($_POST['business_id'] ?? ''));
            $department = trim((string) ($_POST['department'] ?? ''));

            if ($basketId === '') {
                $lastResponse = elldy_bi_create_basket($basketName, $businessName, $businessId);
                $basketId = elldy_bi_basket_id_from_response($lastResponse);
            } else {
                $lastResponse = ['used_existing_basket_id' => $basketId];
            }

            if ($basketId !== '') {
                $settings = elldy_bi_settings();
                $settings['default_basket_id'] = $basketId;
                $settings['default_basket_name'] = $basketName;
                $settings['default_business_id'] = $businessId;
                $settings['default_business_name'] = $businessName;
                $settings['default_department'] = $department;
                save_elldy_bi_settings($settings);
            }

            flash('success', $basketId !== '' ? 'Basket ID is ready.' : 'Basket request sent, but no basket ID was returned.');
        }

        if ($action === 'upload_tables') {
            $lastResponse = elldy_bi_upload_tables_to_basket(
                trim((string) ($_POST['business_name'] ?? '')),
                trim((string) ($_POST['business_id'] ?? '')),
                trim((string) ($_POST['basket_name'] ?? '')),
                trim((string) ($_POST['basket_id'] ?? '')),
                trim((string) ($_POST['department'] ?? '')),
                $_POST['table_names'] ?? [],
                trim((string) ($_POST['format'] ?? 'csv'))
            );
            flash('success', 'Selected database tables exported and uploaded to Elldy BI.');
        }
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
    }
}

$settings = elldy_bi_settings();
$tables = elldy_bi_database_tables();
$maskedToken = $settings['api_token'] !== '' ? str_repeat('*', 18) . substr($settings['api_token'], -6) : 'Not configured';
require __DIR__ . '/_admin_header.php';
?>
<section class="page-title">
    <p class="eyebrow">Integration</p>
    <h1>Elldy BI Workspace</h1>
    <p>Connect with token, prepare a basket ID, then push academy database tables into Elldy BI.</p>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <h2>Step 1: API Configuration</h2>
        <fieldset>
            <legend>Connection</legend>
            <p><a href="<?= e(elldy_bi_token_url()) ?>" target="_blank" rel="noopener">Generate token after Elldy browser login</a></p>
            <label>Base URL
                <input name="base_url" value="<?= e($settings['base_url']) ?>" placeholder="https://elldy.com" required>
            </label>
            <label>API Token
                <textarea name="api_token" rows="4" placeholder="Paste Elldy BI API token" required><?= e($settings['api_token']) ?></textarea>
            </label>
        </fieldset>
        <fieldset>
            <legend>Defaults</legend>
            <label>Business ID
                <input name="default_business_id" value="<?= e($settings['default_business_id']) ?>" placeholder="Example: 1">
            </label>
            <label>Business name
                <input name="default_business_name" value="<?= e($settings['default_business_name']) ?>" placeholder="Demo Business">
            </label>
            <label>Department
                <input name="default_department" value="<?= e($settings['default_department']) ?>" placeholder="Sales">
            </label>
            <label>Basket name
                <input name="default_basket_name" value="<?= e($settings['default_basket_name']) ?>" placeholder="Sales Basket">
            </label>
            <label>Basket ID
                <input name="default_basket_id" value="<?= e($settings['default_basket_id']) ?>" placeholder="Created basket ID">
            </label>
        </fieldset>
        <button class="button primary" type="submit">Save Elldy BI Settings</button>
    </form>

    <aside class="detail-aside">
        <h2>Status</h2>
        <div class="material-item"><strong>Base URL</strong><p><?= e($settings['base_url']) ?></p></div>
        <div class="material-item"><strong>Token</strong><p><?= e($maskedToken) ?></p></div>
        <div class="material-item"><strong>Default business ID</strong><p><?= e($settings['default_business_id'] ?: 'Not set') ?></p></div>
        <div class="material-item"><strong>Default business</strong><p><?= e($settings['default_business_name'] ?: 'Not set') ?></p></div>
        <div class="material-item"><strong>Default basket ID</strong><p><?= e($settings['default_basket_id'] ?: 'Not created') ?></p></div>
    </aside>
</section>

<section class="section">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="fetch_summary">
        <h2>Workspace Lookup</h2>
        <label>Business ID
            <input name="business_id" value="<?= e($settings['default_business_id']) ?>" placeholder="Optional">
        </label>
        <button class="button primary" type="submit">Load Summary, Departments & Baskets</button>
    </form>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_department">
        <h2>Create Department</h2>
        <label>Business ID
            <input name="business_id" value="<?= e($settings['default_business_id']) ?>" placeholder="Use ID if available">
        </label>
        <label>Business name
            <input name="business_name" value="<?= e($settings['default_business_name']) ?>" required>
        </label>
        <label>Department name
            <input name="department_name" value="<?= e($settings['default_department']) ?>" required>
        </label>
        <button class="button primary" type="submit">Create Department</button>
    </form>

    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="resolve_basket">
        <h2>Step 2: Basket</h2>
        <label>Business ID
            <input name="business_id" value="<?= e($settings['default_business_id']) ?>" placeholder="Use ID if available">
        </label>
        <label>Business name
            <input name="business_name" value="<?= e($settings['default_business_name']) ?>" required>
        </label>
        <label>Department
            <input name="department" value="<?= e($settings['default_department']) ?>" required>
        </label>
        <label>Basket name
            <input name="basket_name" value="<?= e($settings['default_basket_name']) ?>" required>
        </label>
        <label>Existing basket ID
            <input name="basket_id" value="<?= e($settings['default_basket_id']) ?>" placeholder="Leave blank to create from basket name">
        </label>
        <button class="button primary" type="submit">Create / Use Basket</button>
    </form>
</section>

<section class="section">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="fetch_basket_data">
        <h2>Basket Datasets</h2>
        <label>Basket ID
            <input name="basket_id" value="<?= e($settings['default_basket_id']) ?>" required>
        </label>
        <button class="button primary" type="submit">List Uploaded Datasets</button>
    </form>
</section>

<section class="admin-grid">
    <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload_tables">
        <h2>Step 3: Push Tables</h2>
        <label>Business ID
            <input name="business_id" value="<?= e($settings['default_business_id']) ?>" placeholder="Use ID if available">
        </label>
        <label>Business name
            <input name="business_name" value="<?= e($settings['default_business_name']) ?>" required>
        </label>
        <label>Basket name
            <input name="basket_name" value="<?= e($settings['default_basket_name']) ?>" required>
        </label>
        <label>Department
            <input name="department" value="<?= e($settings['default_department']) ?>" required>
        </label>
        <label>Basket ID
            <input name="basket_id" value="<?= e($settings['default_basket_id']) ?>" required>
        </label>
        <label>Database tables
            <select name="table_names[]" multiple size="10" required>
                <?php foreach ($tables as $table): ?>
                    <option value="<?= e($table) ?>"><?= e($table) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Export format
            <select name="format" required>
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
                <option value="parquet" disabled>Parquet needs PHP library</option>
            </select>
        </label>
        <button class="button primary" type="submit">Push Selected Tables</button>
    </form>

    <aside class="detail-aside">
        <h2>Response</h2>
        <?php if ($lastResponse): ?>
            <pre><?= e(json_encode($lastResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        <?php else: ?>
            <div class="material-item">
                <strong>Flow</strong>
                <p>Save token, create or enter basket ID, select database tables, and push them to Elldy BI.</p>
            </div>
            <div class="material-item">
                <strong>Formats</strong>
                <p>CSV and JSON are available now. Parquet can be added after installing a PHP Parquet writer library.</p>
            </div>
        <?php endif; ?>
    </aside>
</section>
<?php require __DIR__ . '/_admin_footer.php'; ?>
