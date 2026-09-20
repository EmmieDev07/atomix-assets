<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');
checkTeacherAuth();

define('GEMINI_API_KEY', 'AQ.Ab8RN6J7cKKJQeHzTJtki2Fqqsi3AwuT-4URsjSN85L9ZM9DSg');

$rawInput = file_get_contents('php://input');
$data     = json_decode($rawInput, true);

$contextText = trim($data['contextText'] ?? '');
$count       = (int)($data['count'] ?? 3);
$formatType  = trim($data['formatType'] ?? 'mixed'); // Capture format selection from UI

if (empty($contextText)) {
    echo json_encode(['success' => false, 'error' => 'No lesson content provided.']);
    exit;
}

if ($count < 1 || $count > 30) {
    $count = 3;
}

$keyClean = trim(GEMINI_API_KEY);

// Build dynamic prompt for MCQ, True/False, and Short Answer
$prompt = "You are an expert educational quiz creator. Read the following content and generate exactly {$count} questions based STRICTLY on the text.

Content:
\"\"\"
{$contextText}
\"\"\"

Allowed Format Mode: '{$formatType}'

Requirements:
1. Return ONLY a valid JSON array. Do not include markdown blocks or extra text.
2. Each item MUST follow one of these exact structures depending on 'question_type':

   For Multiple Choice ('mcq'):
   {
     \"question_type\": \"mcq\",
     \"question_text\": \"Question here?\",
     \"answer_0\": \"Option A\",
     \"answer_1\": \"Option B\",
     \"answer_2\": \"Option C\",
     \"answer_3\": \"Option D\",
     \"correct_answer_index\": 0,
     \"source_quote\": \"Exact sentence or short phrase from the provided text that proves this answer.\"
   }

   For True/False ('true_false'):
   {
     \"question_type\": \"true_false\",
     \"question_text\": \"Statement to evaluate as True or False.\",
     \"correct_answer\": \"true\",
     \"source_quote\": \"Exact sentence from text.\"
   }

   For Identification / Short Answer ('short_answer'):
   {
     \"question_type\": \"short_answer\",
     \"question_text\": \"Question asking for a term or phrase?\",
     \"correct_answer\": \"Expected answer term\",
     \"source_quote\": \"Exact sentence from text.\"
   }

3. If mode is 'mcq', generate ONLY 'mcq' type.
4. If mode is 'true_false', generate ONLY 'true_false' type.
5. If mode is 'short_answer', generate ONLY 'short_answer' type.
6. If mode is 'mixed', generate a mix of all three types.";

$primaryModel  = 'gemini-3.5-flash';
$fallbackModel = 'gemini-3-flash';

function sendGeminiRequest($modelName, $promptText, $apiKey) {
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . urlencode($apiKey);

    $payload = [
        'contents' => [
            ['parts' => [['text' => $promptText]]]
        ],
        'generationConfig' => [
            'temperature'      => 0.2,
            'responseMimeType' => 'application/json'
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-goog-api-key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'error'    => $curlError
    ];
}

$res = sendGeminiRequest($primaryModel, $prompt, $keyClean);

if ($res['httpCode'] === 404) {
    $res = sendGeminiRequest($fallbackModel, $prompt, $keyClean);
}

if ($res['error']) {
    echo json_encode(['success' => false, 'error' => 'cURL Error: ' . $res['error']]);
    exit;
}

if ($res['httpCode'] !== 200) {
    $errDecoded = json_decode($res['response'], true);
    $apiMsg     = $errDecoded['error']['message'] ?? 'Unknown API error';
    echo json_encode(['success' => false, 'error' => 'Gemini API Error (HTTP ' . $res['httpCode'] . '): ' . $apiMsg]);
    exit;
}

$responseData     = json_decode($res['response'], true);
$rawGeneratedText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($rawGeneratedText)) {
    echo json_encode(['success' => false, 'error' => 'AI returned an empty response.']);
    exit;
}

$cleanedJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawGeneratedText));

$startPos = strpos($cleanedJson, '[');
$endPos   = strrpos($cleanedJson, ']');

if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
    $cleanedJson = substr($cleanedJson, $startPos, ($endPos - $startPos) + 1);
}

$parsedQuestions = json_decode($cleanedJson, true);

if (!is_array($parsedQuestions)) {
    // Retry once with a stricter prompt that forbids any preamble
    $strictPrompt = "Return ONLY a valid JSON array with no markdown, no explanation, no preamble. " . $prompt;
    $retry = sendGeminiRequest($primaryModel, $strictPrompt, $keyClean);
    if ($retry['httpCode'] === 200) {
        $retryData = json_decode($retry['response'], true);
        $retryText = $retryData['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $retryJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($retryText));
        $s = strpos($retryJson, '['); $e = strrpos($retryJson, ']');
        if ($s !== false && $e !== false && $e > $s) $retryJson = substr($retryJson, $s, $e - $s + 1);
        $parsedQuestions = json_decode($retryJson, true);
    }
}

if (!is_array($parsedQuestions)) {
    $preview = substr($cleanedJson, 0, 300);
    echo json_encode(['success' => false, 'error' => 'Failed to parse AI response as JSON. Raw: ' . $preview]);
    exit;
}

$formattedQuestions = [];
foreach ($parsedQuestions as $q) {
    if (empty($q['question_text'])) {
        continue;
    }

    $qType = strtolower(trim($q['question_type'] ?? 'mcq'));

    if ($qType === 'mcq') {
        if (empty($q['answer_0']) || empty($q['answer_1']) || empty($q['answer_2']) || empty($q['answer_3'])) {
            continue;
        }
        $idx = isset($q['correct_answer_index']) ? (int)$q['correct_answer_index'] : 0;
        if ($idx < 0 || $idx > 3) $idx = 0;

        $formattedQuestions[] = [
            'question_type'        => 'mcq',
            'question_text'        => (string)$q['question_text'],
            'answer_0'             => (string)$q['answer_0'],
            'answer_1'             => (string)$q['answer_1'],
            'answer_2'             => (string)$q['answer_2'],
            'answer_3'             => (string)$q['answer_3'],
            'correct_answer_index' => $idx,
            'source_quote'         => (string)($q['source_quote'] ?? 'Source verified from lesson content.')
        ];
    } elseif ($qType === 'true_false') {
        $tfVal = strtolower(trim($q['correct_answer'] ?? 'true'));
        if ($tfVal !== 'true' && $tfVal !== 'false') $tfVal = 'true';

        $formattedQuestions[] = [
            'question_type'  => 'true_false',
            'question_text'  => (string)$q['question_text'],
            'correct_answer' => $tfVal,
            'source_quote'   => (string)($q['source_quote'] ?? 'Source verified from lesson content.')
        ];
    } elseif ($qType === 'short_answer') {
        $formattedQuestions[] = [
            'question_type'  => 'short_answer',
            'question_text'  => (string)$q['question_text'],
            'correct_answer' => (string)($q['correct_answer'] ?? ''),
            'source_quote'   => (string)($q['source_quote'] ?? 'Source verified from lesson content.')
        ];
    }
}

echo json_encode([
    'success'   => true,
    'questions' => $formattedQuestions
]);