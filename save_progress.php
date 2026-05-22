<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_user();
verify_csrf();
ensure_learning_progress_table();
ensure_course_detail_columns();

$enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
$materialId = (int) ($_POST['material_id'] ?? 0);
$watchedSeconds = max(0, (float) ($_POST['watched_seconds'] ?? 0));
$durationSeconds = max(0, (float) ($_POST['duration_seconds'] ?? 0));

$stmt = db()->prepare(
    "SELECT e.id, e.course_id, e.user_id, e.status, c.fee, c.discount_fee, m.material_type
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     JOIN materials m ON m.course_id = e.course_id
     WHERE e.id = ? AND e.user_id = ? AND m.id = ? AND e.status != 'cancelled'"
);
$stmt->execute([$enrollmentId, (int) $user['id'], $materialId]);
$row = $stmt->fetch();

if (!$row || ($row['material_type'] ?? 'video') !== 'video') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false]);
    exit;
}

$percent = $durationSeconds > 0 ? min(100, round(($watchedSeconds / $durationSeconds) * 100, 2)) : 0;
$isCompleted = $percent >= 90 ? 1 : 0;

$save = db()->prepare(
    "INSERT INTO learning_progress
        (enrollment_id, user_id, course_id, material_id, watched_seconds, duration_seconds, progress_percent, is_completed, completed_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))
     ON DUPLICATE KEY UPDATE
        watched_seconds = GREATEST(watched_seconds, VALUES(watched_seconds)),
        duration_seconds = GREATEST(duration_seconds, VALUES(duration_seconds)),
        progress_percent = GREATEST(progress_percent, VALUES(progress_percent)),
        is_completed = GREATEST(is_completed, VALUES(is_completed)),
        completed_at = IF(completed_at IS NULL AND VALUES(is_completed) = 1, NOW(), completed_at)"
);
$save->execute([
    $enrollmentId,
    (int) $user['id'],
    (int) $row['course_id'],
    $materialId,
    $watchedSeconds,
    $durationSeconds,
    $percent,
    $isCompleted,
    $isCompleted,
]);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'progress_percent' => $percent, 'is_completed' => (bool) $isCompleted]);
