<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function elldy_token_error(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    elldy_token_error(405, 'Method not allowed');
}

try {
    $admin = current_admin();
    if (!$admin) {
        elldy_token_error(401, 'Admin login required');
    }

    $csrf = $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
    if (!is_string($csrf) || $csrf === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        elldy_token_error(403, 'Invalid CSRF token');
    }
    session_write_close();

    $credential = trim((string) getenv('ELLDY_EMBED_SERVER_CREDENTIAL'));
    if ($credential === '' || preg_match('/[\r\n]/', $credential) || !function_exists('curl_init')) {
        elldy_token_error(503, 'Analytics is not configured');
    }

    $request = curl_init('https://elldy.com/secure-embed/427b8160-df90-440b-980a-5ea89ec9184a/token/');
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $credential,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'subject' => 'admin:' . $admin['id'],
            // Admin-wide reporting: do not scope dataset rows to a user ID.
            // The Elldy embed policy must also permit access to all rows.
            'attributes' => (object) [],
        ], JSON_THROW_ON_ERROR),
    ]);
    $body = curl_exec($request);
    $status = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);

    if ($body === false || $status < 200 || $status >= 300) {
        elldy_token_error(502, 'Analytics token service unavailable');
    }
    $payload = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
    if (!is_object($payload)) {
        elldy_token_error(502, 'Invalid analytics token response');
    }
    echo json_encode($payload, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    elldy_token_error(502, 'Unable to load analytics');
}
