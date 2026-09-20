<?php
require __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();
$code = $argv[1] ?? null;
if (!$code) {
    echo "Usage: php inspect_quiz_code.php <ACCESS_CODE>\n";
    exit(1);
}
try {
    $stmt = $db->prepare(
        "SELECT q.*, gac.*
         FROM quizzes q
         LEFT JOIN game_access_codes gac ON q.quiz_id = gac.quiz_id
         WHERE gac.access_code = ?"
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "No row found for access code: $code\n";
        exit(0);
    }
    echo "Server time: " . date('Y-m-d H:i:s T') . "\n";
    foreach ($row as $k => $v) {
        echo $k . ': ' . ($v === null ? 'NULL' : $v) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
