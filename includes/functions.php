<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/whatsapp.php';

const AUTH_REMEMBER_SECONDS = 60 * 60 * 24 * 45;

function site_base_path(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDir = rtrim($scriptDir, '/');

    if (str_ends_with($scriptDir, '/admin')) {
        $scriptDir = substr($scriptDir, 0, -6);
    }

    return $scriptDir === '' ? '' : $scriptDir;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) AUTH_REMEMBER_SECONDS);
    $sessionPath = (site_base_path() === '' ? '' : site_base_path()) . '/';
    $sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => AUTH_REMEMBER_SECONDS,
            'path' => $sessionPath,
            'secure' => $sessionSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(AUTH_REMEMBER_SECONDS, $sessionPath . '; samesite=Lax', '', $sessionSecure, true);
    }

    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function admin_date_filter(string $default = 'all'): array
{
    $range = (string) ($_GET['range'] ?? $default);
    $allowed = ['all', 'today', 'yesterday', 'this_week', 'this_month', 'custom'];

    if (!in_array($range, $allowed, true)) {
        $range = $default;
    }

    $today = new DateTimeImmutable('today');
    $from = null;
    $to = null;

    if ($range === 'today') {
        $from = $today;
        $to = $today;
    } elseif ($range === 'yesterday') {
        $from = $today->modify('-1 day');
        $to = $from;
    } elseif ($range === 'this_week') {
        $from = $today->modify('monday this week');
        $to = $today;
    } elseif ($range === 'this_month') {
        $from = $today->modify('first day of this month');
        $to = $today;
    } elseif ($range === 'custom') {
        $fromInput = trim((string) ($_GET['from'] ?? ''));
        $toInput = trim((string) ($_GET['to'] ?? ''));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromInput) ? new DateTimeImmutable($fromInput) : null;
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toInput) ? new DateTimeImmutable($toInput) : null;
    }

    if ($from && !$to) {
        $to = $today;
    } elseif (!$from && $to) {
        $from = $to;
    }

    return [
        'range' => $range,
        'from' => $from ? $from->format('Y-m-d') : '',
        'to' => $to ? $to->format('Y-m-d') : '',
        'from_datetime' => $from ? $from->format('Y-m-d 00:00:00') : '',
        'to_datetime' => $to ? $to->format('Y-m-d 23:59:59') : '',
    ];
}

function admin_date_condition(string $expression, array $filter, array &$params): string
{
    if ($filter['from_datetime'] === '' || $filter['to_datetime'] === '') {
        return '';
    }

    $params[] = $filter['from_datetime'];
    $params[] = $filter['to_datetime'];

    return "{$expression} BETWEEN ? AND ?";
}

function admin_date_filter_url(string $path, array $extra, array $filter): string
{
    $query = array_merge($extra, ['range' => $filter['range']]);

    if ($filter['range'] === 'custom') {
        $query['from'] = $filter['from'];
        $query['to'] = $filter['to'];
    }

    return $path . '?' . http_build_query($query);
}

function public_url(string $path = ''): string
{
    $base = site_base_path();
    $path = ltrim($path, '/');

    return ($base === '' ? '' : $base) . '/' . $path;
}

function asset_url(string $path): string
{
    $fullPath = __DIR__ . '/../' . ltrim($path, '/');
    $version = is_file($fullPath) ? (string) filemtime($fullPath) : '';
    $url = public_url($path);

    return $version !== '' ? $url . '?v=' . rawurlencode($version) : $url;
}

function academy_faqs(): array
{
    return [
        [
            'question' => 'How do I continue my classes?',
            'answer' => 'Login to your Elldy Academy account, open your trainee dashboard, and click Watch Videos or Join Live Sessions beside your enrolled program. You can continue from your available course materials anytime.',
        ],
        [
            'question' => 'Do I need to create an account?',
            'answer' => 'Yes. Your account keeps your enrollment, video access, payment status, profile details, and certificate access connected safely in one place.',
        ],
        [
            'question' => 'Do I need to login every time?',
            'answer' => 'No. After WhatsApp OTP login, the same browser or installed app stays remembered on that device. If you use another device or browser, login again with your registered WhatsApp number.',
        ],
        [
            'question' => 'What is Elldy?',
            'answer' => 'Elldy is a growing Business Intelligence platform that helps businesses understand data, build dashboards, track performance, and make better decisions from real business information.',
        ],
        [
            'question' => 'What is Elldy Academy?',
            'answer' => 'Elldy Academy is the official learning initiative of the Elldy BI platform, created for students, analysts, business owners, CEOs, and teams who want practical data analytics and BI skills.',
        ],
        [
            'question' => 'Are these courses useful for my career?',
            'answer' => 'Yes. The courses focus on practical analytics, dashboards, KPIs, business cases, and decision-making skills that are useful for data analyst, business analyst, MIS, sales, marketing, operations, and management roles.',
        ],
        [
            'question' => 'Can I access videos after completing the course?',
            'answer' => 'Yes. You can access your available course videos anytime from your learning workspace, based on your enrollment access.',
        ],
        [
            'question' => 'How do I get my certificate?',
            'answer' => 'After your eligible course access is completed or approved, login to Elldy Academy and use the certificate option in your dashboard to download your valid certificate.',
        ],
        [
            'question' => 'What should I do if I have doubts?',
            'answer' => 'Use the WhatsApp support button on the website to contact the Elldy Academy team. Share your course name and question so the team can help you faster.',
        ],
    ];
}

function site_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . public_url($path);
}

function ensure_razorpay_settings_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS razorpay_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            key_id VARCHAR(120) NULL,
            key_secret TEXT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'INR',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $stmt = db()->prepare(
        "INSERT INTO razorpay_settings (id, key_id, key_secret, currency)
         VALUES (1, '', '', 'INR')
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute();
}

function ensure_s3_settings_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS s3_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            access_key_id VARCHAR(160) NULL,
            secret_access_key TEXT NULL,
            region VARCHAR(80) NOT NULL DEFAULT 'ap-south-1',
            bucket_name VARCHAR(190) NULL,
            upload_prefix VARCHAR(190) NOT NULL DEFAULT 'course-videos',
            public_base_url VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $stmt = db()->prepare(
        "INSERT INTO s3_settings (id, access_key_id, secret_access_key, region, bucket_name, upload_prefix, public_base_url)
         VALUES (1, '', '', 'ap-south-1', '', 'course-videos', '')
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute();
}

function s3_settings(): array
{
    ensure_s3_settings_table();
    $settings = db()->query('SELECT * FROM s3_settings WHERE id = 1')->fetch() ?: [];

    return [
        'access_key_id' => trim((string) ($settings['access_key_id'] ?? '')),
        'secret_access_key' => trim((string) ($settings['secret_access_key'] ?? '')),
        'region' => trim((string) ($settings['region'] ?? 'ap-south-1')) ?: 'ap-south-1',
        'bucket_name' => trim((string) ($settings['bucket_name'] ?? '')),
        'upload_prefix' => trim((string) ($settings['upload_prefix'] ?? 'course-videos')) ?: 'course-videos',
        'public_base_url' => rtrim(trim((string) ($settings['public_base_url'] ?? '')), '/'),
    ];
}

function save_s3_settings(array $data): void
{
    ensure_s3_settings_table();
    $stmt = db()->prepare(
        "UPDATE s3_settings
         SET access_key_id = ?, secret_access_key = ?, region = ?, bucket_name = ?, upload_prefix = ?, public_base_url = ?
         WHERE id = 1"
    );
    $stmt->execute([
        trim((string) ($data['access_key_id'] ?? '')),
        trim((string) ($data['secret_access_key'] ?? '')),
        trim((string) ($data['region'] ?? 'ap-south-1')) ?: 'ap-south-1',
        trim((string) ($data['bucket_name'] ?? '')),
        trim((string) ($data['upload_prefix'] ?? 'course-videos')) ?: 'course-videos',
        rtrim(trim((string) ($data['public_base_url'] ?? '')), '/'),
    ]);
}

function ensure_zoom_settings_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS zoom_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            client_id VARCHAR(190) NULL,
            client_secret TEXT NULL,
            sdk_version VARCHAR(20) NOT NULL DEFAULT '5.1.4',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $stmt = db()->prepare(
        "INSERT INTO zoom_settings (id, client_id, client_secret, sdk_version)
         VALUES (1, '', '', '5.1.4')
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute();
}

function zoom_settings(): array
{
    ensure_zoom_settings_table();
    $settings = db()->query('SELECT * FROM zoom_settings WHERE id = 1')->fetch() ?: [];

    return [
        'client_id' => trim((string) ($settings['client_id'] ?? '')),
        'client_secret' => trim((string) ($settings['client_secret'] ?? '')),
        'sdk_version' => trim((string) ($settings['sdk_version'] ?? '5.1.4')) ?: '5.1.4',
    ];
}

function save_zoom_settings(array $data): void
{
    ensure_zoom_settings_table();
    $stmt = db()->prepare(
        "UPDATE zoom_settings
         SET client_id = ?, client_secret = ?, sdk_version = ?
         WHERE id = 1"
    );
    $stmt->execute([
        trim((string) ($data['client_id'] ?? '')),
        trim((string) ($data['client_secret'] ?? '')),
        trim((string) ($data['sdk_version'] ?? '5.1.4')) ?: '5.1.4',
    ]);
}

function ensure_elldy_bi_settings_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS elldy_bi_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            base_url VARCHAR(255) NOT NULL DEFAULT 'https://elldy.com',
            api_token TEXT NULL,
            default_business_id VARCHAR(80) NULL,
            default_business_name VARCHAR(190) NULL,
            default_department VARCHAR(190) NULL,
            default_basket_name VARCHAR(190) NULL,
            default_basket_id VARCHAR(80) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'elldy_bi_settings'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($existing['default_business_id'])) {
        db()->exec("ALTER TABLE elldy_bi_settings ADD COLUMN default_business_id VARCHAR(80) NULL AFTER api_token");
    }

    $stmt = db()->prepare(
        "INSERT INTO elldy_bi_settings (id, base_url, api_token, default_business_id, default_business_name, default_department, default_basket_name, default_basket_id)
         VALUES (1, 'https://elldy.com', '', '', '', '', '', '')
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute();
}

function elldy_bi_settings(): array
{
    ensure_elldy_bi_settings_table();
    $settings = db()->query('SELECT * FROM elldy_bi_settings WHERE id = 1')->fetch() ?: [];

    return [
        'base_url' => rtrim(trim((string) ($settings['base_url'] ?? 'https://elldy.com')), '/') ?: 'https://elldy.com',
        'api_token' => trim((string) ($settings['api_token'] ?? '')),
        'default_business_id' => trim((string) ($settings['default_business_id'] ?? '')),
        'default_business_name' => trim((string) ($settings['default_business_name'] ?? '')),
        'default_department' => trim((string) ($settings['default_department'] ?? '')),
        'default_basket_name' => trim((string) ($settings['default_basket_name'] ?? '')),
        'default_basket_id' => trim((string) ($settings['default_basket_id'] ?? '')),
    ];
}

function save_elldy_bi_settings(array $data): void
{
    ensure_elldy_bi_settings_table();
    $stmt = db()->prepare(
        "UPDATE elldy_bi_settings
         SET base_url = ?, api_token = ?, default_business_id = ?, default_business_name = ?, default_department = ?, default_basket_name = ?, default_basket_id = ?
         WHERE id = 1"
    );
    $stmt->execute([
        rtrim(trim((string) ($data['base_url'] ?? 'https://elldy.com')), '/') ?: 'https://elldy.com',
        trim((string) ($data['api_token'] ?? '')),
        trim((string) ($data['default_business_id'] ?? '')),
        trim((string) ($data['default_business_name'] ?? '')),
        trim((string) ($data['default_department'] ?? '')),
        trim((string) ($data['default_basket_name'] ?? '')),
        trim((string) ($data['default_basket_id'] ?? '')),
    ]);
}

function elldy_bi_api_url(string $path): string
{
    $settings = elldy_bi_settings();

    return $settings['base_url'] . '/' . ltrim($path, '/');
}

function elldy_bi_require_configured(): array
{
    $settings = elldy_bi_settings();

    if ($settings['api_token'] === '') {
        throw new RuntimeException('Elldy BI API token is missing. Save it in Admin > Elldy BI.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled on the server.');
    }

    return $settings;
}

function elldy_bi_decode_response(string|false $response, int $status, string $curlError): array
{
    if ($response === false) {
        throw new RuntimeException('Unable to reach Elldy BI API. ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Elldy BI API returned an invalid response.');
    }

    if ($status < 200 || $status >= 300) {
        $message = (string) ($decoded['message'] ?? $decoded['error'] ?? 'Elldy BI API request failed.');
        throw new RuntimeException($message);
    }

    return $decoded;
}

function elldy_bi_post_json(string $path, array $data): array
{
    $settings = elldy_bi_require_configured();
    $ch = curl_init(elldy_bi_api_url($path));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'X-Elldy-API-Token: ' . $settings['api_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return elldy_bi_decode_response($response, $status, $curlError);
}

function elldy_bi_get_json(string $path, array $query = []): array
{
    $settings = elldy_bi_require_configured();
    $url = elldy_bi_api_url($path);

    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Elldy-API-Token: ' . $settings['api_token']],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return elldy_bi_decode_response($response, $status, $curlError);
}

function elldy_bi_summary(): array
{
    return elldy_bi_get_json('/api/workspace/summary/');
}

function elldy_bi_departments(string $businessId = ''): array
{
    return elldy_bi_get_json('/api/workspace/departments/', $businessId !== '' ? ['business_id' => $businessId] : []);
}

function elldy_bi_baskets(string $businessId = ''): array
{
    return elldy_bi_get_json('/api/workspace/baskets/', $businessId !== '' ? ['business_id' => $businessId] : []);
}

function elldy_bi_basket_data(string $basketId): array
{
    return elldy_bi_get_json('/api/workspace/basket-data/', ['basket_id' => $basketId]);
}

function elldy_bi_token_url(): string
{
    return elldy_bi_api_url('/api/workspace/token/?format=json');
}

function elldy_bi_business_payload(string $businessName, string $businessId = ''): array
{
    return $businessId !== '' ? ['business_id' => $businessId] : ['business_name' => $businessName];
}

function elldy_bi_create_department(string $departmentName, string $businessName, string $businessId = ''): array
{
    return elldy_bi_post_json('/api/workspace/departments/', array_merge(
        ['department_name' => $departmentName],
        elldy_bi_business_payload($businessName, $businessId)
    ));
}

function elldy_bi_create_basket(string $basketName, string $businessName, string $businessId = ''): array
{
    return elldy_bi_post_json('/api/workspace/baskets/', array_merge(
        ['basket_name' => $basketName],
        elldy_bi_business_payload($businessName, $businessId)
    ));
}

function elldy_bi_basket_id_from_response(array $response): string
{
    foreach ([
        $response['basket']['id'] ?? null,
        $response['id'] ?? null,
        $response['data']['basket']['id'] ?? null,
        $response['data']['id'] ?? null,
    ] as $candidate) {
        if ($candidate !== null && (string) $candidate !== '') {
            return (string) $candidate;
        }
    }

    return '';
}

function elldy_bi_database_tables(): array
{
    $database = (string) db()->query('SELECT DATABASE()')->fetchColumn();
    $stmt = db()->prepare(
        "SELECT TABLE_NAME
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );
    $stmt->execute([$database]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function elldy_bi_assert_table_name(string $tableName): string
{
    $tableName = trim($tableName);
    if ($tableName === '' || !in_array($tableName, elldy_bi_database_tables(), true)) {
        throw new RuntimeException('Please choose a valid database table.');
    }

    return $tableName;
}

function elldy_bi_export_table_file(string $tableName, string $format): array
{
    $tableName = elldy_bi_assert_table_name($tableName);
    $format = strtolower(trim($format));

    if (!in_array($format, ['csv', 'json'], true)) {
        throw new RuntimeException('This server can export database tables as CSV or JSON. Parquet needs an extra PHP library before it can be enabled.');
    }

    $stmt = db()->query('SELECT * FROM `' . str_replace('`', '``', $tableName) . '`');
    $rows = $stmt->fetchAll();
    $path = tempnam(sys_get_temp_dir(), 'elldy_bi_');
    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary export file.');
    }

    $fileName = $tableName . '.' . $format;
    $mime = $format === 'json' ? 'application/json' : 'text/csv';

    if ($format === 'json') {
        file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } else {
        $handle = fopen($path, 'wb');
        if (!$handle) {
            throw new RuntimeException('Unable to write the CSV export file.');
        }

        if ($rows) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);
    }

    return ['path' => $path, 'name' => $fileName, 'mime' => $mime, 'rows' => count($rows)];
}

function elldy_bi_table_rows(string $tableName): array
{
    $tableName = elldy_bi_assert_table_name($tableName);
    $stmt = db()->query('SELECT * FROM `' . str_replace('`', '``', $tableName) . '`');

    return $stmt->fetchAll();
}

function elldy_bi_upload_json_rows(string $basketId, string $department, string $title, array $rows): array
{
    return elldy_bi_post_json('/api/workspace/basket-data/', [
        'basket_id' => $basketId,
        'department' => $department,
        'title' => $title,
        'rows' => $rows,
    ]);
}

function elldy_bi_upload_basket_file(string $basketId, string $department, string $title, string $path, string $fileName, string $mime): array
{
    $settings = elldy_bi_require_configured();

    if ($basketId === '' || $department === '' || $title === '') {
        throw new RuntimeException('Basket, department, and data title are required.');
    }

    if (!is_file($path)) {
        throw new RuntimeException('Export file was not created.');
    }

    $ch = curl_init(elldy_bi_api_url('/api/workspace/basket-data/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-Elldy-API-Token: ' . $settings['api_token']],
        CURLOPT_POSTFIELDS => [
            'basket_id' => $basketId,
            'department' => $department,
            'title' => $title,
            'file' => new CURLFile($path, $mime, $fileName),
        ],
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return elldy_bi_decode_response($response, $status, $curlError);
}

function elldy_bi_upload_table_to_basket(string $businessName, string $businessId, string $basketName, string $basketId, string $department, string $title, string $tableName, string $format): array
{
    $departmentResponse = elldy_bi_create_department($department, $businessName, $businessId);
    $basketResponse = null;
    $basketId = trim($basketId);

    if ($basketId === '') {
        $basketResponse = elldy_bi_create_basket($basketName, $businessName, $businessId);
        $basketId = elldy_bi_basket_id_from_response($basketResponse);
    }

    if ($basketId === '') {
        throw new RuntimeException('Elldy BI did not return a basket ID for this basket name.');
    }

    $format = strtolower(trim($format));
    if ($format === 'json') {
        $rows = elldy_bi_table_rows($tableName);
        $uploadResponse = elldy_bi_upload_json_rows($basketId, $department, $title, $rows);
        $exportedRows = count($rows);
    } else {
        $export = elldy_bi_export_table_file($tableName, $format);

        try {
            $uploadResponse = elldy_bi_upload_basket_file(
                $basketId,
                $department,
                $title,
                $export['path'],
                $export['name'],
                $export['mime']
            );
        } finally {
            if (is_file($export['path'])) {
                unlink($export['path']);
            }
        }

        $exportedRows = (int) $export['rows'];
    }

    $settings = elldy_bi_settings();
    $settings['default_business_id'] = $businessId;
    $settings['default_business_name'] = $businessName;
    $settings['default_department'] = $department;
    $settings['default_basket_name'] = $basketName;
    $settings['default_basket_id'] = $basketId;
    save_elldy_bi_settings($settings);

    return [
        'basket_id' => $basketId,
        'exported_rows' => $exportedRows,
        'format' => $format,
        'department_response' => $departmentResponse,
        'basket_response' => $basketResponse ?? ['used_existing_basket_id' => $basketId],
        'upload_response' => $uploadResponse,
    ];
}

function elldy_bi_upload_tables_to_basket(string $businessName, string $businessId, string $basketName, string $basketId, string $department, array $tableNames, string $format): array
{
    $tableNames = array_values(array_filter(array_map('trim', $tableNames), fn (string $tableName): bool => $tableName !== ''));

    if (!$tableNames) {
        throw new RuntimeException('Please choose at least one database table.');
    }

    $results = [];
    foreach ($tableNames as $tableName) {
        $results[$tableName] = elldy_bi_upload_table_to_basket(
            $businessName,
            $businessId,
            $basketName,
            $basketId,
            $department,
            $tableName,
            $tableName,
            $format
        );
        $basketId = (string) ($results[$tableName]['basket_id'] ?? $basketId);
    }

    return $results;
}

function elldy_bi_upload_basket_data(string $basketId, string $department, string $title, array $file): array
{
    $settings = elldy_bi_require_configured();
    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($basketId === '' || $department === '' || $title === '') {
        throw new RuntimeException('Basket ID, department, and title are required.');
    }

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Please choose a CSV file to upload.');
    }

    $originalName = (string) ($file['name'] ?? 'data.csv');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new RuntimeException('Please upload a CSV file.');
    }

    $ch = curl_init(elldy_bi_api_url('/api/workspace/basket-data/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-Elldy-API-Token: ' . $settings['api_token']],
        CURLOPT_POSTFIELDS => [
            'basket_id' => $basketId,
            'department' => $department,
            'title' => $title,
            'file' => new CURLFile($tmpName, 'text/csv', $originalName),
        ],
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return elldy_bi_decode_response($response, $status, $curlError);
}

function ensure_crm_settings_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS crm_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            base_url VARCHAR(255) NOT NULL DEFAULT 'https://elldy.com',
            api_key TEXT NULL,
            default_business_id VARCHAR(80) NULL,
            default_parent_group_id VARCHAR(80) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'crm_settings'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($existing['default_business_id'])) {
        db()->exec("ALTER TABLE crm_settings ADD COLUMN default_business_id VARCHAR(80) NULL AFTER api_key");
    }

    if (!isset($existing['default_parent_group_id'])) {
        db()->exec("ALTER TABLE crm_settings ADD COLUMN default_parent_group_id VARCHAR(80) NULL AFTER default_business_id");
    }

    $stmt = db()->prepare(
        "INSERT INTO crm_settings (id, base_url, api_key, default_business_id, default_parent_group_id)
         VALUES (1, 'https://elldy.com', '', '', '')
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute();
}

