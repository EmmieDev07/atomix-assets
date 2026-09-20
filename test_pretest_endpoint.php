<?php
// Quick test of the get_by_chapter endpoint

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'atomix_db');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test query: get pretest for chapter 1
    $stmt = $pdo->prepare('
        SELECT cp.pretest_id, cp.is_active
        FROM chapter_pretests cp
        WHERE cp.chapter_id = 1 AND cp.is_active = 1
        LIMIT 1
    ');
    $stmt->execute();
    $pretest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pretest) {
        echo "Found pretest for chapter 1: pretest_id = " . $pretest['pretest_id'] . PHP_EOL;
        
        // Get questions
        $qStmt = $pdo->prepare('
            SELECT question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index
            FROM chapter_pretest_questions
            WHERE pretest_id = ?
            ORDER BY question_order, pq_id
        ');
        $qStmt->execute([$pretest['pretest_id']]);
        $dbQuestions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Found " . count($dbQuestions) . " questions" . PHP_EOL;
        
        if (!empty($dbQuestions)) {
            echo "First question format:" . PHP_EOL;
            $q = $dbQuestions[0];
            $unityQuestion = [
                'questionText' => $q['question_text'],
                'answers' => [
                    (string)$q['answer_0'],
                    (string)$q['answer_1'],
                    (string)$q['answer_2'],
                    (string)$q['answer_3'],
                ],
                'correctAnswerIndex' => (int)$q['correct_answer_index'],
            ];
            echo json_encode($unityQuestion, JSON_PRETTY_PRINT) . PHP_EOL;
        }
    } else {
        echo "No active pretest found for chapter 1" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>
