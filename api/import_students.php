<?php
// import_students.php: Imports valid students from preview
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ob_start();
require_once '../config/database.php';
require_once '../includes/email_service.php';
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
checkAdminAuth();

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$students = $data['students'] ?? [];
$class_id = $data['class_id'] ?? null;

$db = Database::getInstance()->getConnection();

if (!$class_id) {
	http_response_code(400);
	echo json_encode(['error' => 'Class ID is required']);
	exit;
}

// Verify class exists
$classCheck = $db->prepare("SELECT class_id FROM classes WHERE class_id = ? LIMIT 1");
$classCheck->execute([$class_id]);
if (!$classCheck->fetchColumn()) {
	http_response_code(400);
	echo json_encode(['error' => 'Invalid class or access denied']);
	exit;
}
$existingUsernames = $db->query("SELECT username FROM users")->fetchAll(PDO::FETCH_COLUMN);

$result = [
	'imported' => 0,
	'skipped' => 0,
	'failed' => 0,
	'reasons' => []
];
foreach ($students as $row) {
	$first = trim($row['first name'] ?? '');
	$last = trim($row['last name'] ?? '');
	$gender = $row['gender'] ?? null;
	$studentFullName = trim($first . ' ' . $last);

	if ($first === '' || $last === '') {
		$result['failed']++;
		$result['reasons'][] = "Missing first or last name for a row";
		continue;
	}

	try {
		$db->beginTransaction();

		// Generate a unique username: lastname + 3 random digits
		$base = preg_replace('/[^a-z0-9]+/i', '', strtolower($last));
		do {
			$username = $base . rand(100, 999);
		} while (in_array($username, $existingUsernames));
		$existingUsernames[] = $username;

		$raw_password = 'changeme';
		$password = password_hash($raw_password, PASSWORD_DEFAULT);

		$stmt = $db->prepare("INSERT INTO users (email, password, username, role, must_change_password, status, email_verified_at) VALUES (NULL, ?, ?, 'student', 1, 'active', NOW())");
		$stmt->execute([$password, $username]);
		$user_id = $db->lastInsertId();
		$stmt = $db->prepare("INSERT INTO students (user_id, first_name, last_name, gender) VALUES (?, ?, ?, ?)");
		$stmt->execute([$user_id, $first, $last, $gender]);
		$student_id = $db->lastInsertId();
		$stmt = $db->prepare("INSERT INTO class_students (student_id, class_id) VALUES (?, ?)");
		$stmt->execute([$student_id, $class_id]);

		$db->commit();

		$result['imported']++;
	} catch (Exception $ex) {
		$db->rollBack();
		$result['failed']++;
		$result['reasons'][] = "$studentFullName: " . $ex->getMessage();
	}
}

while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode(['result' => $result]);