function crm_settings(): array
{
    ensure_crm_settings_table();
    $settings = db()->query('SELECT * FROM crm_settings WHERE id = 1')->fetch() ?: [];

    return [
        'base_url' => rtrim(trim((string) ($settings['base_url'] ?? 'https://elldy.com')), '/') ?: 'https://elldy.com',
        'api_key' => trim((string) ($settings['api_key'] ?? '')),
        'default_business_id' => trim((string) ($settings['default_business_id'] ?? '')),
        'default_parent_group_id' => trim((string) ($settings['default_parent_group_id'] ?? '')),
    ];
}

function save_crm_settings(array $data): void
{
    ensure_crm_settings_table();
    $stmt = db()->prepare(
        "UPDATE crm_settings
         SET base_url = ?, api_key = ?, default_business_id = ?, default_parent_group_id = ?
         WHERE id = 1"
    );
    $stmt->execute([
        rtrim(trim((string) ($data['base_url'] ?? 'https://elldy.com')), '/') ?: 'https://elldy.com',
        trim((string) ($data['api_key'] ?? '')),
        trim((string) ($data['default_business_id'] ?? '')),
        trim((string) ($data['default_parent_group_id'] ?? '')),
    ]);
}

function crm_api_url(string $path): string
{
    $settings = crm_settings();

    return $settings['base_url'] . '/' . ltrim($path, '/');
}

function crm_require_configured(): array
{
    $settings = crm_settings();

    if ($settings['api_key'] === '') {
        throw new RuntimeException('CRM API key is missing. Save it in Admin > CRM Sync first.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled on the server.');
    }

    return $settings;
}

function crm_decode_response(string|false $response, int $status, string $curlError): array
{
    if ($response === false) {
        throw new RuntimeException('Unable to reach CRM API. ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('CRM API returned an invalid response.');
    }

    if ($status < 200 || $status >= 300) {
        $message = (string) ($decoded['message'] ?? $decoded['error'] ?? 'CRM API request failed.');
        throw new RuntimeException($message);
    }

    return $decoded;
}

function crm_json_payload(array $data, array $settings): array
{
    if (($settings['default_business_id'] ?? '') !== '' && !isset($data['biz_id'])) {
        $data['biz_id'] = $settings['default_business_id'];
    }

    return $data;
}

function crm_get_json(string $path, array $query = []): array
{
    $settings = crm_require_configured();
    if (($settings['default_business_id'] ?? '') !== '' && !isset($query['biz_id'])) {
        $query['biz_id'] = $settings['default_business_id'];
    }

    $url = crm_api_url($path);
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $settings['api_key'],
            'X-API-KEY: ' . $settings['api_key'],
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return crm_decode_response($response, $status, $curlError);
}

function crm_post_json(string $path, array $data): array
{
    $settings = crm_require_configured();
    $ch = curl_init(crm_api_url($path));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $settings['api_key'],
            'X-API-KEY: ' . $settings['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(crm_json_payload($data, $settings)),
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return crm_decode_response($response, $status, $curlError);
}

function crm_groups(): array
{
    return crm_get_json('/api/groups');
}

function crm_create_group(string $groupName, string $parentId = ''): array
{
    $groupName = trim($groupName);
    if ($groupName === '') {
        throw new RuntimeException('Group name is required.');
    }

    $payload = ['group_name' => $groupName];
    if ($parentId !== '') {
        $payload['parent_id'] = ctype_digit($parentId) ? (int) $parentId : $parentId;
    }

    return crm_post_json('/api/groups', $payload);
}

function crm_group_id_from_response(array $response): string
{
    foreach ([
        $response['group']['id'] ?? null,
        $response['id'] ?? null,
        $response['data']['group']['id'] ?? null,
        $response['data']['id'] ?? null,
        $response['subgroup']['id'] ?? null,
        $response['data']['subgroup']['id'] ?? null,
    ] as $candidate) {
        if ($candidate !== null && (string) $candidate !== '') {
            return (string) $candidate;
        }
    }

    return '';
}

function crm_flatten_groups(array $response): array
{
    $candidates = [
        $response['groups'] ?? null,
        $response['data']['groups'] ?? null,
        $response['data'] ?? null,
    ];

    $groups = [];
    $walk = static function (array $items) use (&$walk, &$groups): void {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? $item['group_id'] ?? null;
            if ($id !== null) {
                $groups[] = $item;
            }
            foreach (['subgroups', 'children', 'groups'] as $childKey) {
                if (isset($item[$childKey]) && is_array($item[$childKey])) {
                    $walk($item[$childKey]);
                }
            }
        }
    };

    foreach ($candidates as $candidate) {
        if (is_array($candidate)) {
            $walk(array_is_list($candidate) ? $candidate : [$candidate]);
            break;
        }
    }

    return $groups;
}

function crm_parent_groups(array $response): array
{
    return array_values(array_filter(crm_flatten_groups($response), static function (array $group): bool {
        $parent = $group['parent_id'] ?? $group['parentId'] ?? null;
        return $parent === null || (string) $parent === '' || (string) $parent === '0';
    }));
}

function crm_program_contacts(int $courseId): array
{
    if ($courseId <= 0) {
        throw new RuntimeException('Please choose a programme to sync.');
    }

    $stmt = db()->prepare(
        "SELECT e.id AS enrollment_id,
                e.status,
                e.created_at,
                u.name,
                u.email,
                u.phone,
                c.title AS course_title
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         WHERE e.course_id = ?
           AND COALESCE(u.phone, '') != ''
           AND e.status != 'cancelled'
         ORDER BY e.created_at DESC"
    );
    $stmt->execute([$courseId]);

    $contacts = [];
    foreach ($stmt->fetchAll() as $row) {
        $contacts[] = [
            'full_name' => (string) ($row['name'] ?: 'Academy Learner'),
            'phone_number' => (string) $row['phone'],
            'email' => (string) ($row['email'] ?? ''),
            'lead_stage' => 'academy',
            'lead_status' => (string) ($row['status'] ?: 'new'),
            'source' => 'Elldy Academy - ' . (string) $row['course_title'],
            'whatsapp_opt_in' => true,
            'notes' => 'Programme: ' . (string) $row['course_title'] . '; Enrollment ID: ' . (string) $row['enrollment_id'],
        ];
    }

    return $contacts;
}

function crm_import_contacts(string $subgroupId, array $contacts): array
{
    $subgroupId = trim($subgroupId);
    if ($subgroupId === '') {
        throw new RuntimeException('Subgroup ID is required before importing contacts.');
    }

    if (!$contacts) {
        throw new RuntimeException('No contacts with phone numbers found for this programme.');
    }

    return crm_post_json('/api/contacts/import', [
        'subgroup_id' => ctype_digit($subgroupId) ? (int) $subgroupId : $subgroupId,
        'contacts' => $contacts,
    ]);
}

function crm_sync_program_contacts(int $courseId, string $parentGroupId, string $subgroupName): array
{
    $parentGroupId = trim($parentGroupId);
    if ($parentGroupId === '') {
        throw new RuntimeException('Please select a parent group.');
    }

    $subgroupResponse = crm_create_group($subgroupName, $parentGroupId);
    $subgroupId = crm_group_id_from_response($subgroupResponse);
    if ($subgroupId === '') {
        throw new RuntimeException('CRM created the subgroup request, but did not return a subgroup ID.');
    }

    $contacts = crm_program_contacts($courseId);
    $importResponse = crm_import_contacts($subgroupId, $contacts);

    $settings = crm_settings();
    $settings['default_parent_group_id'] = $parentGroupId;
    save_crm_settings($settings);

    return [
        'parent_group_id' => $parentGroupId,
        'subgroup_id' => $subgroupId,
        'contacts_prepared' => count($contacts),
        'subgroup_response' => $subgroupResponse,
        'import_response' => $importResponse,
    ];
}

function zoom_base64_url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function zoom_meeting_details_from_url(string $url): array
{
    $details = [
        'meeting_number' => '',
        'password' => '',
    ];
    $url = trim($url);

    if ($url === '') {
        return $details;
    }

    $parts = parse_url($url);
    $path = (string) ($parts['path'] ?? '');
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);

    if (preg_match('#/(?:j|wc/join|w)/(\d+)#', $path, $matches)) {
        $details['meeting_number'] = $matches[1];
    } elseif (preg_match('/^\d{9,12}$/', preg_replace('/\D+/', '', $url))) {
        $details['meeting_number'] = preg_replace('/\D+/', '', $url);
    }

    foreach (['pwd', 'password', 'passWord'] as $key) {
        if (!empty($query[$key])) {
            $details['password'] = (string) $query[$key];
            break;
        }
    }

    return $details;
}

function zoom_sdk_signature(string $meetingNumber, int $role): string
{
    $settings = zoom_settings();

    if ($settings['client_id'] === '' || $settings['client_secret'] === '') {
        return '';
    }

    $issuedAt = time() - 30;
    $expiresAt = $issuedAt + (60 * 60 * 2);
    $header = zoom_base64_url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $payload = zoom_base64_url(json_encode([
        'appKey' => $settings['client_id'],
        'sdkKey' => $settings['client_id'],
        'mn' => $meetingNumber,
        'role' => $role,
        'iat' => $issuedAt,
        'exp' => $expiresAt,
        'tokenExp' => $expiresAt,
    ], JSON_THROW_ON_ERROR));
    $signature = zoom_base64_url(hash_hmac('sha256', $header . '.' . $payload, $settings['client_secret'], true));

    return $header . '.' . $payload . '.' . $signature;
}

function s3_hmac(string $key, string $data): string
{
    return hash_hmac('sha256', $data, $key, true);
}

function s3_signing_key(string $secret, string $date, string $region, string $service): string
{
    $dateKey = s3_hmac('AWS4' . $secret, $date);
    $regionKey = s3_hmac($dateKey, $region);
    $serviceKey = s3_hmac($regionKey, $service);

    return s3_hmac($serviceKey, 'aws4_request');
}

function s3_object_url(array $settings, string $objectKey): string
{
    if ($settings['public_base_url'] !== '') {
        return $settings['public_base_url'] . '/' . implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    }

    return 'https://' . rawurlencode($settings['bucket_name']) . '.s3.' . rawurlencode($settings['region']) . '.amazonaws.com/' .
        implode('/', array_map('rawurlencode', explode('/', $objectKey)));
}

function s3_object_key_from_url(string $url): ?string
{
    $settings = s3_settings();
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = ltrim((string) ($parts['path'] ?? ''), '/');
    $expectedHost = strtolower($settings['bucket_name'] . '.s3.' . $settings['region'] . '.amazonaws.com');

    if ($path === '') {
        return null;
    }

    if ($host === $expectedHost) {
        return rawurldecode($path);
    }

    if ($settings['public_base_url'] !== '') {
        $baseParts = parse_url($settings['public_base_url']);
        $baseHost = strtolower((string) ($baseParts['host'] ?? ''));
        $basePath = trim((string) ($baseParts['path'] ?? ''), '/');

        if ($host === $baseHost && ($basePath === '' || str_starts_with($path, $basePath . '/'))) {
            $objectPath = $basePath === '' ? $path : substr($path, strlen($basePath) + 1);
            return rawurldecode($objectPath);
        }
    }

    return null;
}

function s3_presigned_get_url(string $objectKey, int $expires = 3600): string
{
    $settings = s3_settings();

    if ($settings['access_key_id'] === '' || $settings['secret_access_key'] === '' || $settings['bucket_name'] === '') {
        return s3_object_url($settings, $objectKey);
    }

    $amzDate = gmdate('Ymd\THis\Z');
    $shortDate = gmdate('Ymd');
    $host = $settings['bucket_name'] . '.s3.' . $settings['region'] . '.amazonaws.com';
    $canonicalUri = '/' . implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    $credentialScope = $shortDate . '/' . $settings['region'] . '/s3/aws4_request';
    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $settings['access_key_id'] . '/' . $credentialScope,
        'X-Amz-Date' => $amzDate,
        'X-Amz-Expires' => (string) max(60, min($expires, 604800)),
        'X-Amz-SignedHeaders' => 'host',
    ];
    ksort($query);
    $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQuery}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";
    $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
    $signature = hash_hmac('sha256', $stringToSign, s3_signing_key($settings['secret_access_key'], $shortDate, $settings['region'], 's3'));

    return 'https://' . $host . $canonicalUri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
}

function s3_new_material_object_key(string $originalName): string
{
    $settings = s3_settings();
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $safeExtension = preg_match('/^[a-z0-9]{2,8}$/', $extension) ? '.' . $extension : '';

    return trim($settings['upload_prefix'], '/') . '/' . date('Y/m') . '/' . bin2hex(random_bytes(12)) . $safeExtension;
}

function s3_new_video_object_key(string $originalName): string
{
    return s3_new_material_object_key($originalName);
}

function s3_presigned_put_url(string $objectKey, string $contentType, int $expires = 3600): string
{
    $settings = s3_settings();
    $amzDate = gmdate('Ymd\THis\Z');
    $shortDate = gmdate('Ymd');
    $host = $settings['bucket_name'] . '.s3.' . $settings['region'] . '.amazonaws.com';
    $canonicalUri = '/' . implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    $credentialScope = $shortDate . '/' . $settings['region'] . '/s3/aws4_request';
    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $settings['access_key_id'] . '/' . $credentialScope,
        'X-Amz-Date' => $amzDate,
        'X-Amz-Expires' => (string) max(60, min($expires, 604800)),
        'X-Amz-SignedHeaders' => 'content-type;host',
    ];
    ksort($query);
    $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $canonicalHeaders = 'content-type:' . $contentType . "\n" . 'host:' . $host . "\n";
    $canonicalRequest = "PUT\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\ncontent-type;host\nUNSIGNED-PAYLOAD";
    $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
    $signature = hash_hmac('sha256', $stringToSign, s3_signing_key($settings['secret_access_key'], $shortDate, $settings['region'], 's3'));

    return 'https://' . $host . $canonicalUri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
}

function playback_video_url(string $url): string
{
    $objectKey = s3_object_key_from_url($url);

    return $objectKey !== null ? s3_presigned_get_url($objectKey) : $url;
}

function material_extension(string $url): string
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

    return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

function is_image_material_url(string $url): bool
{
    return in_array(material_extension($url), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);
}

function is_pdf_material_url(string $url): bool
{
    return material_extension($url) === 'pdf';
}

function material_display_label(array $material): string
{
    $type = (string) ($material['material_type'] ?? 'video');

    if ($type === 'live_session') {
        return 'Live Session';
    }

    if ($type === 'video') {
        return 'Video';
    }

    $extension = material_extension((string) ($material['file_url'] ?? ''));

    return match ($extension) {
        'pdf' => 'PDF',
        'doc', 'docx' => 'Document',
        'ppt', 'pptx' => 'Presentation',
        'xls', 'xlsx' => 'Spreadsheet',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' => 'Image',
        default => 'Resource',
    };
}

function primary_material_types_for_delivery(string $deliveryType): array
{
    return $deliveryType === 'live_session' ? ['live_session', 'video'] : ['video'];
}

function is_allowed_material_mime(string $mime): bool
{
    return str_starts_with($mime, 'video/')
        || str_starts_with($mime, 'image/')
        || in_array($mime, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], true);
}

function is_allowed_image_mime(string $mime): bool
{
    return str_starts_with($mime, 'image/');
}

function is_allowed_video_mime(string $mime): bool
{
    return str_starts_with($mime, 'video/');
}

function upload_material_to_s3(array $file): string
{
    $settings = s3_settings();

    if ($settings['access_key_id'] === '' || $settings['secret_access_key'] === '' || $settings['bucket_name'] === '') {
        throw new RuntimeException('S3 settings are incomplete. Add AWS credentials, region, and bucket first.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled on the server.');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Material upload failed before reaching S3.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpName)) {
        throw new RuntimeException('Uploaded material could not be verified.');
    }

    $mime = mime_content_type($tmpName) ?: '';
    if (!is_allowed_material_mime($mime)) {
        throw new RuntimeException('Please upload a valid video, image, PDF, Word, PowerPoint, or Excel file.');
    }

    $objectKey = s3_new_material_object_key((string) ($file['name'] ?? 'material'));
    $payloadHash = 'UNSIGNED-PAYLOAD';
    $amzDate = gmdate('Ymd\THis\Z');
    $shortDate = gmdate('Ymd');
    $host = $settings['bucket_name'] . '.s3.' . $settings['region'] . '.amazonaws.com';
    $canonicalUri = '/' . implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    $canonicalHeaders = 'content-type:' . $mime . "\n" .
        'host:' . $host . "\n" .
        'x-amz-content-sha256:' . $payloadHash . "\n" .
        'x-amz-date:' . $amzDate . "\n";
    $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
    $canonicalRequest = "PUT\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
    $credentialScope = $shortDate . '/' . $settings['region'] . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
    $signature = hash_hmac('sha256', $stringToSign, s3_signing_key($settings['secret_access_key'], $shortDate, $settings['region'], 's3'));
    $authorization = 'AWS4-HMAC-SHA256 Credential=' . $settings['access_key_id'] . '/' . $credentialScope .
        ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $stream = fopen($tmpName, 'rb');
    if ($stream === false) {
        throw new RuntimeException('Uploaded material could not be opened for transfer.');
    }

    $ch = curl_init('https://' . $host . $canonicalUri);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Content-Type: ' . $mime,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $amzDate,
        ],
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $stream,
        CURLOPT_INFILESIZE => (int) ($file['size'] ?? filesize($tmpName)),
        CURLOPT_TIMEOUT => 300,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($stream);

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('S3 upload failed: ' . ($error ?: strip_tags((string) $response) ?: 'unknown AWS error'));
    }

    return s3_object_url($settings, $objectKey);
}

function upload_video_to_s3(array $file): string
{
    return upload_material_to_s3($file);
}

