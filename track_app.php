<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

verify_csrf();

$eventType = trim((string) ($_POST['event_type'] ?? ''));
$installKey = strtolower(trim((string) ($_POST['install_key'] ?? '')));
$platform = trim((string) ($_POST['platform'] ?? ''));

if (!in_array($eventType, ['appinstalled', 'installed_launch'], true) || $installKey === '') {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

$user = current_user();
record_app_install_event($installKey, $user ? (int) $user['id'] : null, $eventType, $platform);

echo json_encode(['ok' => true]);
