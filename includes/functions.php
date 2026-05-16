<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/whatsapp.php';

if (session_status() === PHP_SESSION_NONE) {
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

function site_base_path(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDir = rtrim($scriptDir, '/');

    if (str_ends_with($scriptDir, '/admin')) {
        $scriptDir = substr($scriptDir, 0, -6);
    }

    return $scriptDir === '' ? '' : $scriptDir;
}

function public_url(string $path = ''): string
{
    $base = site_base_path();
    $path = ltrim($path, '/');

    return ($base === '' ? '' : $base) . '/' . $path;
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

function program_url(array $course): string
{
    $slug = trim((string) ($course['slug'] ?? ''));

    if ($slug === '') {
        $slug = slugify((string) ($course['title'] ?? 'program'));
    }

    return public_url('program/' . rawurlencode($slug));
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
            certificate_url VARCHAR(255) NULL,
            certificate_code VARCHAR(80) NULL,
            issued_at DATETIME NULL,
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
}

function certificate_badge(string $status): string
{
    return match ($status) {
        'requested' => 'Requested',
        'payment_pending' => 'Payment Pending',
        'approved' => 'Approved',
        'issued' => 'Issued',
        'rejected' => 'Rejected',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
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

function build_certificate_pdf(array $row, string $certificateCode): string
{
    $name = trim((string) $row['name']);
    $title = trim((string) $row['title']);
    $completionDate = date('F j, Y');
    $safeTitle = strlen($title) > 58 ? substr($title, 0, 55) . '...' : $title;

    $content = '';
    $content .= "q\n";
    $content .= "0.97 0.98 1 rg 0 0 842 595 re f\n";
    $content .= "0.04 0.16 0.34 RG 4 w 24 24 794 547 re S\n";
    $content .= "0.81 0.65 0.34 RG 1.5 w 38 38 766 519 re S\n";
    $content .= "0.04 0.16 0.34 rg 56 506 730 1 re f\n";
    $content .= "0.81 0.65 0.34 rg 56 495 730 3 re f\n";
    $content .= "0.04 0.16 0.34 rg\n";
    $content .= centered_pdf_text(421, 525, 'ELLDY ACADEMY', 18, 'F2');
    $content .= centered_pdf_text(421, 489, 'CERTIFICATE OF COMPLETION', 28, 'F2');
    $content .= "0.38 0.42 0.5 rg\n";
    $content .= centered_pdf_text(421, 458, 'Issued by Arklytics Solutions and Innovations | Elldy Platform', 12);
    $content .= "0.15 0.18 0.24 rg\n";
    $content .= centered_pdf_text(421, 404, 'This certificate is proudly presented to', 15);
    $content .= "0.04 0.16 0.34 rg\n";
    $content .= centered_pdf_text(421, 360, $name, 30, 'F2');
    $content .= "0.81 0.65 0.34 RG 1.2 w 180 346 m 662 346 l S\n";
    $content .= "0.15 0.18 0.24 rg\n";
    $content .= centered_pdf_text(421, 312, 'for successfully completing the program', 15);
    $content .= centered_pdf_text(421, 279, $safeTitle, 21, 'F2');
    $content .= "0.38 0.42 0.5 rg\n";
    $content .= centered_pdf_text(421, 243, 'with demonstrated learning in analytics, dashboards, reporting, and business intelligence.', 13);
    $content .= "0.04 0.16 0.34 rg\n";
    $content .= pdf_text(72, 126, 'Completion Date', 11, 'F2');
    $content .= pdf_text(72, 105, $completionDate, 14);
    $content .= pdf_text(326, 126, 'Certificate ID', 11, 'F2');
    $content .= pdf_text(326, 105, $certificateCode, 14);
    $content .= pdf_text(612, 126, 'Authorized Credential', 11, 'F2');
    $content .= pdf_text(612, 105, 'Arklytics + Elldy', 14);
    $content .= "0.81 0.65 0.34 rg 649 173 62 62 re f\n";
    $content .= "1 1 1 rg\n";
    $content .= centered_pdf_text(680, 203, 'BI', 20, 'F2');
    $content .= "Q\n";

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>';
    $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

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
        "SELECT cr.*, e.id AS enrollment_id, e.status AS enrollment_status, u.name, c.title
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

    $downloadUrl = public_url('download_certificate.php?enrollment_id=' . $enrollmentId);
    if (($certificate['status'] ?? '') === 'issued' && ($certificate['certificate_url'] ?? '') !== $downloadUrl) {
        $urlUpdate = db()->prepare('UPDATE certificate_requests SET certificate_url = ? WHERE enrollment_id = ?');
        $urlUpdate->execute([$downloadUrl, $enrollmentId]);
        $certificate['certificate_url'] = $downloadUrl;
    }

    $expectedIssuedPath = __DIR__ . '/../assets/certificates/issued/certificate-' . $enrollmentId . '.pdf';

    if (
        $certificate['status'] !== 'issued'
        || empty($certificate['certificate_url'])
        || !is_file($expectedIssuedPath)
    ) {
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

    if (!isset($existing['reminder_template_name'])) {
        db()->exec("ALTER TABLE whatsapp_settings ADD COLUMN reminder_template_name VARCHAR(120) NULL AFTER enrollment_template_name");
    }

    $stmt = db()->prepare(
        "INSERT INTO whatsapp_settings (id, business_account_id, phone_number_id, access_token, template_name, enrollment_template_name, reminder_template_name, template_language, graph_version)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute([
        WHATSAPP_BUSINESS_ACCOUNT_ID,
        WHATSAPP_PHONE_NUMBER_ID,
        whatsapp_access_token(),
        whatsapp_otp_template_name(),
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
        'reminder_template_name' => trim((string) ($settings['reminder_template_name'] ?? '')),
        'template_language' => trim((string) ($settings['template_language'] ?? 'en')) ?: 'en',
        'graph_version' => trim((string) ($settings['graph_version'] ?? WHATSAPP_GRAPH_VERSION)) ?: WHATSAPP_GRAPH_VERSION,
    ];
}

function save_whatsapp_settings(array $data): void
{
    ensure_whatsapp_settings_table();

    $stmt = db()->prepare(
        "UPDATE whatsapp_settings
         SET business_account_id = ?, phone_number_id = ?, access_token = ?, template_name = ?, enrollment_template_name = ?, reminder_template_name = ?, template_language = ?, graph_version = ?
         WHERE id = 1"
    );
    $stmt->execute([
        trim((string) ($data['business_account_id'] ?? '')),
        trim((string) ($data['phone_number_id'] ?? '')),
        trim((string) ($data['access_token'] ?? '')),
        trim((string) ($data['template_name'] ?? '')),
        trim((string) ($data['enrollment_template_name'] ?? '')),
        trim((string) ($data['reminder_template_name'] ?? '')),
        trim((string) ($data['template_language'] ?? 'en')) ?: 'en',
        trim((string) ($data['graph_version'] ?? WHATSAPP_GRAPH_VERSION)) ?: WHATSAPP_GRAPH_VERSION,
    ]);
}

function send_whatsapp_template_message(string $phone, string $templateName, array $bodyParameters = []): bool
{
    $settings = whatsapp_settings();
    unset($_SESSION['whatsapp_send_error']);

    if ($settings['access_token'] === '' || $settings['phone_number_id'] === '' || $templateName === '') {
        $_SESSION['whatsapp_send_error'] = 'WhatsApp template messaging is not fully configured.';
        return false;
    }

    if (!function_exists('curl_init')) {
        $_SESSION['whatsapp_send_error'] = 'PHP cURL extension is not enabled in XAMPP.';
        return false;
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

    if ($bodyParameters) {
        $payload['template']['components'] = [[
            'type' => 'body',
            'parameters' => array_map(
                fn (string $value): array => ['type' => 'text', 'text' => $value],
                array_map('strval', $bodyParameters)
            ),
        ]];
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
    }

    return $status >= 200 && $status < 300;
}

function send_enrollment_whatsapp(array $user, array $course): bool
{
    $settings = whatsapp_settings();

    return send_whatsapp_template_message(
        (string) $user['phone'],
        $settings['enrollment_template_name'],
        [(string) $user['name'], (string) $course['title'], site_url('login.php')]
    );
}

function send_class_reminder_whatsapp(array $row): bool
{
    $settings = whatsapp_settings();

    return send_whatsapp_template_message(
        (string) $row['phone'],
        $settings['reminder_template_name'],
        [(string) $row['name'], (string) $row['title'], site_url('login.php')]
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
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
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
        'expert_photo' => 'ADD COLUMN expert_photo VARCHAR(255) NULL AFTER expert_bio',
        'certification_fee' => 'ADD COLUMN certification_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER fee',
    ];

    foreach ($missing as $column => $definition) {
        if (!isset($existing[$column])) {
            db()->exec("ALTER TABLE courses {$definition}");
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