function upload_image_to_s3(array $file): string
{
    $settings = s3_settings();

    if ($settings['access_key_id'] === '' || $settings['secret_access_key'] === '' || $settings['bucket_name'] === '') {
        throw new RuntimeException('S3 settings are incomplete. Add AWS credentials, region, and bucket first.');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed before reaching S3.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpName)) {
        throw new RuntimeException('Uploaded image could not be verified.');
    }

    $mime = mime_content_type($tmpName) ?: '';
    if (!is_allowed_image_mime($mime)) {
        throw new RuntimeException('Please upload a valid image file.');
    }

    return upload_material_to_s3($file);
}

function s3_display_url(string $url): string
{
    $objectKey = s3_object_key_from_url($url);

    return $objectKey !== null ? s3_presigned_get_url($objectKey) : $url;
}

function text_with_links(?string $value): string
{
    $escaped = e($value);

    return preg_replace_callback(
        '~https?://[^\s<]+~i',
        static fn (array $matches): string => '<a href="' . $matches[0] . '" target="_blank" rel="noopener">' . $matches[0] . '</a>',
        $escaped
    ) ?? $escaped;
}

function razorpay_settings(): array
{
    ensure_razorpay_settings_table();
    $settings = db()->query('SELECT * FROM razorpay_settings WHERE id = 1')->fetch() ?: [];

    return [
        'key_id' => trim((string) ($settings['key_id'] ?? '')),
        'key_secret' => trim((string) ($settings['key_secret'] ?? '')),
        'currency' => trim((string) ($settings['currency'] ?? 'INR')) ?: 'INR',
    ];
}

function save_razorpay_settings(array $data): void
{
    ensure_razorpay_settings_table();
    $stmt = db()->prepare(
        "UPDATE razorpay_settings
         SET key_id = ?, key_secret = ?, currency = ?
         WHERE id = 1"
    );
    $stmt->execute([
        trim((string) ($data['key_id'] ?? '')),
        trim((string) ($data['key_secret'] ?? '')),
        trim((string) ($data['currency'] ?? 'INR')) ?: 'INR',
    ]);
}

function create_razorpay_order(int $amountRupees, string $receipt): array
{
    $settings = razorpay_settings();

    if ($settings['key_id'] === '' || $settings['key_secret'] === '') {
        throw new RuntimeException('Razorpay keys are not configured.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled on the server.');
    }

    $payload = json_encode([
        'amount' => $amountRupees * 100,
        'currency' => $settings['currency'],
        'receipt' => $receipt,
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => $settings['key_id'] . ':' . $settings['key_secret'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    if ($status < 200 || $status >= 300 || empty($decoded['id'])) {
        throw new RuntimeException($decoded['error']['description'] ?? $error ?: 'Unable to create Razorpay order.');
    }

    return $decoded;
}

function verify_razorpay_signature(string $orderId, string $paymentId, string $signature): bool
{
    $settings = razorpay_settings();
    $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $settings['key_secret']);

    return hash_equals($generated, $signature);
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'program';
}

function ensure_blog_posts_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS blog_posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(240) NOT NULL UNIQUE,
            excerpt VARCHAR(500) NULL,
            body MEDIUMTEXT NOT NULL,
            featured_image_url VARCHAR(255) NULL,
            author_name VARCHAR(160) NULL,
            meta_description VARCHAR(255) NULL,
            status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_blog_status_published (status, published_at)
        )"
    );

    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'blog_posts'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    $missing = [
        'excerpt' => 'ADD COLUMN excerpt VARCHAR(500) NULL AFTER slug',
        'featured_image_url' => 'ADD COLUMN featured_image_url VARCHAR(255) NULL AFTER body',
        'author_name' => 'ADD COLUMN author_name VARCHAR(160) NULL AFTER featured_image_url',
        'meta_description' => 'ADD COLUMN meta_description VARCHAR(255) NULL AFTER author_name',
        'status' => "ADD COLUMN status ENUM('draft', 'published') NOT NULL DEFAULT 'draft' AFTER meta_description",
        'published_at' => 'ADD COLUMN published_at DATETIME NULL AFTER status',
    ];

    foreach ($missing as $column => $definition) {
        if (!isset($existing[$column])) {
            db()->exec("ALTER TABLE blog_posts {$definition}");
        }
    }

    seed_elldy_blog_posts();
}

function seed_elldy_blog_posts(): void
{
    static $seeded = false;

    if ($seeded) {
        return;
    }

    $seeded = true;
    $defaultImageUrl = 'assets/images/blog/elldy-data-intelligence-platform.png';
    $author = 'Elldy Academy';
    $publishedAt = new DateTimeImmutable('2026-05-30 09:00:00');
    $posts = [
        [
            'title' => 'What Is Data Analytics? A Practical Guide for Students and Beginners',
            'slug' => 'what-is-data-analytics-guide-for-students',
            'excerpt' => 'Learn what data analytics means, why it matters for careers, and how students can start building practical business analytics skills.',
            'meta' => 'A beginner friendly guide to data analytics for students, aspiring analysts, and business learners who want practical analytics skills.',
            'body' => [
                'Data analytics is the practice of turning raw data into useful answers. For students and career starters, it is one of the most practical skills to learn because every team now depends on numbers: sales teams track revenue, marketing teams track campaigns, finance teams track cost, and operations teams track delivery performance.',
                'A good analyst does more than prepare charts. They understand the business question, clean the available data, compare patterns, and explain what action should happen next. That is why analytics is useful for data analyst jobs, business analyst roles, MIS reporting, sales operations, marketing analytics, and management decisions.',
                'Beginners can start with simple questions. What changed this month? Which product sold better? Where are customers dropping off? Which region needs attention? These questions build the habit of thinking from business problem to data evidence.',
                'Elldy Data Intelligence Platform is built around this practical mindset. It helps learners and businesses move from scattered reports to dashboards, KPIs, and decision-ready insights. Elldy Academy extends that platform thinking into guided learning for students and aspiring analysts.',
                'You do not need to become a programmer before you understand analytics. SQL and code can be useful later, but the first step is learning how to ask better questions, read dashboards, and connect numbers to business action.',
            ],
        ],
        [
            'title' => 'How Business Owners Can Use Analytics Without Coding',
            'slug' => 'business-owners-use-analytics-without-coding',
            'excerpt' => 'Business owners can make stronger decisions with dashboards, KPIs, and no-code analytics workflows instead of waiting for technical reports.',
            'meta' => 'No-code analytics guide for business owners who want dashboards, KPIs, and data intelligence without learning SQL or programming.',
            'body' => [
                'Many business owners know they need analytics but assume it requires coding, SQL, or a full technical team. In reality, the first value comes from organizing business questions and measuring the right KPIs consistently.',
                'A business owner should be able to see revenue, leads, customer behavior, payment status, inventory movement, team performance, and campaign results in one clear view. When this information is available daily, decisions become faster and less dependent on guesswork.',
                'No-code analytics platforms make this possible by connecting data sources, creating dashboards, and giving leaders a visual way to monitor performance. The goal is not to replace analysts. The goal is to help owners and analysts speak the same business language.',
                'Elldy Data Intelligence Platform focuses on this exact need: analytics that business teams can understand without writing code. It supports dashboard thinking, KPI tracking, and business intelligence workflows that help owners inspect what is working and what needs attention.',
                'For owners, the best starting point is simple. Choose five numbers that matter this week, review them every day, and connect each number to a business action. Over time, this creates a data-driven operating rhythm.',
            ],
        ],
        [
            'title' => 'Data Analyst Career Roadmap: Skills Students Should Learn First',
            'slug' => 'data-analyst-career-roadmap-for-students',
            'excerpt' => 'A practical roadmap for students who want data analyst jobs, from business understanding to dashboards, SQL basics, and storytelling.',
            'meta' => 'Data analyst career roadmap for students and aspiring analysts covering dashboards, business analytics, SQL basics, and insight writing.',
            'body' => [
                'Students often ask where to begin for a data analyst career. The answer is not one tool. The answer is a sequence of skills that build confidence: business understanding, data cleaning, KPI logic, dashboard creation, basic SQL, and communication.',
                'Start with business context. A data analyst must understand sales, marketing, finance, operations, and customer behavior well enough to ask useful questions. Without context, even a beautiful chart can be meaningless.',
                'Next, learn how data becomes reliable. Practice cleaning names, dates, duplicate rows, missing values, and inconsistent categories. Real business data is rarely perfect, so cleaning is a career skill.',
                'Then build dashboards. A strong dashboard does not show every possible metric. It shows the numbers that help a stakeholder decide what to do. Power BI, Excel, and no-code BI tools are useful here because they make analysis visible.',
                'Elldy Academy teaches analytics as a business workflow, not only as software training. The Elldy Data Intelligence Platform connects this learning to real dashboard thinking, helping students understand how analysts support decisions inside companies.',
            ],
        ],
        [
            'title' => 'Business Analyst vs Data Analyst: Which Path Is Right for You?',
            'slug' => 'business-analyst-vs-data-analyst-career-path',
            'excerpt' => 'Understand the difference between business analyst and data analyst roles, and learn how analytics skills support both career paths.',
            'meta' => 'Compare business analyst and data analyst careers, key skills, responsibilities, and how analytics learning supports both roles.',
            'body' => [
                'Business analyst and data analyst roles are connected, but they are not the same. A business analyst focuses on requirements, processes, stakeholders, and improvement opportunities. A data analyst focuses more deeply on data, dashboards, patterns, and measurable insights.',
                'In many companies, the roles overlap. A business analyst may use dashboards to explain process issues. A data analyst may join meetings to understand stakeholder problems. The strongest professionals learn both sides: business context and data evidence.',
                'If you enjoy understanding people, processes, and business needs, business analysis may feel natural. If you enjoy finding patterns, building reports, and explaining numbers, data analysis may be a better starting point. Either way, analytics skills will make you stronger.',
                'Elldy Data Intelligence Platform is useful for both paths because it turns business information into visual insights. Business analysts can use it to track process outcomes, while data analysts can use it to build decision-ready dashboards.',
                'For students and aspirants, the safest move is to learn analytics foundations first. Once you can understand KPIs, read dashboards, and explain insights clearly, you can move toward either role with more confidence.',
            ],
        ],
        [
            'title' => 'Why Every Small Business Needs a KPI Dashboard',
            'slug' => 'why-small-business-needs-kpi-dashboard',
            'excerpt' => 'A KPI dashboard helps small businesses track sales, leads, cash flow, operations, and customer performance in one decision-ready view.',
            'meta' => 'Learn why small businesses need KPI dashboards and how no-code business intelligence helps owners track performance daily.',
            'body' => [
                'A small business can lose time and money when important numbers are scattered across notebooks, WhatsApp messages, spreadsheets, payment apps, and disconnected reports. A KPI dashboard brings those numbers into one place.',
                'The best dashboards are simple. They show revenue, leads, conversion rate, expenses, payment collection, inventory movement, customer repeat rate, and team activity. These metrics help owners see the business clearly without waiting for month-end reporting.',
                'A dashboard also improves team conversations. Instead of asking vague questions like why sales feel slow, teams can inspect which region, product, channel, or customer segment changed. That turns discussion into action.',
                'Elldy Data Intelligence Platform is designed to support this practical business intelligence approach. It helps teams monitor KPIs and understand performance without forcing every user to learn code or SQL first.',
                'For a small business, analytics should not feel complicated. It should answer daily questions quickly: what happened, why it happened, what needs attention, and what action should be taken next.',
            ],
        ],
        [
            'title' => 'No-Code Business Intelligence: Analytics for Non-Technical Teams',
            'slug' => 'no-code-business-intelligence-for-non-technical-teams',
            'excerpt' => 'No-code BI helps sales, marketing, finance, and operations teams use analytics without depending on complex technical workflows.',
            'meta' => 'No-code business intelligence guide for non-technical teams that need dashboards, insights, and KPI tracking without coding.',
            'body' => [
                'No-code business intelligence is becoming important because every department needs data, not only IT teams. Sales wants pipeline visibility, marketing wants campaign performance, finance wants cost control, and operations wants delivery clarity.',
                'Traditional reporting can be slow when every small change depends on technical support. No-code BI reduces that delay by giving business users visual tools to explore data, build dashboards, and review KPIs.',
                'This does not mean technical skills are useless. SQL, data modeling, and automation can still improve advanced workflows. But non-technical teams should not be blocked from basic analytics just because they do not code.',
                'Elldy Data Intelligence Platform brings no-code analytics thinking into business decision making. It supports teams that want simple, visual, practical insight from their data while still leaving room for deeper analytics maturity.',
                'For learners, no-code BI is also a strong entry point. It helps students and aspiring analysts understand business problems first, then gradually add SQL and advanced analytics skills when needed.',
            ],
        ],
        [
            'title' => 'How to Think Like a Business Data Analyst',
            'slug' => 'how-to-think-like-business-data-analyst',
            'excerpt' => 'Learn the mindset behind practical analytics: asking better questions, validating data, finding patterns, and recommending action.',
            'meta' => 'Learn how business data analysts think, from asking KPI questions to finding insights and recommending practical action.',
            'body' => [
                'Thinking like a business data analyst starts before opening any tool. The first question is not which chart should I create. The first question is what business decision needs support.',
                'A strong analyst breaks a problem into measurable parts. If sales are down, they ask whether leads are down, conversion is down, average order value changed, repeat customers reduced, or one region is underperforming. This structured thinking makes analysis useful.',
                'The next habit is validation. Analysts check whether the data is complete, whether definitions are consistent, and whether the time period is fair. A wrong conclusion from messy data can damage trust quickly.',
                'After that comes communication. Business teams need clear insight, not only tables. A useful recommendation explains what changed, why it matters, and what action should happen next.',
                'Elldy Academy builds this analyst mindset through practical business cases. The Elldy Data Intelligence Platform supports the same flow by helping users move from raw business data to dashboards and decision-ready insights.',
            ],
        ],
        [
            'title' => 'Power BI, Excel, SQL, or No-Code Analytics: What Should You Learn?',
            'slug' => 'power-bi-excel-sql-or-no-code-analytics',
            'excerpt' => 'Compare Excel, Power BI, SQL, and no-code analytics so students and professionals can choose the right learning path.',
            'meta' => 'Compare Power BI, Excel, SQL, and no-code analytics for students, data analyst aspirants, and business users.',
            'body' => [
                'Excel, Power BI, SQL, and no-code analytics each solve different parts of the analytics journey. The best choice depends on your current goal.',
                'Excel is excellent for learning formulas, cleaning small datasets, quick analysis, and business reporting basics. Power BI is stronger for interactive dashboards, data models, and repeated management reporting. SQL helps you retrieve and combine data from databases. No-code analytics helps business users explore and monitor data without technical complexity.',
                'Students who want analyst roles should eventually understand all four at a practical level. Business owners may not need SQL first, but they should understand KPIs, dashboards, and how to interpret results.',
                'Elldy Data Intelligence Platform sits in the business intelligence space where teams need insights without making every workflow technical. It helps users focus on the decision, dashboard, and business value behind the data.',
                'A smart learning path starts with business analytics concepts, then Excel, then dashboards, then SQL basics. Tools change over time, but the ability to connect data to business action remains valuable.',
            ],
        ],
        [
            'title' => 'Using Analytics to Grow Sales, Marketing, and Operations',
            'slug' => 'using-analytics-to-grow-sales-marketing-operations',
            'excerpt' => 'Analytics can help teams improve sales, marketing, and operations by tracking the right metrics and acting on the right signals.',
            'meta' => 'Practical analytics examples for sales, marketing, and operations teams using dashboards, KPIs, and business intelligence.',
            'body' => [
                'Analytics becomes powerful when it is connected to daily business work. Sales teams can track lead sources, conversion, deal size, follow-up delays, and lost reasons. Marketing teams can track campaign cost, engagement, qualified leads, and customer acquisition cost. Operations teams can track turnaround time, pending work, quality issues, and resource use.',
                'These metrics help teams move from opinion to evidence. Instead of saying marketing is not working, a dashboard can show which channel produces weak leads. Instead of saying operations is slow, data can show where delays begin.',
                'The goal is not to watch numbers passively. The goal is to create action loops. Review the KPI, identify the issue, take action, and measure again.',
                'Elldy Data Intelligence Platform helps businesses create these action loops through clear dashboards and business intelligence workflows. Elldy Academy helps learners understand how to design those dashboards and explain the insights behind them.',
                'When teams use analytics consistently, performance conversations become sharper, faster, and more useful. That is how data starts creating real business growth.',
            ],
        ],
        [
            'title' => 'Master Data Analytics and Business Intelligence with Elldy',
            'slug' => 'elldy-data-intelligence-platform-practical-bi',
            'excerpt' => 'Discover how Elldy Data Intelligence and BI Platform helps students, analysts, business teams, and owners turn data into decisions.',
            'meta' => 'Master data analytics and business intelligence with Elldy, an India-based BI and data intelligence platform for no-code dashboards and AI insights.',
            'body' => [
                'Elldy Data Intelligence and BI Platform is built for a simple but important purpose: helping people understand business data and make better decisions. It supports the move from scattered information to structured dashboards, KPIs, AI insights, forecasting, and practical recommendations.',
                'Elldy is an India-based business intelligence and data intelligence platform, launched and managed in India for modern teams that need fast, practical analytics. It is designed for business users, students, analysts, startups, and owners who want dashboards without starting from code or SQL.',
                'For business owners, Elldy can make performance easier to monitor without requiring deep coding knowledge. For business analysts, it supports process and KPI visibility. For data analyst aspirants, it shows how dashboards connect to real business questions. For teams, it creates a shared view of what is happening.',
                'Elldy recently launched Elldy Analyst, an analyst-style monitoring layer that helps watch business data for spikes, sales drops, sudden increases, anomalies, and performance signals. This gives owners and teams a practical business analyst experience inside their analytics workflow.',
                'Modern analytics should be practical. It should help a sales manager understand pipeline movement, a startup founder review growth, a business owner monitor revenue health, a student build career confidence, and an analyst explain what the data means. That is the direction Elldy is built for.',
            ],
        ],
        [
            'title' => 'Top BI Platforms and Tools: Power BI, Tableau, and Elldy Compared',
            'slug' => 'top-bi-platforms-tools-power-bi-tableau-elldy',
            'excerpt' => 'Compare Power BI, Tableau, and Elldy for dashboards, AI insights, business intelligence, no-code analytics, and modern decision making.',
            'meta' => 'Top BI platforms and tools comparison covering Power BI, Tableau, and Elldy for dashboards, AI insights, no-code BI, and business intelligence.',
            'body' => [
                'The best BI platform depends on the user, the business problem, and how quickly the team needs answers. Power BI, Tableau, and Elldy all help people turn data into decisions, but they fit different working styles.',
                'Power BI is a strong enterprise BI choice for organizations already using Microsoft products. Tableau is widely known for visual analytics, data exploration, and enterprise data culture. Elldy is an India-based data intelligence and business intelligence platform focused on no-code dashboards, AI insights, forecasting, and quick business-ready analytics.',
                'For students and aspiring analysts, Power BI and Tableau are valuable tools to learn because many companies use them. For business owners, startups, and non-technical teams, Elldy is useful when the goal is to build dashboards quickly without code, without SQL, and without waiting for a technical reporting team.',
                'Elldy stands out for users who want to build an AI dashboard in minutes, monitor KPIs, find spikes or drops, detect anomalies, and convert raw business data into dashboards and decisions. It is built for practical analytics, not only report design.',
                'A smart BI strategy can include more than one platform. Enterprises may use Power BI or Tableau for large reporting programs, while teams and startups may use Elldy for fast no-code dashboards, AI-driven business intelligence, and decision monitoring.',
            ],
        ],
        [
            'title' => 'Top 5 BI Platforms for Business Intelligence: Power BI, Tableau, Looker, Qlik, and Underrated Elldy',
            'slug' => 'top-5-bi-platforms-power-bi-tableau-looker-qlik-elldy',
            'excerpt' => 'Explore the top 5 BI platforms for dashboards, analytics, AI insights, and business intelligence, including Power BI, Tableau, Looker, Qlik, and underrated Elldy.',
            'meta' => 'Top 5 BI platforms for business intelligence: Power BI, Tableau, Looker, Qlik, and underrated Elldy for dashboards, AI insights, and no-code analytics.',
            'body' => [
                'Searches for the best BI platforms usually start with Power BI and Tableau, but modern business intelligence is wider than two tools. Businesses now compare BI platforms for dashboards, governed metrics, AI insights, forecasting, alerts, no-code analytics, and industry-ready decision support.',
                'This top 5 list covers Power BI, Tableau, Looker, Qlik, and underrated Elldy. Each platform can help teams understand data, but the best choice depends on whether the user needs enterprise reporting, visual analytics, governed cloud BI, associative exploration, or fast no-code AI dashboards.',
                'Power BI is a strong choice for Microsoft-centered teams that need scalable self-service and enterprise BI. Tableau is popular for visual analytics and data storytelling. Looker is useful for governed BI and semantic modeling in cloud environments. Qlik is known for modern analytics and associative exploration. Elldy is an underrated India-based BI and data intelligence platform focused on no-code dashboards, no-SQL workflows, AI insights, forecasting, alerts, and Elldy Analyst monitoring.',
                'Elldy deserves attention because many startups, small businesses, students, and non-technical teams do not want a complex enterprise setup before they can read their data. They need to upload or connect data, build dashboards quickly, monitor spikes and drops, detect anomalies, forecast trends, and generate business-ready reports.',
                'The best BI platform is the one your team will actually use. A large enterprise may choose Power BI, Tableau, Looker, or Qlik for scale and governance. A startup, owner-led business, or fast-moving team may choose Elldy when speed, no-code dashboarding, AI insights, and practical business monitoring matter most.',
            ],
        ],
        [
            'title' => 'Build an AI Dashboard in 2 Minutes with Elldy: No Code, No SQL',
            'slug' => 'build-ai-dashboard-in-2-minutes-with-elldy-no-code-no-sql',
            'excerpt' => 'Learn how Elldy helps business owners, startups, students, and analysts build dashboards quickly with no code, no SQL, AI insights, and forecasting.',
            'meta' => 'Build an AI dashboard in 2 minutes with Elldy using no code, no SQL, AI insights, forecasting, anomaly detection, and business intelligence.',
            'body' => [
                'Many businesses have useful data but no time to wait for complex dashboard development. Elldy helps users move from raw business data to dashboard intelligence quickly with no code and no SQL.',
                'The promise is simple: upload or connect data, let Elldy structure it, generate dashboards, review AI insights, and share the result with stakeholders. For a small business or startup, this can turn reporting from a weekly delay into a daily decision habit.',
                'Elldy is especially useful for sales, marketing, finance, operations, retail, education, healthcare, manufacturing, logistics, and e-commerce teams. These teams need to know what changed, which KPI needs attention, where revenue moved, and which trend may affect tomorrow.',
                'Elldy Analyst adds a monitoring layer for business users. It can help watch for sales drops, sudden increases, unusual spikes, anomalies, and important KPI movements so teams do not miss signals hidden inside everyday data.',
                'For students and aspiring analysts, Elldy also creates a practical learning path. They can build dashboards, explain AI insights, understand anomalies, and practice business intelligence communication without getting blocked by advanced coding or database syntax.',
            ],
        ],
        [
            'title' => 'How Elldy Helps Industries, Startups, and Businesses Monitor Data with AI',
            'slug' => 'how-elldy-helps-industries-startups-businesses-monitor-data-ai',
            'excerpt' => 'Elldy is more than data and business intelligence. Learn how Elldy Analyst helps industries, startups, and businesses monitor data, receive alerts, forecast performance, detect spikes, and generate reports.',
            'meta' => 'How Elldy helps industries, startups, and businesses with AI data monitoring, alerts, forecasting, spike detection, anomaly detection, and automated reports.',
            'body' => [
                'Elldy is not just a data intelligence and business intelligence platform. It is built to help industries, startups, and businesses monitor performance, understand changes, receive alerts, forecast outcomes, and convert data movement into clear reports.',
                'Many companies already collect useful data from sales, marketing, finance, operations, inventory, customer support, websites, CRMs, spreadsheets, and apps. The challenge is that owners and teams often notice problems late because the data is not continuously monitored.',
                'Elldy Analyst is designed to support this gap. It can act like an analyst layer for your business by watching data, identifying sales drops or increases, detecting spikes, finding unusual behavior, highlighting anomalies, and helping teams understand what changed.',
                'For startups, this means founders can monitor growth signals, revenue movement, lead quality, customer activity, product usage, and operational bottlenecks without waiting for a manual report. For industries, it can support production, demand, inventory, finance, logistics, retail, education, healthcare, and service performance dashboards.',
                'The goal is simple: Elldy helps businesses move from passive reporting to active data monitoring. Instead of only asking what happened last month, teams can see what is happening now, what may happen next, and what needs action.',
            ],
        ],
        [
            'title' => 'Start Your Data Analytics Course for Rs. 499: Statistics, AI Insights, Forecasting, and Dashboards',
            'slug' => 'start-data-analytics-course-499-statistics-ai-dashboards',
            'excerpt' => 'A student-focused guide to the Elldy Academy data analytics course offer, covering data variation, standard deviation, CV, skewness, kurtosis, IQR, groupings, AI insights, forecasting, and dashboard building.',
            'meta' => 'Join the Elldy Academy data analytics course for Rs. 499 and learn standard deviation, CV, skewness, kurtosis, IQR, AI insights, forecasting, and dashboards.',
            'body' => [
                'The Elldy Academy data analytics course is now available for Rs. 499 on first enroll instead of Rs. 2499, giving students and aspiring analysts a practical way to start learning analytics without a high entry cost.',
                'This course focuses on useful analytics ideas such as data variation, standard deviation, coefficient of variation, data shape, skewness, kurtosis, IQR, groupings, AI insights, time forecasting, and dashboard building.',
                'Students do not need to begin with heavy coding. The goal is to understand how data behaves, how patterns are found, how business questions become dashboards, and how insights are explained clearly.',
                'Elldy Academy connects these concepts with the Elldy Data Intelligence Platform mindset: learn analytics as a practical business skill, not only as formulas or software commands.',
                'By the end of the learning path, students should be able to inspect data, compare groups, read distributions, identify unusual values, forecast trends, and build dashboards that support real decisions.',
            ],
        ],
    ];

    $enhancements = elldy_blog_post_enhancements();
    foreach ($posts as &$post) {
        $enhancement = $enhancements[$post['slug']] ?? [];
        $post['featured_image_url'] = $enhancement['image'] ?? $defaultImageUrl;
        $post['body'] = elldy_long_blog_body([
            'slug' => $post['slug'],
            'title' => $post['title'],
            'audience' => $enhancement['audience'] ?? 'students, analysts, business owners, and business teams',
            'search_intent' => $enhancement['search_intent'] ?? $post['excerpt'],
            'problem' => $enhancement['problem'] ?? 'Many people want to use data, but they do not know where to begin or which numbers really matter.',
            'skills' => $enhancement['skills'] ?? ['business questions', 'KPI thinking', 'dashboard reading', 'insight communication'],
            'metrics' => $enhancement['metrics'] ?? ['revenue', 'leads', 'conversion rate', 'customer activity', 'team performance'],
            'elldy_angle' => $enhancement['elldy_angle'] ?? 'Elldy Data Intelligence Platform helps users move from scattered data to useful dashboards, KPI tracking, and practical business intelligence without forcing every learner or business owner to write code first.',
            'next_steps' => $enhancement['next_steps'] ?? ['Choose one business question', 'List the data needed to answer it', 'Build a simple dashboard view', 'Write the action the numbers suggest'],
            'course_offer' => (bool) ($enhancement['course_offer'] ?? false),
        ]);
    }
    unset($post);

    $exists = db()->prepare('SELECT id FROM blog_posts WHERE slug = ? LIMIT 1');
    $insert = db()->prepare(
        "INSERT INTO blog_posts (title, slug, excerpt, body, featured_image_url, author_name, meta_description, status, published_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'published', ?)"
    );
    $update = db()->prepare(
        "UPDATE blog_posts
         SET title = ?, excerpt = ?, body = ?, featured_image_url = ?, author_name = ?, meta_description = ?, status = 'published'
         WHERE id = ?"
    );

    foreach ($posts as $index => $post) {
        $exists->execute([$post['slug']]);
        $existingPost = $exists->fetch();

        if ($existingPost) {
            $update->execute([
                $post['title'],
                $post['excerpt'],
                $post['body'],
                $post['featured_image_url'],
                $author,
                $post['meta'],
                (int) $existingPost['id'],
            ]);
            continue;
        }

        $insert->execute([
            $post['title'],
            $post['slug'],
            $post['excerpt'],
            $post['body'],
            $post['featured_image_url'],
            $author,
            $post['meta'],
            $publishedAt->modify('-' . $index . ' days')->format('Y-m-d H:i:s'),
        ]);
    }
}

