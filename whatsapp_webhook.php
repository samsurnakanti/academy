<?php
require_once __DIR__ . '/includes/functions.php';

ensure_whatsapp_settings_table();
ensure_whatsapp_invite_logs_table();

$settings = whatsapp_settings();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

    if ($mode === 'subscribe' && $settings['webhook_verify_token'] !== '' && hash_equals($settings['webhook_verify_token'], $token)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    exit('Webhook verification failed.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid JSON.');
}

foreach (($payload['entry'] ?? []) as $entry) {
    foreach (($entry['changes'] ?? []) as $change) {
        $statuses = $change['value']['statuses'] ?? [];

        foreach ($statuses as $statusRow) {
            $messageId = (string) ($statusRow['id'] ?? '');
            $status = (string) ($statusRow['status'] ?? '');
            $timestamp = (int) ($statusRow['timestamp'] ?? 0);
            $errorMessage = '';

            if (!empty($statusRow['errors'][0]['title'])) {
                $errorMessage = (string) $statusRow['errors'][0]['title'];
            } elseif (!empty($statusRow['errors'][0]['message'])) {
                $errorMessage = (string) $statusRow['errors'][0]['message'];
            }

            update_whatsapp_invite_delivery_status($messageId, $status, $timestamp, $errorMessage);
        }
    }
}

http_response_code(200);
echo 'EVENT_RECEIVED';
