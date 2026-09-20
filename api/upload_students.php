<?php
// upload_students.php: Handles Excel/CSV upload, validation, and preview
ob_start();
require_once '../config/database.php';
$db = Database::getInstance()->getConnection();
require_once '../includes/auth_check.php';
checkAdminAuth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['student_file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

if ($_FILES['student_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server limit.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form limit.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
    ];
    $errorCode = $_FILES['student_file']['error'];
    $errorMessage = $uploadErrors[$errorCode] ?? 'Unknown upload error';
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed: ' . $errorMessage]);
    exit;
}

$classId = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
if ($classId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Class ID is required']);
    exit;
}

 $classCheck = $db->prepare("SELECT class_id FROM classes WHERE class_id = ? LIMIT 1");
 $classCheck->execute([$classId]);

if (!$classCheck->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid class or access denied']);
    exit;
}

$file = $_FILES['student_file']['tmp_name'];
$ext = strtolower(pathinfo($_FILES['student_file']['name'], PATHINFO_EXTENSION));

require_once '../vendor/autoload.php'; // For PhpSpreadsheet when present
use PhpOffice\PhpSpreadsheet\IOFactory;

// Enable error reporting for troubleshooting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (($ext === 'xlsx' || $ext === 'xls') && !class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode([
        'error' => 'PHP ZipArchive extension is required to import Excel files. Please enable ZipArchive in php.ini and restart your server, or upload a CSV file instead.'
    ]);
    exit;
}

// Custom error handler to capture errors and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'error' => "PHP Error [$errno]: $errstr in $errfile on line $errline"
    ]);
    exit;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Uncaught Exception: ' . $exception->getMessage()
    ]);
    exit;
});

$rows = [];
if ($ext === 'xlsx' || $ext === 'xls') {
    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($sheet->toArray() as $row) {
            $rows[] = $row;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read Excel file: ' . $e->getMessage()]);
        exit;
    }
} elseif ($ext === 'csv') {
    // Parse CSV
    if (($handle = fopen($file, 'r')) !== false) {
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read CSV file']);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only Excel (.xlsx) or CSV allowed.']);
    exit;
}

// Validate rows
$preview = [];
$classes = $db->query("SELECT class_name FROM classes")->fetchAll(PDO::FETCH_COLUMN);

// Map header columns to normalized keys (case-insensitive, trimmed)
if (count($rows) === 0) {
    ob_end_clean();
    echo json_encode(['preview' => []]);
    exit;
}

$rawHeader = $rows[0];
$normalizedHeader = [];
foreach ($rawHeader as $col) {
    $normalizedHeader[] = strtolower(trim($col));
}

// Define expected keys
$expectedKeys = [
    'first name' => null,
    'last name' => null,
    'gender' => null
];

// Build a mapping from normalized header to expected keys
$headerMap = [];
foreach ($normalizedHeader as $i => $col) {
    foreach ($expectedKeys as $expected => $v) {
        if ($col === $expected) {
            $headerMap[$expected] = $i;
        }
    }
}

for ($i = 1; $i < count($rows); $i++) {
    $rowData = $rows[$i];
    $row = [];
    foreach ($expectedKeys as $key => $v) {
        $row[$key] = isset($headerMap[$key]) && isset($rowData[$headerMap[$key]]) ? $rowData[$headerMap[$key]] : null;
    }

    // Skip fully empty rows.
    $isCompletelyEmpty = true;
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            $isCompletelyEmpty = false;
            break;
        }
    }
    if ($isCompletelyEmpty) {
        continue;
    }

    $valid = true;
    $errors = [];

    // Validate required fields.
    if (empty(trim((string) $row['first name']))) {
        $valid = false;
        $errors[] = 'Missing first name';
    }
    if (empty(trim((string) $row['last name']))) {
        $valid = false;
        $errors[] = 'Missing last name';
    }

    // Validate gender.
    $validGenders = ['male', 'female', 'others', 'other'];
    $gender = strtolower(trim((string)$row['gender']));
    if (!$gender) {
        $valid = false;
        $errors[] = 'Missing gender (must be male, female, or other)';
    } else if (!in_array($gender, $validGenders, true)) {
        $valid = false;
        $errors[] = 'Invalid gender (must be male, female, or other)';
    }

    $preview[] = [
        'row' => $row,
        'valid' => $valid,
        'errors' => $errors
    ];
}

ob_end_clean();
echo json_encode(['preview' => $preview]);