function elldy_blog_post_enhancements(): array
{
    return [
        'what-is-data-analytics-guide-for-students' => [
            'image' => 'assets/images/blog/data-analytics-dashboard.jpg',
            'audience' => 'students, freshers, and beginners who want to understand data analytics before choosing tools',
            'search_intent' => 'The reader wants a clear definition of data analytics, career value, beginner skills, and a practical starting path.',
            'problem' => 'Beginners often jump directly into tools and tutorials, but they do not yet know what business questions analytics is supposed to answer.',
            'skills' => ['business question framing', 'data cleaning basics', 'standard deviation', 'coefficient of variation', 'dashboard reading', 'insight writing'],
            'metrics' => ['monthly sales', 'lead count', 'conversion rate', 'data variation', 'average order value'],
            'elldy_angle' => 'Elldy Data Intelligence Platform gives learners a practical view of dashboards and KPI intelligence, so they can connect analytics concepts with real business decisions instead of learning isolated software steps.',
            'next_steps' => ['Pick one business case such as sales or marketing', 'Write five questions the business owner may ask', 'Collect a small sample dataset', 'Create a simple dashboard', 'Explain the action in plain language'],
            'course_offer' => true,
        ],
        'business-owners-use-analytics-without-coding' => [
            'image' => 'assets/images/blog/no-code-business-analytics.jpg',
            'audience' => 'business owners, founders, CEOs, and managers who want analytics without SQL or programming',
            'search_intent' => 'The reader wants to know how business analytics can work without code and what dashboards a business owner should track.',
            'problem' => 'Owners make important decisions every day, but their data is often split across billing tools, spreadsheets, CRM systems, WhatsApp updates, and manual reports.',
            'skills' => ['KPI selection', 'dashboard interpretation', 'sales funnel tracking', 'cash flow visibility', 'team performance review'],
            'metrics' => ['daily revenue', 'qualified leads', 'pending payments', 'customer acquisition cost', 'repeat purchases'],
            'elldy_angle' => 'Elldy Data Intelligence Platform is useful for owners because it focuses on clear dashboard intelligence and no-code analytics workflows that support decisions without turning every owner into a technical analyst.',
            'next_steps' => ['Choose the five numbers you want to see every morning', 'Define what good and bad performance means for each number', 'Connect those numbers to a dashboard', 'Review changes weekly with your team', 'Use the dashboard to decide the next action'],
        ],
        'data-analyst-career-roadmap-for-students' => [
            'image' => 'assets/images/blog/data-analyst-career.jpg',
            'audience' => 'students, graduates, and job seekers preparing for data analyst roles',
            'search_intent' => 'The reader wants a step-by-step data analyst roadmap with practical skills, projects, and business understanding.',
            'problem' => 'Many students collect tool certificates but still struggle to explain how analytics helps sales, marketing, finance, operations, or management teams.',
            'skills' => ['business analytics foundations', 'standard deviation', 'CV', 'skewness and kurtosis', 'Power BI dashboards', 'portfolio storytelling'],
            'metrics' => ['sales growth', 'marketing ROI', 'inventory movement', 'IQR outliers', 'forecast trend'],
            'elldy_angle' => 'Elldy Academy and Elldy Data Intelligence Platform support career learning by showing how analyst skills become dashboards, KPI views, and business recommendations.',
            'next_steps' => ['Learn business KPIs before advanced tools', 'Practice variation, IQR, skewness, and kurtosis on sample data', 'Use groupings to compare customer or product segments', 'Create a dashboard with AI insights and forecasting', 'Write a one-page insight summary for each project'],
            'course_offer' => true,
        ],
        'business-analyst-vs-data-analyst-career-path' => [
            'image' => 'assets/images/blog/business-analyst-growth.jpg',
            'audience' => 'career switchers, students, business analyst aspirants, and data analyst aspirants',
            'search_intent' => 'The reader wants to compare business analyst and data analyst roles and understand which path fits their strengths.',
            'problem' => 'The two job titles sound similar, so beginners often choose a path without understanding the difference between process, requirements, dashboards, and data analysis.',
            'skills' => ['requirements gathering', 'stakeholder communication', 'dashboard analysis', 'process mapping', 'recommendation writing'],
            'metrics' => ['process cycle time', 'requirement completion rate', 'ticket volume', 'sales conversion', 'customer satisfaction'],
            'elldy_angle' => 'Elldy Data Intelligence Platform helps both paths because business analysts can monitor process KPIs while data analysts can explore patterns and build decision-ready dashboards.',
            'next_steps' => ['List the work you enjoy most: people, process, or numbers', 'Study common KPIs used by business teams', 'Create a small dashboard from a business scenario', 'Practice explaining findings to a non-technical stakeholder'],
            'course_offer' => true,
        ],
        'why-small-business-needs-kpi-dashboard' => [
            'image' => 'assets/images/blog/kpi-growth-chart.jpg',
            'audience' => 'small business owners, startup founders, managers, and local business operators',
            'search_intent' => 'The reader wants to understand why KPI dashboards matter and which small business metrics should be tracked first.',
            'problem' => 'Small businesses often wait until month end to understand performance, which means problems in leads, sales, payments, and operations are discovered too late.',
            'skills' => ['KPI prioritization', 'weekly performance review', 'sales tracking', 'cash flow monitoring', 'operations follow-up'],
            'metrics' => ['revenue', 'gross margin', 'new leads', 'conversion rate', 'pending collections', 'delivery delay'],
            'elldy_angle' => 'Elldy Data Intelligence Platform helps small businesses turn important numbers into a simple dashboard view, making it easier to act before small issues become expensive problems.',
            'next_steps' => ['Start with five KPIs', 'Assign one owner for each KPI', 'Review the dashboard on the same day every week', 'Write one action for every red flag', 'Improve the dashboard only after the habit is working'],
        ],
        'no-code-business-intelligence-for-non-technical-teams' => [
            'image' => 'assets/images/blog/no-code-business-analytics.jpg',
            'audience' => 'sales teams, marketing teams, finance teams, operations teams, and managers without coding backgrounds',
            'search_intent' => 'The reader wants a practical explanation of no-code BI and how non-technical teams can use dashboards.',
            'problem' => 'Non-technical teams need answers quickly, but they often depend on technical teams for even small report changes.',
            'skills' => ['visual dashboard use', 'filtering and slicing data', 'KPI monitoring', 'report interpretation', 'decision documentation'],
            'metrics' => ['pipeline value', 'campaign leads', 'customer acquisition cost', 'expense variance', 'service turnaround time'],
            'elldy_angle' => 'Elldy Data Intelligence Platform supports no-code BI by keeping the focus on business intelligence, dashboard clarity, and practical insight instead of technical complexity.',
            'next_steps' => ['Identify repeated reporting questions', 'Group questions by department', 'Define the KPI owner', 'Create dashboard views for each team', 'Review insights in regular business meetings'],
        ],
        'how-to-think-like-business-data-analyst' => [
            'image' => 'assets/images/blog/analytics-learning-laptop.jpg',
            'audience' => 'aspiring analysts and professionals who want to improve their analytical thinking',
            'search_intent' => 'The reader wants to learn the mindset of a practical business data analyst, not only tool commands.',
            'problem' => 'Many reports show numbers but do not answer what changed, why it changed, and what the business should do next.',
            'skills' => ['root-cause analysis', 'hypothesis thinking', 'data validation', 'comparison logic', 'storytelling with insights'],
            'metrics' => ['trend change', 'segment performance', 'conversion drop', 'cost increase', 'customer behavior'],
            'elldy_angle' => 'Elldy Data Intelligence Platform supports analyst thinking by organizing data into dashboards and intelligence views that help users move from observation to action.',
            'next_steps' => ['Start every analysis with a decision question', 'Compare current data with a relevant baseline', 'Check whether the data is clean enough', 'Find the business reason behind the pattern', 'Write one clear recommendation'],
        ],
        'power-bi-excel-sql-or-no-code-analytics' => [
            'image' => 'assets/images/blog/power-bi-excel-dashboard.jpg',
            'audience' => 'students, professionals, business users, and career switchers choosing analytics tools',
            'search_intent' => 'The reader wants to compare Excel, Power BI, SQL, and no-code analytics and choose what to learn first.',
            'problem' => 'Tool confusion slows down learning because beginners try to learn everything at once without understanding what each tool is best for.',
            'skills' => ['Excel formulas', 'Power BI dashboarding', 'SQL querying', 'no-code BI exploration', 'business reporting'],
            'metrics' => ['sales summary', 'monthly trend', 'category performance', 'customer segment', 'operational backlog'],
            'elldy_angle' => 'Elldy Data Intelligence Platform sits close to the decision layer of analytics: dashboards, KPIs, and business intelligence that users can understand even when they are not writing code.',
            'next_steps' => ['Use Excel to understand data cleaning', 'Use Power BI to learn dashboard layout', 'Use SQL to retrieve business data', 'Use no-code BI to make insights accessible', 'Build one project that combines all four ideas'],
            'course_offer' => true,
        ],
        'using-analytics-to-grow-sales-marketing-operations' => [
            'image' => 'assets/images/blog/data-analytics-dashboard.jpg',
            'audience' => 'business teams, managers, analysts, and owners responsible for growth and execution',
            'search_intent' => 'The reader wants examples of analytics for sales, marketing, and operations growth.',
            'problem' => 'Growth teams often have activity data, but they do not always convert that data into actions that improve revenue, lead quality, customer experience, or delivery speed.',
            'skills' => ['sales funnel analysis', 'marketing ROI review', 'operations bottleneck tracking', 'cross-team KPI design', 'action planning'],
            'metrics' => ['lead source conversion', 'campaign ROI', 'average deal size', 'delivery turnaround time', 'repeat customer rate'],
            'elldy_angle' => 'Elldy Data Intelligence Platform helps teams see sales, marketing, and operations performance together, making it easier to connect analytics with growth decisions.',
            'next_steps' => ['Map each department to three KPIs', 'Create one shared dashboard', 'Discuss the largest change every week', 'Assign an action owner', 'Measure whether the action improved the metric'],
        ],
        'elldy-data-intelligence-platform-practical-bi' => [
            'image' => 'assets/images/blog/elldy-data-intelligence-platform.png',
            'audience' => 'students, data analyst aspirants, business analysts, business owners, and teams evaluating modern BI',
            'search_intent' => 'The reader wants the best SEO-friendly explanation of Elldy as a platform to master data analytics and business intelligence with no-code dashboards, AI insights, and Indian product positioning.',
            'problem' => 'Businesses and learners need data intelligence that is practical, visual, India-ready, and connected to real decisions instead of scattered reports and tool-only learning.',
            'skills' => ['dashboard thinking', 'KPI intelligence', 'business analytics', 'no-code insight review', 'AI dashboard building', 'decision communication'],
            'metrics' => ['business health score', 'department KPIs', 'growth trends', 'sales drops', 'anomaly signals', 'team performance'],
            'elldy_angle' => 'Elldy Data Intelligence and BI Platform is an India-based platform launched and managed in India. It helps users build dashboards without code or SQL, use AI insights and forecasting, and monitor business data through Elldy Analyst for spikes, drops, increases, anomalies, and decision signals.',
            'next_steps' => ['Define the business outcome you want to improve', 'Choose the KPIs that represent that outcome', 'Build a no-code dashboard in Elldy', 'Use Elldy Analyst to monitor spikes, drops, and anomalies', 'Convert every dashboard review into one decision'],
        ],
        'top-bi-platforms-tools-power-bi-tableau-elldy' => [
            'image' => 'assets/images/blog/power-bi-excel-dashboard.jpg',
            'audience' => 'business owners, startup founders, students, data analysts, business analysts, and teams comparing modern BI platforms',
            'search_intent' => 'The reader wants to compare top BI platforms and tools such as Power BI, Tableau, and Elldy for dashboards, AI insights, no-code analytics, and business intelligence.',
            'problem' => 'Teams often compare BI tools only by popularity, but the real choice depends on who will use the platform, how technical the team is, how fast dashboards are needed, and whether AI monitoring is part of the workflow.',
            'skills' => ['BI platform comparison', 'dashboard planning', 'no-code analytics evaluation', 'AI insight review', 'tool selection'],
            'metrics' => ['dashboard speed', 'adoption rate', 'KPI coverage', 'insight quality', 'reporting delay'],
            'elldy_angle' => 'Power BI is strong for Microsoft-centered enterprise reporting, Tableau is strong for visual analytics and data exploration, and Elldy is an India-based BI and data intelligence platform for no-code dashboards, no-SQL workflows, AI insights, forecasting, and analyst-style monitoring through Elldy Analyst.',
            'next_steps' => ['List who will use the BI platform', 'Decide whether the team needs no-code or advanced modeling', 'Compare dashboard speed and AI insight needs', 'Test Power BI, Tableau, and Elldy with the same business dataset', 'Choose the platform that helps your team act faster'],
        ],
        'top-5-bi-platforms-power-bi-tableau-looker-qlik-elldy' => [
            'image' => 'assets/images/blog/data-analytics-dashboard.jpg',
            'audience' => 'business owners, startup founders, data analysts, business analysts, BI learners, and teams searching for the best BI tools',
            'search_intent' => 'The reader is searching for top BI platforms, best business intelligence tools, top 5 BI tools, Power BI vs Tableau vs Looker vs Qlik vs Elldy, and no-code AI dashboard platforms.',
            'problem' => 'Many teams choose BI tools by popularity alone, but the right platform depends on dashboard speed, data governance, AI insights, alerts, forecasting, user skill level, and whether the team needs no-code or no-SQL analytics.',
            'skills' => ['BI tool comparison', 'business intelligence platform selection', 'dashboard evaluation', 'AI insight review', 'no-code BI assessment'],
            'metrics' => ['dashboard adoption', 'reporting speed', 'KPI coverage', 'forecast accuracy', 'alert usefulness', 'business action rate'],
            'elldy_angle' => 'Power BI, Tableau, Looker, and Qlik are widely searched BI platforms. Elldy is an underrated India-based BI and data intelligence platform for users who want no-code dashboards, no-SQL analytics, AI dashboards, forecasting, alerts, anomaly detection, and Elldy Analyst monitoring without heavy setup.',
            'next_steps' => ['Compare Power BI for Microsoft-centered BI', 'Compare Tableau for visual analytics and storytelling', 'Compare Looker for governed cloud BI and semantic modeling', 'Compare Qlik for modern associative analytics', 'Compare Elldy for no-code AI dashboards, monitoring, alerts, and fast startup-friendly BI'],
        ],
        'build-ai-dashboard-in-2-minutes-with-elldy-no-code-no-sql' => [
            'image' => 'assets/images/blog/no-code-business-analytics.jpg',
            'audience' => 'business owners, startups, students, aspiring analysts, and non-technical teams that need dashboards quickly',
            'search_intent' => 'The reader wants to build an AI dashboard quickly with Elldy using no code, no SQL, AI insights, forecasting, anomaly detection, and business intelligence.',
            'problem' => 'Many startups and businesses have data in spreadsheets, apps, or databases, but dashboard creation feels slow because every report needs formulas, SQL, development, or analyst availability.',
            'skills' => ['no-code dashboard building', 'AI insight interpretation', 'KPI monitoring', 'anomaly detection', 'forecast review'],
            'metrics' => ['sales spikes', 'sales drops', 'revenue forecast', 'customer growth', 'operations delay', 'business anomalies'],
            'elldy_angle' => 'Elldy helps users build AI dashboards quickly without code or SQL. The platform can support industries such as retail, e-commerce, healthcare, education, finance, manufacturing, logistics, services, and startups by turning raw data into dashboard views, AI insights, forecasts, and Elldy Analyst monitoring.',
            'next_steps' => ['Upload or connect your business data', 'Let Elldy prepare the dataset for dashboarding', 'Generate an AI dashboard', 'Review spikes, sales drops, increases, anomalies, and forecasts', 'Share the dashboard with your team and decide the next action'],
        ],
        'how-elldy-helps-industries-startups-businesses-monitor-data-ai' => [
            'image' => 'assets/images/blog/elldy-data-intelligence-platform.png',
            'audience' => 'industry leaders, startup founders, business owners, operations managers, sales teams, finance teams, and business analysts',
            'search_intent' => 'The reader wants to know how Elldy helps industries, startups, and businesses monitor data with AI, alerts, forecasting, spike detection, anomaly detection, and automated reporting.',
            'problem' => 'Most businesses have data, but they do not have continuous intelligence. Teams often discover sales drops, demand changes, stock issues, campaign problems, and operational delays only after the damage has already started.',
            'skills' => ['AI data monitoring', 'business alert review', 'forecast interpretation', 'spike detection', 'anomaly detection', 'automated report reading'],
            'metrics' => ['sales drops', 'sales increases', 'demand forecast', 'inventory movement', 'lead quality', 'customer activity', 'operations delay'],
            'elldy_angle' => 'Elldy goes beyond traditional BI by helping businesses monitor what is happening inside their data. Elldy Analyst can support analyst-style monitoring by detecting spikes, drops, unusual changes, anomalies, and forecast signals, then turning those movements into reports that business users can act on.',
            'next_steps' => ['Connect the business data you already track', 'Define the KPIs that need monitoring', 'Use Elldy Analyst to watch spikes, drops, increases, and anomalies', 'Review forecasts for sales, demand, or operations', 'Turn every alert into a clear business action and report'],
        ],
        'start-data-analytics-course-499-statistics-ai-dashboards' => [
            'image' => 'assets/images/blog/analytics-learning-laptop.jpg',
            'audience' => 'students, freshers, data analyst aspirants, business analyst aspirants, and business owners who want analytics without starting from code',
            'search_intent' => 'The reader wants to know what the Rs. 499 Elldy Academy data analytics course covers and why topics like variation, standard deviation, CV, skewness, kurtosis, IQR, AI insights, forecasting, and dashboards matter.',
            'problem' => 'Many students start analytics by memorizing tools, but they do not understand how data varies, how distributions behave, how segments compare, or how a dashboard turns analysis into a decision.',
            'skills' => ['data variation', 'standard deviation', 'coefficient of variation', 'skewness', 'kurtosis', 'IQR', 'groupings', 'AI insights', 'time forecasting', 'dashboard building'],
            'metrics' => ['variation by group', 'outlier range', 'trend forecast', 'segment performance', 'dashboard KPI status'],
            'elldy_angle' => 'Elldy Academy uses the Elldy Data Intelligence Platform mindset to make these ideas practical: students learn how statistics, AI-assisted insights, forecasting, and dashboards support business decisions without needing to start with heavy coding or SQL.',
            'next_steps' => ['Enroll in the Rs. 499 first enroll offer', 'Practice standard deviation, CV, IQR, skewness, and kurtosis on a simple dataset', 'Group data by category, region, product, or customer type', 'Use AI insights and time forecasting to find patterns', 'Build a dashboard that explains the result to a business user'],
            'course_offer' => true,
        ],
    ];
}

