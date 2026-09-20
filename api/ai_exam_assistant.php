<?php
// Enable full error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbPath = __DIR__ . '/../config/database.php';
if (!file_exists($dbPath)) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "Database config file not found at: $dbPath"]);
    exit;
}

require_once $dbPath;

ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database Connection Failed: ' . $e->getMessage()]);
    exit;
}

define('GEMINI_API_KEY', 'AQ.Ab8RN6J7cKKJQeHzTJtki2Fqqsi3AwuT-4URsjSN85L9ZM9DSg');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$action = $_GET['action'] ?? $data['action'] ?? 'test';

// Map exam section types to question_type stored in questions_master
function normalizeType($type) {
    $type = strtolower($type);
    if ($type === 'multiple_choice' || $type === 'mcq') return 'mcq';
    if ($type === 'true_false' || $type === 'tf') return 'true_false';
    if ($type === 'identification' || $type === 'short_answer') return 'short_answer';
    return $type;
}

// TEST ACTION: Endpoint verification
if ($action === 'test') {
    try {
        $stmt = $db->query("
            SELECT 
                l.lesson_id, 
                l.lesson_title, 
                c.chapter_title, 
                COUNT(q.question_id) AS total_questions
            FROM lessons l
            INNER JOIN chapters c ON l.chapter_id = c.chapter_id
            INNER JOIN questions_master q ON q.lesson_id = l.lesson_id
            GROUP BY l.lesson_id, l.lesson_title, c.chapter_title
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'API Connected Successfully',
            'action' => 'test',
            'record_count' => count($results),
            'sample_data' => array_slice($results, 0, 3)
        ], JSON_PRETTY_PRINT);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Query Failed: ' . $e->getMessage()]);
        exit;
    }
}

// ACTION 1: Get available lessons filtered by question type
if ($action === 'get_lessons') {
    $targetType = normalizeType($_GET['section_type'] ?? 'mcq');

    try {
        $stmt = $db->prepare("
            SELECT 
                l.lesson_id, 
                l.lesson_title, 
                c.chapter_title, 
                COUNT(q.question_id) AS total_questions
            FROM lessons l
            INNER JOIN chapters c ON l.chapter_id = c.chapter_id
            INNER JOIN questions_master q ON q.lesson_id = l.lesson_id
            WHERE LOWER(q.question_type) = ?
            GROUP BY l.lesson_id, l.lesson_title, c.chapter_title
            HAVING total_questions > 0
            ORDER BY c.chapter_order ASC, l.lesson_order ASC
        ");
        $stmt->execute([$targetType]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'filter_type' => $targetType, 'lessons' => $lessons]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ACTION 2: Auto-select questions per lesson and format for exam section
if ($action === 'auto_build_section') {
    $lessonConfig = $data['lesson_config'] ?? [];
    $targetType   = normalizeType($data['section_type'] ?? 'mcq');

    if (empty($lessonConfig)) {
        echo json_encode(['success' => false, 'error' => 'Please specify a question quantity for at least one lesson.']);
        exit;
    }

    $allSelectedQuestions = [];

    try {
        foreach ($lessonConfig as $config) {
            $lId = (int)$config['lesson_id'];
            $requestedCount = (int)$config['count'];

            if ($requestedCount <= 0) continue;

            // Fetch questions from questions_master for this lesson & type
            $stmt = $db->prepare("
                SELECT q.*, c.chapter_title, l.lesson_title
                FROM questions_master q
                INNER JOIN lessons l ON q.lesson_id = l.lesson_id
                INNER JOIN chapters c ON l.chapter_id = c.chapter_id
                WHERE q.lesson_id = ? AND LOWER(q.question_type) = ?
                ORDER BY RAND()
            ");
            $stmt->execute([$lId, $targetType]);
            $bankItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($bankItems)) continue;

            if (count($bankItems) <= $requestedCount) {
                $chosenItems = $bankItems;
            } else {
                // AI selection for balance
                $summary = array_map(function($q) {
                    return [
                        'id' => $q['question_id'],
                        'text' => $q['question_text']
                    ];
                }, $bankItems);

                $prompt = "Select exactly {$requestedCount} non-duplicate question IDs from this JSON list. Return ONLY a valid JSON array of chosen integer question IDs. Example: [1, 4, 9]";
                $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode(GEMINI_API_KEY);

                $payload = [
                    'contents' => [
                        ['parts' => [['text' => $prompt . "\n\n" . json_encode($summary)]]]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json'
                    ]
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $chosenIds = [];
                if ($httpCode === 200) {
                    $resData = json_decode($response, true);
                    $rawText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
                    $chosenIds = json_decode($cleaned, true);
                }

                if (is_array($chosenIds) && !empty($chosenIds)) {
                    $chosenItems = array_filter($bankItems, function($item) use ($chosenIds) {
                        return in_array((int)$item['question_id'], array_map('intval', $chosenIds));
                    });
                } else {
                    shuffle($bankItems);
                    $chosenItems = array_slice($bankItems, 0, $requestedCount);
                }
            }

            // Fetch choices from quiz_choices table for all types
            foreach ($chosenItems as $q) {
                $qId = (int)$q['question_id'];
                $choicesStmt = $db->prepare("SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY choice_id ASC");
                $choicesStmt->execute([$qId]);
                $choices = $choicesStmt->fetchAll(PDO::FETCH_ASSOC);

                $q['choices_data'] = $choices;
                $allSelectedQuestions[] = $q;
            }
        }

        if (empty($allSelectedQuestions)) {
            echo json_encode(['success' => false, 'error' => 'No matching questions found in selected lessons.']);
            exit;
        }

        // Format for exam section rendering
        $formattedQuestions = [];
        $labels = ['A', 'B', 'C', 'D'];

        foreach ($allSelectedQuestions as $q) {
            $qText = $q['question_text'];

            if ($targetType === 'mcq') {
                $choicesList = [];
                $correctLetter = 'A';

                foreach ($q['choices_data'] as $idx => $c) {
                    $choicesList[] = $c['choice_text'];
                    if (!empty($c['is_correct'])) {
                        $correctLetter = $labels[$idx] ?? 'A';
                    }
                }

                $formattedQuestions[] = [
                    'questionText'  => $qText,
                    'choices'       => $choicesList,
                    'correctAnswer' => $correctLetter
                ];
            } else {
                // Fallback scan across common column names in database schemas
                $ansKey = $q['answer'] ?? $q['correct_answer'] ?? $q['correct_answer_text'] ?? '';

                // If column was blank, check quiz_choices table for an item marked is_correct = 1
                if (empty($ansKey) && !empty($q['choices_data'])) {
                    foreach ($q['choices_data'] as $c) {
                        if (!empty($c['is_correct'])) {
                            $ansKey = $c['choice_text'];
                            break;
                        }
                    }
                }

                $formattedQuestions[] = [
                    'questionText'  => $qText,
                    'choices'       => [],
                    'correctAnswer' => $ansKey
                ];
            }
        }

        echo json_encode(['success' => true, 'questions' => $formattedQuestions, 'total' => count($formattedQuestions)]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}