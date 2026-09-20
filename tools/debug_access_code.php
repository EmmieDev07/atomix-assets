<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();
$code = trim($_GET['code'] ?? '');
header('Content-Type: text/plain; charset=utf-8');

echo "Server time: " . date('Y-m-d H:i:s') . " (" . date_default_timezone_get() . ")\n\n";
if ($code === '') {
    echo "Usage: /finalweb/tools/debug_access_code.php?code=YOURCODE\n";
    exit;
}

try {
    $stmt = $db->prepare(
        "SELECT gac.code_id, gac.teacher_id, gac.quiz_id, gac.access_code, gac.is_active, gac.created_at, q.start_time, q.end_time
         FROM game_access_codes gac
         JOIN quizzes q ON gac.quiz_id = q.quiz_id
         WHERE gac.access_code = ?
         LIMIT 1"
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "No row found for code: $code\n";
        exit;
    }

    print_r($row);

    $now = new DateTime();
    echo "\nNow: " . $now->format('Y-m-d H:i:s') . "\n";

    $startTime = $row['start_time'] ? new DateTime($row['start_time']) : null;
    $endTime = $row['end_time'] ? new DateTime($row['end_time']) : null;

    echo "Start time: " . ($startTime ? $startTime->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "End time:   " . ($endTime ? $endTime->format('Y-m-d H:i:s') : 'NULL') . "\n";

    $computedActive = 1;
    if ($startTime && $now < $startTime) $computedActive = 0;
    if ($endTime && $now > $endTime) $computedActive = 0;

    echo "\nDB is_active: " . ((int)$row['is_active']) . "\n";
    echo "Computed is_active (based on times): " . $computedActive . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