function elldy_long_blog_body(array $config): string
{
    $slug = (string) ($config['slug'] ?? '');
    $skillText = implode(', ', $config['skills']);
    $metricText = implode(', ', $config['metrics']);
    $nextSteps = array_map(static fn (string $step): string => '- ' . $step, $config['next_steps']);
    $profile = elldy_blog_style_profile($slug);
    $courseOfferSection = [];

    if (!empty($config['course_offer'])) {
        $courseOfferSection = [
            '## Course offer for learners',
            'Elldy Academy is offering the data analytics course for just Rs. 499 on your first enroll instead of Rs. 2499. This makes it easier for students, freshers, aspiring data analysts, aspiring business analysts, and business owners to begin practical analytics without a heavy upfront cost.',
            'The course advantages are practical and career-focused. You learn how to understand data variation, standard deviation, coefficient of variation, data shape, skewness, kurtosis, IQR, groupings, AI insights, time forecasting, and dashboard building. These topics help you move beyond basic charts and understand what the data is really saying.',
            'This matters because real analytics is not only about tools. Standard deviation explains spread. CV helps compare variation across groups. Skewness and kurtosis explain the shape of data. IQR helps detect unusual values. Groupings help compare segments. AI insights and forecasting help you identify patterns faster. Dashboards help you present the final story to a business user.',
        ];
    }

    return implode("\n\n", array_merge([
        '## ' . $config['title'],
        elldy_blog_intro($config),
        'This article is written for ' . $config['audience'] . '. It is meant to explain the topic in a practical way, with enough business context to help you understand how the idea works in real decisions.',
    ], $courseOfferSection, [
        '## ' . $profile['problem_heading'],
        $config['problem'],
        $profile['relevance_body'],
        '## ' . $profile['thinking_heading'],
        $profile['thinking_intro'],
        'Before choosing Excel, Power BI, Tableau, SQL, or a no-code platform, ask what decision needs support. Are you trying to increase sales, reduce cost, improve customer retention, speed up operations, or understand team performance?',
        'Once the decision is clear, the data work becomes easier. You can identify which columns are needed, which metrics should be tracked, which comparison period is fair, and which dashboard view will help a stakeholder act.',
        '## ' . $profile['skills_heading'],
        'Useful capabilities for this topic include ' . $skillText . '. These are not just resume keywords. They are practical abilities that help you move from raw information to a clear recommendation.',
        $profile['skills_close'],
        '## ' . $profile['metrics_heading'],
        'A useful dashboard or report usually focuses on metrics such as ' . $metricText . '. The exact numbers can change by industry, but the principle is the same: choose KPIs that connect directly to decisions.',
        'A crowded dashboard can confuse readers. A strong dashboard helps the reader see what changed, whether the change is good or bad, and what action deserves attention.',
        '## ' . $profile['elldy_heading'],
        $config['elldy_angle'],
        'This is important for organic learners and business users because analytics adoption fails when tools feel too technical or disconnected from daily decisions. Elldy keeps the focus on business intelligence, dashboard clarity, KPI monitoring, and insight communication.',
        $profile['elldy_close'],
        '## ' . $profile['mistake_heading'],
        $profile['mistake_body'],
        '## ' . $profile['steps_heading'],
    ], $nextSteps, [
        '## ' . $profile['final_heading'],
        $profile['final_body'],
    ]));
}

function elldy_blog_intro(array $config): string
{
    $title = (string) ($config['title'] ?? 'This topic');
    $intent = (string) ($config['search_intent'] ?? '');

    $intent = preg_replace('/^The reader wants to know how /i', 'This article explains how ', $intent) ?? $intent;
    $intent = preg_replace('/^The reader wants to know what /i', 'This article explains what ', $intent) ?? $intent;
    $intent = preg_replace('/^The reader wants to /i', 'This article explains how to ', $intent) ?? $intent;
    $intent = preg_replace('/^The reader is searching for /i', 'This guide covers ', $intent) ?? $intent;
    $intent = preg_replace('/^The reader (wants|is searching for) /i', '', $intent) ?? $intent;

    if ($intent !== '' && stripos($intent, 'The reader') !== 0) {
        return ucfirst($intent);
    }

    return $title . ' is a practical guide for understanding the topic, comparing the options, and applying the ideas in real business situations.';
}

function elldy_blog_style_profile(string $slug): array
{
    $default = [
        'problem_heading' => 'The real problem behind the topic',
        'relevance_body' => 'Analytics is valuable because it reduces guesswork. Students can use it to build stronger projects, analysts can use it to explain patterns, and business owners can use it to make faster decisions without waiting for manual reporting.',
        'thinking_heading' => 'How to think about it practically',
        'thinking_intro' => 'Good analytics starts with the business question, not with a chart type.',
        'skills_heading' => 'What you should be able to do',
        'skills_close' => 'For learners, this becomes portfolio proof. For businesses, it becomes a repeatable way to make decisions from data.',
        'metrics_heading' => 'Numbers that make the story clear',
        'elldy_heading' => 'How Elldy supports this workflow',
        'elldy_close' => 'For Elldy Academy learners, this platform mindset makes training more practical because data becomes a dashboard, a dashboard becomes a discussion, and that discussion becomes a business action.',
        'mistake_heading' => 'What to avoid',
        'mistake_body' => 'Do not begin by collecting every possible data point. Start with the decision, choose the smallest useful dataset, and then explain what the numbers mean.',
        'steps_heading' => 'A practical next step',
        'final_heading' => 'Bottom line',
        'final_body' => 'Data analytics, business analytics, and business intelligence are most useful when they help people make better decisions from the data they already have.',
    ];

    $profiles = [
        'top-bi-platforms-tools-power-bi-tableau-elldy' => [
            'problem_heading' => 'Why BI tool comparison is difficult',
            'relevance_body' => 'A BI platform affects how quickly a team can understand performance, share numbers, and act on the same facts. That is why tool selection should be treated as a business decision, not only a software decision.',
            'thinking_heading' => 'Power BI, Tableau, and Elldy are built for different users',
            'thinking_intro' => 'A BI platform should be judged by the way your team works, not only by brand popularity.',
            'skills_heading' => 'What to compare before choosing a BI tool',
            'skills_close' => 'The best tool is the one that reduces reporting delay and helps more people act on the same version of truth.',
            'metrics_heading' => 'Comparison points that matter',
            'elldy_heading' => 'Why Elldy belongs in the comparison',
            'elldy_close' => 'That makes Elldy especially relevant for owners, startups, students, and teams that want business intelligence without a long technical setup.',
            'mistake_heading' => 'Do not choose a BI platform only by popularity',
            'mistake_body' => 'A popular platform can still fail if the people who need answers cannot use it. Compare adoption, dashboard speed, AI insights, monitoring, and the effort required to maintain reports.',
            'steps_heading' => 'How to shortlist the right BI platform',
            'final_heading' => 'The practical choice',
            'final_body' => 'Power BI, Tableau, and Elldy can all be useful. The best choice depends on whether your team needs enterprise reporting, visual exploration, or fast no-code business intelligence.',
        ],
        'top-5-bi-platforms-power-bi-tableau-looker-qlik-elldy' => [
            'problem_heading' => 'Why top BI platform lists can be misleading',
            'relevance_body' => 'The best BI platform for one company may be a poor fit for another. A large enterprise may prioritize governance and scale, while a startup may prioritize speed, no-code dashboards, and quick AI insights.',
            'thinking_heading' => 'Match the platform to the team, not the trend',
            'thinking_intro' => 'A top BI platform should fit the user, the data maturity, the reporting speed, and the type of decisions the business makes.',
            'skills_heading' => 'Searchable features buyers compare',
            'skills_close' => 'These comparison points help you separate enterprise reporting, governed analytics, visual exploration, and no-code AI dashboarding.',
            'metrics_heading' => 'How to judge real BI value',
            'elldy_heading' => 'Why underrated Elldy is worth watching',
            'elldy_close' => 'Elldy is especially relevant for India-based businesses, startups, students, and owners who want useful dashboards without writing SQL first.',
            'mistake_heading' => 'Avoid copying another company’s BI stack',
            'mistake_body' => 'A BI setup that works for a large enterprise may be too heavy for a startup. A simple no-code BI platform may be better when speed and adoption matter more than complex governance.',
            'steps_heading' => 'A simple BI selection checklist',
            'final_heading' => 'Best BI platform depends on the job',
            'final_body' => 'Power BI, Tableau, Looker, Qlik, and Elldy all have a place. The smartest choice is the one that your team can use repeatedly to make better decisions.',
        ],
        'build-ai-dashboard-in-2-minutes-with-elldy-no-code-no-sql' => [
            'problem_heading' => 'Why dashboard creation feels slow',
            'relevance_body' => 'When dashboards take too long to create, teams continue making decisions from scattered spreadsheets and delayed reports. Fast dashboarding helps business users notice change while it still matters.',
            'thinking_heading' => 'The dashboard should start from the decision',
            'thinking_intro' => 'A quick dashboard is useful only when it answers a real business question.',
            'skills_heading' => 'What an AI dashboard should help you do',
            'skills_close' => 'This is useful for founders, owners, and analysts because the work moves from manual report building to faster business review.',
            'metrics_heading' => 'Signals Elldy can help monitor',
            'elldy_heading' => 'Where Elldy changes the workflow',
            'elldy_close' => 'The advantage is speed: data can move from upload to insight to dashboard review without waiting for a full technical cycle.',
            'mistake_heading' => 'Do not build a dashboard just to show charts',
            'mistake_body' => 'A dashboard should show what changed and what needs action. If the dashboard does not support a decision, it is only decoration.',
            'steps_heading' => 'How to start with Elldy',
            'final_heading' => 'Fast dashboards are business tools',
            'final_body' => 'Building a dashboard quickly matters because business conditions change quickly. Elldy helps teams notice those changes and respond with data.',
        ],
        'how-elldy-helps-industries-startups-businesses-monitor-data-ai' => [
            'problem_heading' => 'Most businesses see problems too late',
            'relevance_body' => 'Active monitoring matters because business conditions can change quickly. A sudden sales drop, stock issue, campaign failure, demand spike, or operational delay is easier to handle when the team sees it early.',
            'thinking_heading' => 'From reporting to active monitoring',
            'thinking_intro' => 'Traditional reports explain what happened. Active data monitoring helps teams notice what is happening now.',
            'skills_heading' => 'What business monitoring should include',
            'skills_close' => 'For industries and startups, this turns analytics into an operating habit instead of a monthly reporting activity.',
            'metrics_heading' => 'Business signals worth watching',
            'elldy_heading' => 'How Elldy Analyst supports business teams',
            'elldy_close' => 'This makes Elldy more than a dashboard builder. It becomes a monitoring layer for owners, managers, and analysts who need faster awareness.',
            'mistake_heading' => 'Do not wait for month-end reporting',
            'mistake_body' => 'Sales drops, inventory issues, campaign problems, and operational delays become expensive when they are found late. Monitoring helps teams react sooner.',
            'steps_heading' => 'How a business can begin',
            'final_heading' => 'Elldy as an active intelligence layer',
            'final_body' => 'For industries, startups, and businesses, Elldy can help turn scattered data into alerts, forecasts, reports, and decisions.',
        ],
        'data-analyst-career-roadmap-for-students' => [
            'problem_heading' => 'Why students get stuck in tool learning',
            'relevance_body' => 'A student can learn many tools and still struggle in interviews if they cannot explain the business problem, the metric, the pattern, and the recommended action.',
            'thinking_heading' => 'Build career skill in the right order',
            'thinking_intro' => 'A data analyst career becomes easier when business understanding comes before tool memorization.',
            'skills_heading' => 'Skills that make a student job-ready',
            'skills_close' => 'A strong learner can explain the data problem, clean the dataset, build the dashboard, and communicate the insight.',
            'metrics_heading' => 'Project metrics students should practice',
            'elldy_heading' => 'How Elldy helps students practice like analysts',
            'elldy_close' => 'This helps students create portfolio work that looks closer to real business analytics than isolated tutorial output.',
            'mistake_heading' => 'Do not collect certificates without projects',
            'mistake_body' => 'Certificates help, but interviews usually reward proof. Build dashboards, write insight summaries, and explain business actions.',
            'steps_heading' => 'Student action plan',
            'final_heading' => 'A career-ready roadmap',
            'final_body' => 'The goal is not only to learn tools. The goal is to think, build, and communicate like a practical data analyst.',
        ],
    ];

    return array_replace($default, $profiles[$slug] ?? []);
}

