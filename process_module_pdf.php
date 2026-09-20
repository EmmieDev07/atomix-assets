<?php
session_start();
require_once 'config/database.php';

// Include Composer Autoloader
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();

$chapterId = (int)($_POST['chapter_id'] ?? 0);

if ($chapterId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Please select a valid chapter.']);
    exit;
}

if (!isset($_FILES['module_pdf']) || $_FILES['module_pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'PDF upload failed or no file selected.']);
    exit;
}

$file = $_FILES['module_pdf'];
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($fileExt !== 'pdf') {
    echo json_encode(['success' => false, 'error' => 'File must be a .pdf document.']);
    exit;
}

// 1. Target Folder Setup
$uploadDir = 'uploads/modules/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 2. Save PDF File
$newFileName = 'module_ch' . $chapterId . '_' . time() . '.pdf';
$targetFilePath = $uploadDir . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
    echo json_encode(['success' => false, 'error' => 'Could not save PDF file to disk.']);
    exit;
}

// 3. Extract Text using Smalot PDFParser
$extractedText = '';

try {
    if (class_exists('\Smalot\PdfParser\Parser')) {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($targetFilePath);
        $extractedText = $pdf->getText();
    } else {
        echo json_encode(['success' => false, 'error' => 'Smalot PDFParser is not loaded. Ensure vendor/autoload.php exists.']);
        exit;
    }

    // Clean up excessive whitespace and keep readable characters
    $extractedText = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $extractedText);
    $extractedText = trim(preg_replace('/\s+/', ' ', $extractedText));

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'PDF Parsing Error: ' . $e->getMessage()]);
    exit;
}

if (strlen($extractedText) < 50) {
    echo json_encode([
        'success' => false, 
        'error' => 'Could not extract readable text. The PDF might be scanned/image-based or password-protected.'
    ]);
    exit;
}

// 4. Update Database
try {
    $stmt = $db->prepare("UPDATE chapters SET pdf_file_path = ?, lesson_content = ? WHERE chapter_id = ?");
    $stmt->execute([$targetFilePath, $extractedText, $chapterId]);

    echo json_encode([
        'success' => true,
        'message' => 'Module PDF uploaded and clean text extracted successfully!',
        'text_length' => strlen($extractedText),
        'preview' => substr($extractedText, 0, 300) . '...'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database update error: ' . $e->getMessage()]);
}