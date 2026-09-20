<?php
ob_start();

session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

checkTeacherAuth();

define('GEMINI_API_KEY', 'AQ.Ab8RN6J7cKKJQeHzTJtki2Fqqsi3AwuT-4URsjSN85L9ZM9DSg');

$rawInput  = file_get_contents('php://input');
$data      = json_decode($rawInput, true);
$questions = $data['questions'] ?? [];

if (empty($questions) || !is_array($questions)) {
    echo json_encode(['success' => false, 'error' => 'No questions provided for auto-tagging.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Fetch all lessons so AI knows what lesson options exist
    $lessons = $db->query("
        SELECT l.lesson_id, l.lesson_title, c.chapter_title 
        FROM lessons l
        JOIN chapters c ON l.chapter_id = c.chapter_id
        ORDER BY c.chapter_order, l.lesson_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lessons)) {
        // Fallback if no lessons exist in DB
        foreach ($questions as &$q) {
            $q['matched_lesson_id'] = 0;
        }
        echo json_encode(['success' => true, 'questions' => $questions]);
        exit;
    }

    // Format available lessons list for Gemini
    $lessonsListStr = "";
    foreach ($lessons as $ls) {
        $lessonsListStr .= "ID: {$ls['lesson_id']} | Chapter: {$ls['chapter_title']} | Lesson: {$ls['lesson_title']}\n";
    }

    // Build prompt for AI to analyze and match lesson_id
    $questionsJson = json_encode($questions, JSON_PRETTY_PRINT);
    
    $prompt = "You are an educational curriculum classifier.
Below is a list of available lessons and a list of quiz questions generated from a chapter.

AVAILABLE LESSON TARGETS:
{$lessonsListStr}

QUESTIONS TO CLASSIFY:
{$questionsJson}

INSTRUCTIONS:
1. For EVERY question in the list, analyze its text and source quote.
2. Select the single best matching 'lesson_id' from the AVAILABLE LESSON TARGETS above.
3. Return ONLY a JSON array containing the exact same questions with a new integer field added: \"matched_lesson_id\".
4. Do not alter any other fields or text in the questions.";

    // Send payload to Gemini API
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode(GEMINI_API_KEY);

    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature'      => 0.1,
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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $resData = json_decode($response, true);
        $rawText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        
        $startPos = strpos($cleaned, '[');
        $endPos   = strrpos($cleaned, ']');
        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $cleaned = substr($cleaned, $startPos, ($endPos - $startPos) + 1);
        }

        $taggedQuestions = json_decode($cleaned, true);

        if (is_array($taggedQuestions)) {
            echo json_encode(['success' => true, 'questions' => $taggedQuestions]);
            exit;
        }
    }

    // Fallback if AI tagging response failed: assign default lesson
    $defaultLessonId = (int)$lessons[0]['lesson_id'];
    foreach ($questions as &$q) {
        $q['matched_lesson_id'] = $defaultLessonId;
    }

    echo json_encode(['success' => true, 'questions' => $questions]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Auto-tagging error: ' . $e->getMessage()
    ]);
}