function unique_blog_slug(string $title, int $ignoreId = 0): string
{
    ensure_blog_posts_table();
    $baseSlug = slugify($title);
    $slug = $baseSlug;
    $suffix = 2;

    while (true) {
        $stmt = db()->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function unique_course_slug(string $title, int $ignoreId = 0): string
{
    $baseSlug = slugify($title);
    $slug = $baseSlug;
    $suffix = 2;

    while (true) {
        $stmt = db()->prepare('SELECT id FROM courses WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function blog_url(array $post): string
{
    return public_url('blog.php?slug=' . rawurlencode((string) ($post['slug'] ?? '')));
}

function blog_absolute_url(array $post): string
{
    return site_url('blog.php?slug=' . rawurlencode((string) ($post['slug'] ?? '')));
}

function published_blog_posts(int $limit = 12): array
{
    ensure_blog_posts_table();
    $stmt = db()->prepare(
        "SELECT *
         FROM blog_posts
         WHERE status = 'published'
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function blog_reading_minutes(string $body): int
{
    $words = str_word_count(strip_tags($body));
    return max(1, (int) ceil($words / 180));
}

function blog_content_html(string $body): string
{
    $lines = preg_split('/\R/', trim($body)) ?: [];
    $html = [];
    $listOpen = false;

    $closeList = static function () use (&$html, &$listOpen): void {
        if ($listOpen) {
            $html[] = '</ul>';
            $listOpen = false;
        }
    };

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            $closeList();
            continue;
        }

        if (str_starts_with($line, '## ')) {
            $closeList();
            $html[] = '<h2>' . e(substr($line, 3)) . '</h2>';
            continue;
        }

        if (str_starts_with($line, '- ')) {
            if (!$listOpen) {
                $html[] = '<ul>';
                $listOpen = true;
            }

            $html[] = '<li>' . e(substr($line, 2)) . '</li>';
            continue;
        }

        $closeList();
        $html[] = '<p>' . e($line) . '</p>';
    }

    $closeList();

    return implode("\n", $html);
}

function program_url(array $course): string
{
    $slug = trim((string) ($course['slug'] ?? ''));

    if ($slug === '') {
        $slug = slugify((string) ($course['title'] ?? 'program'));
    }

    return public_url('program/' . rawurlencode($slug));
}

function program_absolute_url(array $course): string
{
    $slug = trim((string) ($course['slug'] ?? ''));

    if ($slug === '') {
        $slug = slugify((string) ($course['title'] ?? 'program'));
    }

    return site_url('program/' . rawurlencode($slug));
}

function detail_points(?string $value): string
{
    $lines = preg_split('/\R/', trim((string) $value)) ?: [];
    $items = array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));

    if (!$items) {
        return '<p class="empty">Details will be updated soon.</p>';
    }

    $html = '<ul class="detail-list">';
    foreach ($items as $item) {
        $html .= '<li>' . e($item) . '</li>';
    }

    return $html . '</ul>';
}

function video_embed_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    parse_str((string) ($parts['query'] ?? ''), $query);

    if (str_contains($host, 'youtube.com') && !empty($query['v'])) {
        return 'https://www.youtube.com/embed/' . rawurlencode((string) $query['v']);
    }

    if (str_contains($host, 'youtu.be')) {
        return 'https://www.youtube.com/embed/' . rawurlencode(trim($path, '/'));
    }

    if (str_contains($host, 'vimeo.com')) {
        return 'https://player.vimeo.com/video/' . rawurlencode(trim($path, '/'));
    }

    if (str_contains($host, 'drive.google.com') && preg_match('#/file/d/([^/]+)#', $path, $matches)) {
        return 'https://drive.google.com/file/d/' . rawurlencode($matches[1]) . '/preview';
    }

    return $url;
}

function is_direct_video_url(string $url): bool
{
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
    return (bool) preg_match('/\.(mp4|webm|ogg|mov|m4v)$/', $path);
}

function is_embed_video_provider_url(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

    return str_contains($host, 'youtube.com') ||
        str_contains($host, 'youtu.be') ||
        str_contains($host, 'vimeo.com') ||
        str_contains($host, 'drive.google.com');
}

function is_playable_video_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

    return is_direct_video_url($url) ||
        is_embed_video_provider_url($url) ||
        s3_object_key_from_url($url) !== null ||
        str_contains($host, '.s3.') ||
        str_ends_with($host, '.amazonaws.com');
}

function should_use_native_video_player(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

    return is_direct_video_url($url) ||
        s3_object_key_from_url($url) !== null ||
        str_contains($host, '.s3.') ||
        str_ends_with($host, '.amazonaws.com') ||
        (!is_embed_video_provider_url($url) && $url !== '');
}

function ensure_learning_progress_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS learning_progress (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            material_id INT UNSIGNED NOT NULL,
            watched_seconds DECIMAL(10,2) NOT NULL DEFAULT 0,
            duration_seconds DECIMAL(10,2) NOT NULL DEFAULT 0,
            progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            is_completed TINYINT(1) NOT NULL DEFAULT 0,
            completed_at DATETIME NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_enrollment_material (enrollment_id, material_id),
            INDEX idx_learning_progress_user (user_id),
            INDEX idx_learning_progress_course (course_id),
            CONSTRAINT fk_learning_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            CONSTRAINT fk_learning_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_learning_progress_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            CONSTRAINT fk_learning_progress_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
        )"
    );
}

function learning_progress_for_enrollment(int $enrollmentId): array
{
    ensure_learning_progress_table();
    $stmt = db()->prepare('SELECT * FROM learning_progress WHERE enrollment_id = ?');
    $stmt->execute([$enrollmentId]);
    $rows = [];

    foreach ($stmt->fetchAll() as $row) {
        $rows[(int) $row['material_id']] = $row;
    }

    return $rows;
}

function ensure_live_session_attendance_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS live_session_attendance (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            material_id INT UNSIGNED NOT NULL,
            joined_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_live_session_attendance (enrollment_id, material_id),
            INDEX idx_live_session_attendance_user (user_id),
            INDEX idx_live_session_attendance_course (course_id),
            CONSTRAINT fk_live_session_attendance_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            CONSTRAINT fk_live_session_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_live_session_attendance_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            CONSTRAINT fk_live_session_attendance_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
        )"
    );
}

function live_session_room_name(array $enrollment, array $material): string
{
    $configured = trim((string) ($material['file_url'] ?? ''));

    if ($configured !== '' && !str_contains($configured, '://')) {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $configured) ?: 'ElldyAcademyLiveClass';
    }

    if ($configured !== '') {
        $host = strtolower((string) (parse_url($configured, PHP_URL_HOST) ?? ''));
        $path = trim((string) (parse_url($configured, PHP_URL_PATH) ?? ''), '/');

        if (str_contains($host, 'meet.jit.si') && $path !== '') {
            return preg_replace('/[^A-Za-z0-9_-]/', '', basename($path)) ?: 'ElldyAcademyLiveClass';
        }
    }

    $courseId = (int) ($enrollment['course_id'] ?? $material['course_id'] ?? 0);
    $materialId = (int) ($material['id'] ?? 0);
    $hash = substr(hash('sha256', 'elldy-academy-live:' . $courseId . ':' . $materialId), 0, 10);

    return 'ElldyAcademyC' . $courseId . 'M' . $materialId . $hash;
}

function live_session_is_external_url(string $url): bool
{
    if ($url === '' || !str_contains($url, '://')) {
        return false;
    }

    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

    return !str_contains($host, 'meet.jit.si');
}

function live_session_provider_name(string $url): string
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

    if (str_contains($host, 'meet.google.com')) {
        return 'Google Meet';
    }

    if (str_contains($host, 'zoom.us')) {
        return 'Zoom';
    }

    if (str_contains($host, 'teams.microsoft.com')) {
        return 'Microsoft Teams';
    }

    if (str_contains($host, 'meet.jit.si')) {
        return 'Jitsi Meet';
    }

    return 'Live class';
}

function live_session_is_zoom_url(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

    return str_contains($host, 'zoom.us');
}

function live_session_embed_url(array $enrollment, array $material, array $user): string
{
    $room = live_session_room_name($enrollment, $material);
    $displayName = trim((string) ($user['name'] ?? 'Trainee')) ?: 'Trainee';

    return 'https://meet.jit.si/' . rawurlencode($room) .
        '#config.prejoinPageEnabled=false&userInfo.displayName=' . rawurlencode('"' . $displayName . '"');
}

function record_live_session_attendance(array $enrollment, array $material): void
{
    ensure_live_session_attendance_table();
    ensure_learning_progress_table();

    $enrollmentId = (int) $enrollment['id'];
    $userId = (int) $enrollment['user_id'];
    $courseId = (int) $enrollment['course_id'];
    $materialId = (int) $material['id'];

    $attendance = db()->prepare(
        "INSERT INTO live_session_attendance (enrollment_id, user_id, course_id, material_id, joined_at, last_seen_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE last_seen_at = NOW()"
    );
    $attendance->execute([$enrollmentId, $userId, $courseId, $materialId]);

    $progress = db()->prepare(
        "INSERT INTO learning_progress
            (enrollment_id, user_id, course_id, material_id, watched_seconds, duration_seconds, progress_percent, is_completed, completed_at)
         VALUES (?, ?, ?, ?, 0, 0, 100, 1, NOW())
         ON DUPLICATE KEY UPDATE progress_percent = 100, is_completed = 1, completed_at = COALESCE(completed_at, NOW())"
    );
    $progress->execute([$enrollmentId, $userId, $courseId, $materialId]);
}

function clear_attendance_progress_for_video_session(array $enrollment, array $material): bool
{
    ensure_live_session_attendance_table();
    ensure_learning_progress_table();

    $enrollmentId = (int) $enrollment['id'];
    $materialId = (int) $material['id'];

    $attendance = db()->prepare('DELETE FROM live_session_attendance WHERE enrollment_id = ? AND material_id = ?');
    $attendance->execute([$enrollmentId, $materialId]);

    $progress = db()->prepare(
        "DELETE FROM learning_progress
         WHERE enrollment_id = ?
            AND material_id = ?
            AND watched_seconds = 0
            AND duration_seconds = 0
            AND progress_percent = 100
            AND is_completed = 1"
    );
    $progress->execute([$enrollmentId, $materialId]);

    return $progress->rowCount() > 0;
}

function enrollment_learning_completion(int $enrollmentId): array
{
    ensure_learning_progress_table();

    $stmt = db()->prepare(
        "SELECT e.id, e.status, c.delivery_type
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.status != 'cancelled'"
    );
    $stmt->execute([$enrollmentId]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        return ['total' => 0, 'completed' => 0, 'is_complete' => false];
    }

    if (($enrollment['status'] ?? '') === 'completed') {
        return ['total' => 1, 'completed' => 1, 'is_complete' => true];
    }

    $primaryTypes = primary_material_types_for_delivery((string) ($enrollment['delivery_type'] ?? 'video'));
    $placeholders = implode(',', array_fill(0, count($primaryTypes), '?'));
    $counts = db()->prepare(
        "SELECT COUNT(m.id) AS total,
            SUM(CASE WHEN lp.is_completed = 1 THEN 1 ELSE 0 END) AS completed
         FROM enrollments e
         JOIN materials m ON m.course_id = e.course_id AND m.material_type IN ({$placeholders})
         LEFT JOIN learning_progress lp ON lp.enrollment_id = e.id AND lp.material_id = m.id
         WHERE e.id = ?"
    );
    $counts->execute([...$primaryTypes, $enrollmentId]);
    $row = $counts->fetch() ?: [];
    $total = (int) ($row['total'] ?? 0);
    $completed = (int) ($row['completed'] ?? 0);

    return [
        'total' => $total,
        'completed' => $completed,
        'is_complete' => $total > 0 && $completed >= $total,
    ];
}

function ensure_material_columns(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'materials'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($existing['material_type'])) {
        db()->exec("ALTER TABLE materials ADD COLUMN material_type ENUM('video', 'live_session', 'material') NOT NULL DEFAULT 'video' AFTER description");
    }

    if (!isset($existing['sort_order'])) {
        db()->exec("ALTER TABLE materials ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER file_url");
    }
}

function ensure_certificate_requests_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS certificate_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            status ENUM('requested', 'payment_pending', 'approved', 'issued', 'rejected') NOT NULL DEFAULT 'requested',
            payment_note TEXT NULL,
            applied_at DATETIME NULL,
            dashboard_url VARCHAR(500) NULL,
            dashboard_review_status ENUM('not_submitted', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'not_submitted',
            dashboard_review_note TEXT NULL,
            dashboard_submitted_at DATETIME NULL,
            dashboard_reviewed_at DATETIME NULL,
            certificate_url VARCHAR(255) NULL,
            certificate_code VARCHAR(80) NULL,
            issued_at DATETIME NULL,
            downloaded_at DATETIME NULL,
            download_count INT UNSIGNED NOT NULL DEFAULT 0,
            admin_note TEXT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_certificate_enrollment (enrollment_id),
            CONSTRAINT fk_certificate_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            CONSTRAINT fk_certificate_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_certificate_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        )"
    );

    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'certificate_requests'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($existing['certificate_code'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN certificate_code VARCHAR(80) NULL AFTER certificate_url");
    }

    if (!isset($existing['issued_at'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN issued_at DATETIME NULL AFTER certificate_code");
    }

    if (!isset($existing['dashboard_url'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN dashboard_url VARCHAR(500) NULL AFTER payment_note");
    }

    if (!isset($existing['applied_at'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN applied_at DATETIME NULL AFTER payment_note");
    }

    if (!isset($existing['dashboard_review_status'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN dashboard_review_status ENUM('not_submitted', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'not_submitted' AFTER dashboard_url");
    }

    if (!isset($existing['dashboard_review_note'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN dashboard_review_note TEXT NULL AFTER dashboard_review_status");
    }

    if (!isset($existing['dashboard_submitted_at'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN dashboard_submitted_at DATETIME NULL AFTER dashboard_review_note");
    }

    if (!isset($existing['dashboard_reviewed_at'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN dashboard_reviewed_at DATETIME NULL AFTER dashboard_submitted_at");
    }

    if (!isset($existing['downloaded_at'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN downloaded_at DATETIME NULL AFTER issued_at");
    }

    if (!isset($existing['download_count'])) {
        db()->exec("ALTER TABLE certificate_requests ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER downloaded_at");
    }
}

function certificate_badge(string $status): string
{
    return match ($status) {
        'requested' => 'Requested',
        'payment_pending' => 'Payment Pending',
        'approved' => 'Approved',
        'issued' => 'Issued',
        'rejected' => 'Cancelled',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function dashboard_review_badge(?string $status): string
{
    return match ($status) {
        'pending' => 'Review Pending',
        'approved' => 'Approved',
        'rejected' => 'Needs Changes',
        default => 'Not Submitted',
    };
}

function normalize_elldy_dashboard_url(string $url): string
{
    $url = trim($url);
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return $url;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = strtolower((string) $parts['host']);
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = rtrim((string) ($parts['path'] ?? ''), '/');
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

    return $scheme . '://' . $host . $port . $path . $query;
}

function is_elldy_dashboard_url(string $url): bool
{
    $url = normalize_elldy_dashboard_url($url);
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($host === '') {
        return false;
    }

    if ($host === 'academy.elldy.com') {
        return false;
    }

    return $host === 'elldy.com' || str_ends_with($host, '.elldy.com');
}

function certificate_dashboard_is_approved(array $certificate): bool
{
    return ($certificate['dashboard_review_status'] ?? '') === 'approved'
        && is_elldy_dashboard_url((string) ($certificate['dashboard_url'] ?? ''));
}

function certificate_code_for_enrollment(int $enrollmentId): string
{
    return 'ELD-DA-' . date('Y') . '-' . str_pad((string) $enrollmentId, 4, '0', STR_PAD_LEFT);
}

function svg_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function pdf_escape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
}

function pdf_text(float $x, float $y, string $text, int $size, string $font = 'F1'): string
{
    return "BT /{$font} {$size} Tf {$x} {$y} Td (" . pdf_escape($text) . ") Tj ET\n";
}

function centered_pdf_text(float $centerX, float $y, string $text, int $size, string $font = 'F1'): string
{
    $estimatedWidth = strlen($text) * $size * 0.48;
    return pdf_text(round($centerX - ($estimatedWidth / 2), 2), $y, $text, $size, $font);
}

function certificate_jpeg_asset(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $size = getimagesize($path);
    if (!$size || ($size['mime'] ?? '') !== 'image/jpeg') {
        return null;
    }

    return [
        'width' => (int) $size[0],
        'height' => (int) $size[1],
        'data' => (string) file_get_contents($path),
    ];
}

function build_certificate_pdf(array $row, string $certificateCode): string
{
    $name = trim((string) $row['name']);
    $title = trim((string) (($row['certificate_title'] ?? '') !== '' ? $row['certificate_title'] : $row['title']));
    $certificateDetails = trim((string) ($row['certificate_details'] ?? ''));
    $completionDate = date('F j, Y');
    $safeTitle = strlen($title) > 58 ? substr($title, 0, 55) . '...' : $title;
    $brandingDir = __DIR__ . '/../assets/certificates/branding';
    $elldyLogo = certificate_jpeg_asset($brandingDir . '/elldy-logo.jpg');
    $arklyticsLogo = certificate_jpeg_asset($brandingDir . '/arklytics-logo.jpg');
    $signature = certificate_jpeg_asset($brandingDir . '/signature.jpeg');
    $medalBadge = certificate_jpeg_asset($brandingDir . '/medal-badge.jpg');

    $content = '';
    $content .= "q\n";
    $content .= "0.985 0.99 1 rg 0 0 842 595 re f\n";
    $content .= "0.04 0.16 0.34 RG 3.2 w 24 24 794 547 re S\n";
    $content .= "0.81 0.65 0.34 RG 1.2 w 38 38 766 519 re S\n";
    $content .= "0.04 0.16 0.34 rg 78 490 686 1 re f\n";
    $content .= "0.81 0.65 0.34 rg 78 484 686 2 re f\n";
    if ($elldyLogo) {
        $content .= "0.95 0.97 1 rg 70 516 70 28 re f\n";
        $content .= "0.82 0.87 0.94 RG 0.8 w 70 516 70 28 re S\n";
        $content .= "q 58 0 0 22 76 519 cm /Im1 Do Q\n";
    }
    if ($arklyticsLogo) {
        $content .= "1 1 1 rg 148 516 92 28 re f\n";
        $content .= "0.82 0.87 0.94 RG 0.8 w 148 516 92 28 re S\n";
        $content .= "q 76 0 0 19 156 521 cm /Im2 Do Q\n";
    }
    $content .= "0.04 0.16 0.34 rg\n";
    $content .= centered_pdf_text(421, 453, 'CERTIFICATE OF COMPLETION', 27, 'F2');
    $content .= "0.38 0.42 0.5 rg\n";
    $content .= centered_pdf_text(421, 425, 'Issued under the Elldy Data Intelligence Platform learning ecosystem', 11);
    $content .= "0.15 0.18 0.24 rg\n";
    $content .= centered_pdf_text(421, 381, 'This certificate is proudly presented to', 14);
    $content .= "0.04 0.16 0.34 rg\n";
    $content .= centered_pdf_text(421, 341, $name, 30, 'F2');
    $content .= "0.81 0.65 0.34 RG 1.5 w 244 326 m 598 326 l S\n";
    if ($medalBadge) {
        $content .= "q 96 0 0 96 604 300 cm /Im4 Do Q\n";
        $content .= "0.63 0.43 0.11 rg\n";
        $content .= centered_pdf_text(652, 348, 'VERIFIED', 9, 'F2');
    }
    $content .= "0.15 0.18 0.24 rg\n";
    $content .= centered_pdf_text(421, 292, 'for successfully completing the program', 14);
    $content .= centered_pdf_text(421, 257, $safeTitle, 20, 'F2');
    $content .= "0.81 0.65 0.34 RG 1 w 170 242 m 672 242 l S\n";
    $content .= "0.38 0.42 0.5 rg\n";
    if ($certificateDetails === '') {
        $certificateDetails = "Data preparation\nDashboard building\nForecasting\nBusiness intelligence";
    }
    $detailParts = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|\|/', $certificateDetails) ?: [])));
    $detailLine = implode('  •  ', array_slice($detailParts, 0, 4));
    $content .= centered_pdf_text(421, 216, 'successfully demonstrating capability in', 12);
    $content .= centered_pdf_text(421, 194, $detailLine, 12, 'F2');
    $content .= "0.04 0.16 0.34 rg\n";
    $content .= pdf_text(72, 120, 'Completion Date', 10, 'F2');
    $content .= "0.81 0.65 0.34 RG 0.8 w 72 114 m 192 114 l S\n";
    $content .= pdf_text(72, 94, $completionDate, 13);
    $content .= pdf_text(311, 120, 'Certificate ID', 10, 'F2');
    $content .= "0.81 0.65 0.34 RG 0.8 w 311 114 m 468 114 l S\n";
    $content .= pdf_text(311, 94, $certificateCode, 13);
    $content .= pdf_text(610, 120, 'Authorized Signatory', 10, 'F2');
    $content .= "0.81 0.65 0.34 RG 0.8 w 610 114 m 742 114 l S\n";
    $content .= pdf_text(632, 94, 'Elldy Academy', 13);
    if ($signature) {
        $content .= "q 116 0 0 28 618 137 cm /Im3 Do Q\n";
        $content .= "0.04 0.16 0.34 RG 0.8 w 610 132 m 742 132 l S\n";
    }
    $content .= "Q\n";

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> /XObject << /Im1 7 0 R /Im2 8 0 R /Im3 9 0 R /Im4 10 0 R >> >> /Contents 4 0 R >>';
    $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
    $objects[] = $elldyLogo
        ? "<< /Type /XObject /Subtype /Image /Width {$elldyLogo['width']} /Height {$elldyLogo['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($elldyLogo['data']) . " >>\nstream\n{$elldyLogo['data']}\nendstream"
        : '<< >>';
    $objects[] = $arklyticsLogo
        ? "<< /Type /XObject /Subtype /Image /Width {$arklyticsLogo['width']} /Height {$arklyticsLogo['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($arklyticsLogo['data']) . " >>\nstream\n{$arklyticsLogo['data']}\nendstream"
        : '<< >>';
    $objects[] = $signature
        ? "<< /Type /XObject /Subtype /Image /Width {$signature['width']} /Height {$signature['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($signature['data']) . " >>\nstream\n{$signature['data']}\nendstream"
        : '<< >>';
    $objects[] = $medalBadge
        ? "<< /Type /XObject /Subtype /Image /Width {$medalBadge['width']} /Height {$medalBadge['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($medalBadge['data']) . " >>\nstream\n{$medalBadge['data']}\nendstream"
        : '<< >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $objectNumber = $index + 1;
        $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

function issue_certificate_for_enrollment(array $row): ?string
{
    ensure_certificate_requests_table();

    if (!certificate_dashboard_is_approved($row)) {
        return null;
    }

    $certificateCode = trim((string) ($row['certificate_code'] ?? ''));
    if ($certificateCode === '') {
        $certificateCode = certificate_code_for_enrollment((int) $row['enrollment_id']);
    }

    $issuedDir = __DIR__ . '/../assets/certificates/issued';
    if (!is_dir($issuedDir)) {
        mkdir($issuedDir, 0775, true);
    }

    $fileName = 'certificate-' . (int) $row['enrollment_id'] . '.pdf';
    $absolutePath = $issuedDir . '/' . $fileName;
    file_put_contents($absolutePath, build_certificate_pdf($row, $certificateCode));
    $certificateUrl = public_url('download_certificate.php?enrollment_id=' . (int) $row['enrollment_id']);

    $stmt = db()->prepare(
        "UPDATE certificate_requests
         SET status = 'issued', certificate_url = ?, certificate_code = ?, issued_at = NOW()
         WHERE enrollment_id = ?"
    );
    $stmt->execute([$certificateUrl, $certificateCode, (int) $row['enrollment_id']]);

    return $certificateUrl;
}

function ensure_instant_certificate_for_enrollment(int $enrollmentId): ?array
{
    ensure_certificate_requests_table();

    $stmt = db()->prepare(
        "SELECT cr.*, e.id AS enrollment_id, e.status AS enrollment_status, u.name,
                c.title, c.fee, c.discount_fee, c.certification_fee, c.certificate_discount_fee, c.certificate_title, c.certificate_details
         FROM certificate_requests cr
         JOIN enrollments e ON e.id = cr.enrollment_id
         JOIN users u ON u.id = cr.user_id
         JOIN courses c ON c.id = cr.course_id
         WHERE cr.enrollment_id = ?"
    );
    $stmt->execute([$enrollmentId]);
    $certificate = $stmt->fetch();

    if (!$certificate) {
        return $certificate ?: null;
    }

    if (!in_array($certificate['enrollment_status'], ['paid', 'completed'], true) && course_fee_amount($certificate) > 0) {
        return $certificate;
    }

    if (certificate_fee_amount($certificate) > 0 && trim((string) ($certificate['payment_note'] ?? '')) === '') {
        return $certificate;
    }

    if (!certificate_dashboard_is_approved($certificate)) {
        return $certificate;
    }

    $downloadUrl = public_url('download_certificate.php?enrollment_id=' . $enrollmentId);
    if (($certificate['status'] ?? '') === 'issued' && ($certificate['certificate_url'] ?? '') !== $downloadUrl) {
        $urlUpdate = db()->prepare('UPDATE certificate_requests SET certificate_url = ? WHERE enrollment_id = ?');
        $urlUpdate->execute([$downloadUrl, $enrollmentId]);
        $certificate['certificate_url'] = $downloadUrl;
    }

    $expectedIssuedPath = __DIR__ . '/../assets/certificates/issued/certificate-' . $enrollmentId . '.pdf';

    if (($certificate['status'] ?? '') === 'issued' && (empty($certificate['certificate_url']) || !is_file($expectedIssuedPath))) {
        issue_certificate_for_enrollment($certificate);
        $stmt->execute([$enrollmentId]);
        $certificate = $stmt->fetch();
    }

    return $certificate ?: null;
}

function money(float|int|string $value): string
{
    return '₹' . number_format((float) $value, 2);
}

function discounted_amount(array $row, string $regularKey, string $discountKey): float
{
    $regular = max(0, (float) ($row[$regularKey] ?? 0));

    if (!array_key_exists($discountKey, $row) || $row[$discountKey] === null || $row[$discountKey] === '') {
        return $regular;
    }

    $discount = max(0, (float) $row[$discountKey]);

    return $discount < $regular ? $discount : $regular;
}

function course_fee_amount(array $course): float
{
    return discounted_amount($course, 'fee', 'discount_fee');
}

function certificate_fee_amount(array $course): float
{
    return discounted_amount($course, 'certification_fee', 'certificate_discount_fee');
}

function course_should_show_fee_details(array $course): bool
{
    return (int) ($course['show_fee_details'] ?? 1) === 1;
}

function price_html(array $row, string $regularKey, string $discountKey): string
{
    $regular = max(0, (float) ($row[$regularKey] ?? 0));
    $amount = discounted_amount($row, $regularKey, $discountKey);

    if ($regular > 0 && $amount < $regular) {
        if ($amount <= 0) {
            return '<span class="price-stack"><del>' . e(money($regular)) . '</del><strong>Free</strong></span>';
        }

        return '<span class="price-stack"><del>' . e(money($regular)) . '</del><strong>' . e(money($amount)) . '</strong></span>';
    }

    return e(money($amount));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token.');
    }
}

function ensure_whatsapp_settings_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS whatsapp_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            business_account_id VARCHAR(80) NULL,
            phone_number_id VARCHAR(80) NULL,
            access_token TEXT NULL,
            template_name VARCHAR(120) NULL,
            template_language VARCHAR(20) NOT NULL DEFAULT 'en',
            graph_version VARCHAR(20) NOT NULL DEFAULT 'v20.0',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'whatsapp_settings'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($existing['template_language'])) {
        db()->exec("ALTER TABLE whatsapp_settings ADD COLUMN template_language VARCHAR(20) NOT NULL DEFAULT 'en' AFTER template_name");
    }

    if (!isset($existing['enrollment_template_name'])) {
        db()->exec("ALTER TABLE whatsapp_settings ADD COLUMN enrollment_template_name VARCHAR(120) NULL AFTER template_name");
    }

    if (!isset($existing['course_invite_template_name'])) {
        db()->exec("ALTER TABLE whatsapp_settings ADD COLUMN course_invite_template_name VARCHAR(120) NULL AFTER enrollment_template_name");
    }

    if (!isset($existing['reminder_template_name'])) {
        db()->exec("ALTER TABLE whatsapp_settings ADD COLUMN reminder_template_name VARCHAR(120) NULL AFTER course_invite_template_name");
    }

    if (!isset($existing['certificate_template_name'])) {
        db()->exec("ALTER TABLE whatsapp_settings ADD COLUMN certificate_template_name VARCHAR(120) NULL AFTER reminder_template_name");
    }

    $stmt = db()->prepare(
        "INSERT INTO whatsapp_settings (id, business_account_id, phone_number_id, access_token, template_name, enrollment_template_name, course_invite_template_name, reminder_template_name, certificate_template_name, template_language, graph_version)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute([
        WHATSAPP_BUSINESS_ACCOUNT_ID,
        WHATSAPP_PHONE_NUMBER_ID,
        whatsapp_access_token(),
        whatsapp_otp_template_name(),
        null,
        null,
        null,
        null,
        'en',
        WHATSAPP_GRAPH_VERSION,
    ]);

    db()->exec(
        "UPDATE whatsapp_settings
         SET template_name = 'elldy_academy_otp'
         WHERE id = 1 AND (template_name IS NULL OR template_name = '')"
    );
}

function whatsapp_settings(): array
{
    ensure_whatsapp_settings_table();

    $settings = db()->query('SELECT * FROM whatsapp_settings WHERE id = 1')->fetch() ?: [];

    return [
        'business_account_id' => trim((string) ($settings['business_account_id'] ?? WHATSAPP_BUSINESS_ACCOUNT_ID)),
        'phone_number_id' => trim((string) ($settings['phone_number_id'] ?? WHATSAPP_PHONE_NUMBER_ID)),
        'access_token' => trim((string) ($settings['access_token'] ?? whatsapp_access_token())),
        'template_name' => trim((string) ($settings['template_name'] ?? whatsapp_otp_template_name())),
        'enrollment_template_name' => trim((string) ($settings['enrollment_template_name'] ?? '')),
        'course_invite_template_name' => trim((string) ($settings['course_invite_template_name'] ?? '')),
        'reminder_template_name' => trim((string) ($settings['reminder_template_name'] ?? '')),
        'certificate_template_name' => trim((string) ($settings['certificate_template_name'] ?? '')),
        'template_language' => trim((string) ($settings['template_language'] ?? 'en')) ?: 'en',
        'graph_version' => trim((string) ($settings['graph_version'] ?? WHATSAPP_GRAPH_VERSION)) ?: WHATSAPP_GRAPH_VERSION,
    ];
}

function save_whatsapp_settings(array $data): void
{
    ensure_whatsapp_settings_table();

    $stmt = db()->prepare(
        "UPDATE whatsapp_settings
         SET business_account_id = ?, phone_number_id = ?, access_token = ?, template_name = ?, enrollment_template_name = ?, course_invite_template_name = ?, reminder_template_name = ?, certificate_template_name = ?, template_language = ?, graph_version = ?
         WHERE id = 1"
    );
    $stmt->execute([
        trim((string) ($data['business_account_id'] ?? '')),
        trim((string) ($data['phone_number_id'] ?? '')),
        trim((string) ($data['access_token'] ?? '')),
        trim((string) ($data['template_name'] ?? '')),
        trim((string) ($data['enrollment_template_name'] ?? '')),
        trim((string) ($data['course_invite_template_name'] ?? '')),
        trim((string) ($data['reminder_template_name'] ?? '')),
        trim((string) ($data['certificate_template_name'] ?? '')),
        trim((string) ($data['template_language'] ?? 'en')) ?: 'en',
        trim((string) ($data['graph_version'] ?? WHATSAPP_GRAPH_VERSION)) ?: WHATSAPP_GRAPH_VERSION,
    ]);
}

function send_whatsapp_template_message_result(string $phone, string $templateName, array $bodyParameters = [], array $options = []): array
{
    $settings = whatsapp_settings();
    unset($_SESSION['whatsapp_send_error']);

    if ($settings['access_token'] === '') {
        $_SESSION['whatsapp_send_error'] = 'WhatsApp access token is missing. Save it in Admin > WhatsApp.';
        return ['ok' => false, 'message_id' => '', 'error' => $_SESSION['whatsapp_send_error']];
    }

    if ($settings['phone_number_id'] === '') {
        $_SESSION['whatsapp_send_error'] = 'WhatsApp Phone Number ID is missing. Save it in Admin > WhatsApp.';
        return ['ok' => false, 'message_id' => '', 'error' => $_SESSION['whatsapp_send_error']];
    }

    if ($templateName === '') {
        $_SESSION['whatsapp_send_error'] = 'WhatsApp template name is missing.';
        return ['ok' => false, 'message_id' => '', 'error' => $_SESSION['whatsapp_send_error']];
    }

    if (!function_exists('curl_init')) {
        $_SESSION['whatsapp_send_error'] = 'PHP cURL extension is not enabled in XAMPP.';
        return ['ok' => false, 'message_id' => '', 'error' => $_SESSION['whatsapp_send_error']];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => normalize_whatsapp_number($phone),
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => ['code' => $settings['template_language']],
        ],
    ];

    $components = [];
    $headerType = strtolower(trim((string) ($options['header_type'] ?? '')));
    $headerValue = trim((string) ($options['header_value'] ?? ''));

    if ($headerType !== '' && $headerType !== 'none' && $headerValue !== '') {
        if ($headerType === 'text') {
            $headerParameter = ['type' => 'text', 'text' => $headerValue];
        } elseif (in_array($headerType, ['image', 'video', 'document'], true)) {
            $headerParameter = ['type' => $headerType, $headerType => ['link' => $headerValue]];
        } else {
            $_SESSION['whatsapp_send_error'] = 'Unsupported WhatsApp template header type.';
            return ['ok' => false, 'message_id' => '', 'error' => $_SESSION['whatsapp_send_error']];
        }

        $components[] = [
            'type' => 'header',
            'parameters' => [$headerParameter],
        ];
    }

    if ($bodyParameters) {
        $components[] = [
            'type' => 'body',
            'parameters' => array_map(
                fn (string $value): array => ['type' => 'text', 'text' => $value],
                array_map('strval', $bodyParameters)
            ),
        ];
    }

    if ($components) {
        $payload['template']['components'] = $components;
    }

    $url = 'https://graph.facebook.com/' . $settings['graph_version'] . '/' . $settings['phone_number_id'] . '/messages';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $settings['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        $message = 'Meta WhatsApp API rejected the template message.';
        $decoded = json_decode((string) $response, true);
        if (isset($decoded['error']['message'])) {
            $message .= ' ' . $decoded['error']['message'];
        } elseif ($curlError !== '') {
            $message .= ' ' . $curlError;
        }
        $_SESSION['whatsapp_send_error'] = $message;

        return ['ok' => false, 'message_id' => '', 'error' => $message];
    }

    $decoded = json_decode((string) $response, true);
    $messageId = (string) ($decoded['messages'][0]['id'] ?? '');

    return ['ok' => true, 'message_id' => $messageId, 'error' => ''];
}

function send_whatsapp_template_message(string $phone, string $templateName, array $bodyParameters = [], array $options = []): bool
{
    return send_whatsapp_template_message_result($phone, $templateName, $bodyParameters, $options)['ok'];
}

function send_enrollment_whatsapp(array $user, array $course): bool
{
    $settings = whatsapp_settings();

    if ($settings['enrollment_template_name'] === '') {
        $_SESSION['whatsapp_send_error'] = 'Enrollment WhatsApp template name is missing. Save the approved Meta template name in Admin > WhatsApp.';
        return false;
    }

    return send_whatsapp_template_message(
        (string) $user['phone'],
        $settings['enrollment_template_name'],
        [(string) $user['name'], (string) $course['title'], site_url('login.php')]
    );
}

function send_course_invite_whatsapp(string $phone, string $name, array $course, string $description = '', string $duration = ''): bool
{
    return send_course_invite_whatsapp_result($phone, $name, $course, $description, $duration)['ok'];
}

function send_course_invite_whatsapp_result(string $phone, string $name, array $course, string $description = '', string $duration = ''): array
{
    $settings = whatsapp_settings();

    if ($settings['course_invite_template_name'] === '') {
        $_SESSION['whatsapp_send_error'] = 'Course invite WhatsApp template name is missing. Save the approved Meta template name in Admin > WhatsApp.';
        return ['ok' => false, 'message_id' => '', 'error' => $_SESSION['whatsapp_send_error']];
    }

    return send_whatsapp_template_message_result(
        $phone,
        $settings['course_invite_template_name'],
        [
            $name !== '' ? $name : 'there',
            (string) $course['title'],
            $description !== '' ? $description : (string) ($course['short_description'] ?? 'Master practical data analytics, dashboards, and business case solving with Elldy Academy.'),
            $duration !== '' ? $duration : (string) ($course['duration'] ?? 'Flexible duration'),
            program_absolute_url($course),
        ]
    );
}

function ensure_whatsapp_invite_logs_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS whatsapp_invite_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NULL,
            course_title VARCHAR(190) NULL,
            contact_name VARCHAR(190) NULL,
            phone VARCHAR(40) NOT NULL,
            invite_description TEXT NULL,
            invite_duration VARCHAR(120) NULL,
            message_id VARCHAR(190) NULL,
            status ENUM('queued', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent',
            response_message TEXT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status_updated_at DATETIME NULL,
            INDEX idx_whatsapp_invite_sent_at (sent_at),
            INDEX idx_whatsapp_invite_course (course_id),
            INDEX idx_whatsapp_invite_message_id (message_id)
        )"
    );

    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'whatsapp_invite_logs'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    $missing = [
        'message_id' => 'ADD COLUMN message_id VARCHAR(190) NULL AFTER invite_duration',
        'status_updated_at' => 'ADD COLUMN status_updated_at DATETIME NULL AFTER sent_at',
    ];

    foreach ($missing as $column => $definition) {
        if (!isset($existing[$column])) {
            db()->exec("ALTER TABLE whatsapp_invite_logs {$definition}");
        }
    }

    db()->exec("ALTER TABLE whatsapp_invite_logs MODIFY COLUMN status ENUM('queued', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent'");
}

function log_whatsapp_invite(array $course, array $contact, string $description, string $duration, bool $sent, string $message, string $messageId = ''): void
{
    ensure_whatsapp_invite_logs_table();
    $stmt = db()->prepare(
        "INSERT INTO whatsapp_invite_logs
            (course_id, course_title, contact_name, phone, invite_description, invite_duration, message_id, status, response_message, status_updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        (int) ($course['id'] ?? 0) ?: null,
        (string) ($course['title'] ?? ''),
        (string) ($contact['name'] ?? ''),
        (string) ($contact['phone'] ?? ''),
        $description,
        $duration,
        $messageId !== '' ? $messageId : null,
        $sent ? 'sent' : 'failed',
        $message,
    ]);
}

function send_class_reminder_whatsapp(array $row): bool
{
    $settings = whatsapp_settings();

    if ($settings['reminder_template_name'] === '') {
        $_SESSION['whatsapp_send_error'] = 'Reminder WhatsApp template name is missing. Save the approved Meta template name in Admin > WhatsApp.';
        return false;
    }

    return send_whatsapp_template_message(
        (string) $row['phone'],
        $settings['reminder_template_name'],
        [(string) $row['name'], (string) $row['title'], site_url('login.php')]
    );
}

function send_certificate_eligible_whatsapp(array $row): bool
{
    $settings = whatsapp_settings();

    if ($settings['certificate_template_name'] === '') {
        $_SESSION['whatsapp_send_error'] = 'Certificate WhatsApp template name is missing. Save the approved Meta template name in Admin > WhatsApp.';
        return false;
    }

    $certificateUrl = site_url('certificate_apply.php?enrollment_id=' . (int) $row['enrollment_id']);

    return send_whatsapp_template_message(
        (string) $row['phone'],
        $settings['certificate_template_name'],
        [(string) $row['name'], (string) $row['course_title'], $certificateUrl]
    );
}

function normalize_whatsapp_number(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (strlen($digits) === 10) {
        return '91' . $digits;
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        return '91' . substr($digits, 1);
    }

    return $digits;
}

function ensure_app_analytics_tables(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    db()->exec(
        "CREATE TABLE IF NOT EXISTS app_user_activity (
            user_id INT UNSIGNED PRIMARY KEY,
            login_count INT UNSIGNED NOT NULL DEFAULT 0,
            return_count INT UNSIGNED NOT NULL DEFAULT 0,
            first_login_at DATETIME NULL,
            last_login_at DATETIME NULL,
            last_active_at DATETIME NULL,
            last_return_at DATETIME NULL,
            last_installed_app_at DATETIME NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_app_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS app_installs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            install_key CHAR(64) NOT NULL UNIQUE,
            user_id INT UNSIGNED NULL,
            platform VARCHAR(80) NULL,
            user_agent TEXT NULL,
            first_installed_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            launch_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_app_installs_user (user_id),
            CONSTRAINT fk_app_install_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
}

function record_user_login(int $userId): void
{
    ensure_app_analytics_tables();

    $stmt = db()->prepare(
        "INSERT INTO app_user_activity (user_id, login_count, first_login_at, last_login_at, last_active_at)
         VALUES (?, 1, NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            login_count = login_count + 1,
            last_login_at = NOW(),
            last_active_at = NOW()"
    );
    $stmt->execute([$userId]);
}

function record_user_activity(int $userId, bool $installedApp = false): void
{
    ensure_app_analytics_tables();

    $sessionKey = $installedApp ? 'last_installed_app_activity_at' : 'last_user_activity_at';
    $lastRecordedAt = (int) ($_SESSION[$sessionKey] ?? 0);

    if (time() - $lastRecordedAt < 300) {
        return;
    }

    $_SESSION[$sessionKey] = time();

    $installedSql = $installedApp ? ', last_installed_app_at = NOW()' : '';
    $stmt = db()->prepare(
        "INSERT INTO app_user_activity (user_id, last_active_at, last_installed_app_at)
         VALUES (?, NOW(), " . ($installedApp ? 'NOW()' : 'NULL') . ")
         ON DUPLICATE KEY UPDATE last_active_at = NOW(){$installedSql}"
    );
    $stmt->execute([$userId]);
}

function record_user_return(int $userId): void
{
    ensure_app_analytics_tables();

    $stmt = db()->prepare(
        "INSERT INTO app_user_activity (user_id, return_count, last_return_at, last_active_at)
         VALUES (?, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            return_count = return_count + 1,
            last_return_at = NOW(),
            last_active_at = NOW()"
    );
    $stmt->execute([$userId]);
}

function record_app_install_event(string $installKey, ?int $userId, string $eventType, string $platform): void
{
    ensure_app_analytics_tables();

    if (!preg_match('/^[a-f0-9]{64}$/', $installKey)) {
        return;
    }

    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);
    $isLaunch = $eventType === 'installed_launch';

    $stmt = db()->prepare(
        "INSERT INTO app_installs (install_key, user_id, platform, user_agent, first_installed_at, last_seen_at, launch_count)
         VALUES (?, ?, ?, ?, NOW(), NOW(), ?)
         ON DUPLICATE KEY UPDATE
            user_id = COALESCE(VALUES(user_id), user_id),
            platform = VALUES(platform),
            user_agent = VALUES(user_agent),
            last_seen_at = NOW(),
            launch_count = launch_count + VALUES(launch_count)"
    );
    $stmt->execute([$installKey, $userId, substr($platform, 0, 80), $userAgent, $isLaunch ? 1 : 0]);

    if ($userId) {
        record_user_activity($userId, true);
    }
}

function ensure_login_otp_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS login_otps (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            phone VARCHAR(40) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_otps_user_phone (user_id, phone),
            CONSTRAINT fk_login_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
}

function ensure_user_remember_tokens_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS user_remember_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            selector VARCHAR(64) NOT NULL UNIQUE,
            token_hash VARCHAR(255) NOT NULL,
            user_agent_hash CHAR(64) NULL,
            expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_remember_user (user_id),
            INDEX idx_user_remember_expires (expires_at),
            CONSTRAINT fk_user_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    $schema = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'user_remember_tokens'"
    );
    $columns->execute([$schema]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($existing['user_agent_hash'])) {
        db()->exec("ALTER TABLE user_remember_tokens ADD COLUMN user_agent_hash CHAR(64) NULL AFTER token_hash");
    }

    if (!isset($existing['last_used_at'])) {
        db()->exec("ALTER TABLE user_remember_tokens ADD COLUMN last_used_at DATETIME NULL AFTER expires_at");
    }

    db()->exec('DELETE FROM user_remember_tokens WHERE expires_at < NOW()');
}

function remember_cookie_name(): string
{
    return 'elldy_remember';
}

function remember_cookie_path(): string
{
    $path = site_base_path();
    return ($path === '' ? '' : $path) . '/';
}

function remember_user_agent_hash(): string
{
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function remember_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => remember_cookie_path(),
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function create_remembered_device(int $userId): void
{
    ensure_user_remember_tokens_table();

    $selector = bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(32));
    $expires = time() + AUTH_REMEMBER_SECONDS;

    $stmt = db()->prepare(
        "INSERT INTO user_remember_tokens (user_id, selector, token_hash, user_agent_hash, expires_at)
         VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))"
    );
    $stmt->execute([
        $userId,
        $selector,
        password_hash($token, PASSWORD_DEFAULT),
        remember_user_agent_hash(),
        $expires,
    ]);

    if (!headers_sent()) {
        setcookie(remember_cookie_name(), $selector . ':' . $token, remember_cookie_options($expires));
    }
}

function clear_remembered_device(): void
{
    ensure_user_remember_tokens_table();
    $cookie = (string) ($_COOKIE[remember_cookie_name()] ?? '');
    $parts = explode(':', $cookie, 2);

    if (count($parts) === 2 && preg_match('/^[a-f0-9]{24}$/', $parts[0])) {
        $stmt = db()->prepare('DELETE FROM user_remember_tokens WHERE selector = ?');
        $stmt->execute([$parts[0]]);
    }

    if (!headers_sent()) {
        setcookie(remember_cookie_name(), '', remember_cookie_options(time() - 3600));
    }
}

function user_from_remembered_device(): ?array
{
    $cookie = (string) ($_COOKIE[remember_cookie_name()] ?? '');
    $parts = explode(':', $cookie, 2);

    if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{24}$/', $parts[0]) || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
        return null;
    }

    ensure_user_remember_tokens_table();

    [$selector, $token] = $parts;
    $stmt = db()->prepare(
        "SELECT u.*, rt.id AS remember_token_id, rt.token_hash, rt.user_agent_hash
         FROM user_remember_tokens rt
         JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = ? AND rt.expires_at >= NOW()
         LIMIT 1"
    );
    $stmt->execute([$selector]);
    $row = $stmt->fetch();

    if (!$row || !hash_equals((string) $row['user_agent_hash'], remember_user_agent_hash()) || !password_verify($token, (string) $row['token_hash'])) {
        clear_remembered_device();
        return null;
    }

    $_SESSION['user_id'] = (int) $row['id'];
    record_user_return((int) $row['id']);

    $newToken = bin2hex(random_bytes(32));
    $expires = time() + AUTH_REMEMBER_SECONDS;
    $update = db()->prepare(
        "UPDATE user_remember_tokens
         SET token_hash = ?, expires_at = FROM_UNIXTIME(?), last_used_at = NOW()
         WHERE id = ?"
    );
    $update->execute([password_hash($newToken, PASSWORD_DEFAULT), $expires, (int) $row['remember_token_id']]);

    if (!headers_sent()) {
        setcookie(remember_cookie_name(), $selector . ':' . $newToken, remember_cookie_options($expires));
    }

    return $row;
}

function find_user_by_whatsapp(string $phone): ?array
{
    $normalized = normalize_whatsapp_number($phone);
    $stmt = db()->query('SELECT * FROM users WHERE phone IS NOT NULL AND phone != ""');

    foreach ($stmt->fetchAll() as $user) {
        if (normalize_whatsapp_number((string) $user['phone']) === $normalized) {
            return $user;
        }
    }

    return null;
}

function send_whatsapp_otp(string $phone, string $otp): bool
{
    $settings = whatsapp_settings();
    $token = $settings['access_token'];

    unset($_SESSION['whatsapp_send_error']);

    if ($token === '') {
        $_SESSION['whatsapp_send_error'] = 'WhatsApp access token is missing. Save it in Admin > WhatsApp.';
        return false;
    }

    if (!function_exists('curl_init')) {
        $_SESSION['whatsapp_send_error'] = 'PHP cURL extension is not enabled in XAMPP.';
        return false;
    }

    if ($settings['phone_number_id'] === '') {
        $_SESSION['whatsapp_send_error'] = 'WhatsApp Phone Number ID is missing. Save it in Admin > WhatsApp.';
        return false;
    }

    $to = normalize_whatsapp_number($phone);
    $url = 'https://graph.facebook.com/' . $settings['graph_version'] . '/' . $settings['phone_number_id'] . '/messages';
    $template = $settings['template_name'];

    if ($template !== '') {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $settings['template_language']],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [[
                            'type' => 'text',
                            'text' => $otp,
                        ]],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [[
                            'type' => 'text',
                            'text' => $otp,
                        ]],
                    ],
                ],
            ],
        ];
    } else {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => "Your Elldy Academy login OTP is {$otp}. It is valid for 10 minutes.",
            ],
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        $message = 'Meta WhatsApp API rejected the OTP request.';
        $decoded = json_decode((string) $response, true);
        if (isset($decoded['error']['message'])) {
            $message .= ' ' . $decoded['error']['message'];
        } elseif ($curlError !== '') {
            $message .= ' ' . $curlError;
        }

        $_SESSION['whatsapp_send_error'] = $message;
    }

    return $status >= 200 && $status < 300;
}

function create_login_otp(array $user): bool
{
    ensure_login_otp_table();
    $otp = (string) random_int(100000, 999999);
    $phone = normalize_whatsapp_number((string) $user['phone']);

    $stmt = db()->prepare('INSERT INTO login_otps (user_id, phone, otp_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
    $stmt->execute([(int) $user['id'], $phone, password_hash($otp, PASSWORD_DEFAULT)]);

    return send_whatsapp_otp($phone, $otp);
}

function verify_login_otp(int $userId, string $phone, string $otp): bool
{
    ensure_login_otp_table();
    $normalized = normalize_whatsapp_number($phone);
    $stmt = db()->prepare(
        "SELECT *
         FROM login_otps
         WHERE user_id = ? AND phone = ? AND used_at IS NULL AND expires_at >= NOW()
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $stmt->execute([$userId, $normalized]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($otp, $row['otp_hash'])) {
        return false;
    }

    $update = db()->prepare('UPDATE login_otps SET used_at = NOW() WHERE id = ?');
    $update->execute([(int) $row['id']]);

    return true;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return user_from_remembered_device();
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        clear_remembered_device();
        return null;
    }

    record_user_activity((int) $user['id']);

    return $user;
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please login or create your account to continue.');
        redirect('login.php');
    }

    return $user;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('login.php');
    }

    return $admin;
}

function enrollment_badge(string $status): string
{
    return match ($status) {
        'free_access' => 'First Session Free',
        'payment_pending' => 'Payment Pending',
        'paid' => 'Paid',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function ensure_enrollment_detail_columns(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'enrollments'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    $missing = [
        'program_payment_attempted_at' => 'ADD COLUMN program_payment_attempted_at DATETIME NULL AFTER payment_requested_at',
        'student_background' => 'ADD COLUMN student_background TEXT NULL AFTER payment_requested_at',
        'learning_goals' => 'ADD COLUMN learning_goals TEXT NULL AFTER student_background',
        'completion_expectation' => 'ADD COLUMN completion_expectation TEXT NULL AFTER learning_goals',
        'daily_reminders_enabled' => 'ADD COLUMN daily_reminders_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER completion_expectation',
        'last_reminder_sent_on' => 'ADD COLUMN last_reminder_sent_on DATE NULL AFTER daily_reminders_enabled',
    ];

    foreach ($missing as $column => $definition) {
        if (!isset($existing[$column])) {
            db()->exec("ALTER TABLE enrollments {$definition}");
        }
    }

    db()->exec("ALTER TABLE enrollments MODIFY COLUMN status ENUM('free_access', 'payment_pending', 'paid', 'completed', 'cancelled') NOT NULL DEFAULT 'free_access'");
}

function ensure_course_detail_columns(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $database = db()->query('SELECT DATABASE()')->fetchColumn();
    $columns = db()->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'courses'"
    );
    $columns->execute([$database]);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    $missing = [
        'slug' => 'ADD COLUMN slug VARCHAR(220) NULL UNIQUE AFTER title',
        'learning_plan' => 'ADD COLUMN learning_plan TEXT NULL AFTER description',
        'completion_benefits' => 'ADD COLUMN completion_benefits TEXT NULL AFTER learning_plan',
        'expert_name' => 'ADD COLUMN expert_name VARCHAR(160) NULL AFTER completion_benefits',
        'expert_title' => 'ADD COLUMN expert_title VARCHAR(190) NULL AFTER expert_name',
        'expert_bio' => 'ADD COLUMN expert_bio TEXT NULL AFTER expert_title',
        'expert_photo' => 'ADD COLUMN expert_photo TEXT NULL AFTER expert_bio',
        'promo_video_url' => 'ADD COLUMN promo_video_url TEXT NULL AFTER expert_photo',
        'certification_fee' => 'ADD COLUMN certification_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER fee',
        'discount_fee' => 'ADD COLUMN discount_fee DECIMAL(10,2) NULL DEFAULT NULL AFTER fee',
        'certificate_discount_fee' => 'ADD COLUMN certificate_discount_fee DECIMAL(10,2) NULL DEFAULT NULL AFTER certification_fee',
        'show_fee_details' => 'ADD COLUMN show_fee_details TINYINT(1) NOT NULL DEFAULT 1 AFTER certificate_discount_fee',
        'delivery_type' => "ADD COLUMN delivery_type ENUM('video', 'live_session') NOT NULL DEFAULT 'video' AFTER certificate_discount_fee",
        'certificate_details' => 'ADD COLUMN certificate_details TEXT NULL AFTER delivery_type',
        'certificate_title' => 'ADD COLUMN certificate_title VARCHAR(220) NULL AFTER certificate_details',
        'first_class_link' => 'ADD COLUMN first_class_link TEXT NULL AFTER certificate_title',
    ];

    foreach ($missing as $column => $definition) {
        if (!isset($existing[$column])) {
            db()->exec("ALTER TABLE courses {$definition}");
        }
    }

    $nullableColumns = ['discount_fee', 'certificate_discount_fee'];
    $columnDetails = db()->prepare(
        "SELECT COLUMN_NAME, IS_NULLABLE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'courses' AND COLUMN_NAME IN ('discount_fee', 'certificate_discount_fee')"
    );
    $columnDetails->execute([$database]);
    $details = [];
    foreach ($columnDetails->fetchAll() as $column) {
        $details[$column['COLUMN_NAME']] = $column['IS_NULLABLE'];
    }

    foreach ($nullableColumns as $column) {
        if (($details[$column] ?? 'YES') === 'NO') {
            db()->exec("ALTER TABLE courses MODIFY COLUMN {$column} DECIMAL(10,2) NULL DEFAULT NULL");
            db()->exec("UPDATE courses SET {$column} = NULL WHERE {$column} = 0");
        }
    }

    $textColumns = [
        'short_description',
        'expert_photo',
        'promo_video_url',
        'first_class_link',
    ];
    $textDetails = db()->prepare(
        "SELECT COLUMN_NAME, DATA_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'courses' AND COLUMN_NAME IN ('short_description', 'expert_photo', 'promo_video_url', 'first_class_link')"
    );
    $textDetails->execute([$database]);
    $textColumnTypes = [];
    foreach ($textDetails->fetchAll() as $column) {
        $textColumnTypes[$column['COLUMN_NAME']] = strtolower((string) $column['DATA_TYPE']);
    }

    foreach ($textColumns as $column) {
        if (($textColumnTypes[$column] ?? '') !== 'text') {
            db()->exec("ALTER TABLE courses MODIFY COLUMN {$column} TEXT NULL");
        }
    }

    $slugRows = db()->query('SELECT id, title, slug FROM courses ORDER BY id ASC')->fetchAll();
    $usedSlugs = [];
    $slugUpdate = db()->prepare('UPDATE courses SET slug = ? WHERE id = ?');

    foreach ($slugRows as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            $baseSlug = slugify((string) $row['title']);
            $slug = $baseSlug;
            $suffix = 2;

            while (isset($usedSlugs[$slug])) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }

            $slugUpdate->execute([$slug, (int) $row['id']]);
        }

        $usedSlugs[$slug] = true;
    }

    $platformCourses = [
        'Full Stack Web Development' => [
            'Data Analytics & BI Foundations',
            'Build Excel, SQL, Power BI, and dashboard thinking with business-ready analytics cases.',
            'A practical analytics program for trainees who want to understand business data, clean messy datasets, write SQL, create KPI dashboards, and explain insights to decision makers using real company-style cases.',
            "Business case understanding and KPI framing\nExcel analytics and data cleaning\nSQL queries for business reporting\nPower BI dashboard design\nData storytelling for management\nReal-time sales, finance, and operations cases",
            "Business-ready analytics project portfolio\nPower BI dashboard case study\nCourse completion certificate\nInterview-ready BI and reporting confidence\nReusable templates for KPI, sales, and operations analysis",
            '10 weeks',
            12999.00,
            'https://meet.google.com/data-bi-demo',
        ],
        'Digital Marketing Masterclass' => [
            'Advanced Business Intelligence with Power BI',
            'Design executive BI dashboards, data models, DAX measures, and decision-ready reports.',
            'A focused BI course for trainees who want to move beyond charts and build structured dashboards for sales, marketing, finance, HR, and operations teams using professional BI practices.',
            "Power BI data modelling\nDAX measures and calculated columns\nExecutive dashboard layout\nSales, revenue, and customer analytics\nData refresh and report publishing\nStakeholder presentation with insights",
            "Advanced Power BI project portfolio\nExecutive dashboard presentation\nCourse completion certificate\nBI analyst workflow confidence\nTemplates for business review dashboards",
            '8 weeks',
            15999.00,
            'https://meet.google.com/power-bi-demo',
        ],
        'UI/UX Design Foundations' => [
            'Real-Time Business Analytics Cases',
            'Solve practical business problems using data, analytics logic, BI dashboards, and insight delivery.',
            'A case-led program for trainees who want to think like analytics consultants: understand business problems, ask the right questions, analyse data, prepare dashboards, and recommend actions.',
            "Problem discovery from business scenarios\nCustomer, sales, finance, and operations cases\nRoot-cause analysis with data\nDashboard requirement planning\nInsight writing and recommendation structure\nPresentation of analytics findings",
            "Business case solving portfolio\nAnalytics consulting mindset\nCourse completion certificate\nConfidence to discuss data with business teams\nReusable case-study frameworks",
            '6 weeks',
            9999.00,
            'https://meet.google.com/business-cases-demo',
        ],
    ];

    $courseCount = (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn();

    if ($courseCount > 0) {
        return;
    }

    $update = db()->prepare(
        "UPDATE courses
         SET title = ?, short_description = ?, description = ?, learning_plan = ?, completion_benefits = ?, duration = ?, fee = ?, first_class_link = ?
         WHERE title = ?"
    );

    foreach ($platformCourses as $oldTitle => $course) {
        $update->execute([...$course, $oldTitle]);
    }

    $insert = db()->prepare(
        "INSERT INTO courses (title, short_description, description, learning_plan, completion_benefits, duration, fee, first_class_link, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            short_description = VALUES(short_description),
            description = VALUES(description),
            learning_plan = VALUES(learning_plan),
            completion_benefits = VALUES(completion_benefits),
            duration = VALUES(duration),
            fee = VALUES(fee),
            first_class_link = VALUES(first_class_link),
            is_active = VALUES(is_active)"
    );

    foreach ($platformCourses as $course) {
        $insert->execute($course);
    }
}

function active_courses(int $limit = 12): array
{
    ensure_course_detail_columns();
    $stmt = db()->prepare('SELECT * FROM courses WHERE is_active = 1 ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
