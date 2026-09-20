<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/jwt_helper.php';

// ── Session timeout configuration ─────────────────────────────────────────────
// Pages will timeout after this many seconds of inactivity (30 minutes).
define('SESSION_TIMEOUT', 1800);

/**
 * Check session inactivity timeout.
 * Call BEFORE any auth checks.  Returns true if the session is still valid,
 * or destroys it and returns false if it has expired.
 */
function checkSessionTimeout() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Session expired – destroy cleanly
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
        return false;
    }
    // Refresh last activity timestamp
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Record a login event: stamp last_activity in session and persist last_login to DB.
 * Call this after a successful login (session is already populated).
 */
function recordLogin($db) {
    $_SESSION['last_activity'] = time();
    if (!empty($_SESSION['user_id'])) {
        try {
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([(int) $_SESSION['user_id']]);
        } catch (Exception $e) {
            error_log('recordLogin DB error: ' . $e->getMessage());
        }
    }
}

/**
 * Restore session from JWT token if session is empty but JWT is valid
 */
function restoreSessionFromJWT() {
    $jwtUser = JWTHelper::authenticate();
    if ($jwtUser) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT role, status, email_verified_at, must_change_password FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([(int) $jwtUser['user_id']]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            $verifiedAt = $account['email_verified_at'] ?? null;
            $hasValidVerification = !empty($verifiedAt) && $verifiedAt !== '0000-00-00 00:00:00';
            $requiresVerification = ($account['role'] ?? '') === 'teacher';
            if (!$account || $account['status'] !== 'active' || ($requiresVerification && !$hasValidVerification)) {
                return false;
            }
        } catch (Exception $e) {
            error_log('restoreSessionFromJWT DB error: ' . $e->getMessage());
            return false;
        }

        $_SESSION['user_id'] = $jwtUser['user_id'];
        $_SESSION['role'] = $jwtUser['role'];
        $_SESSION['email'] = $jwtUser['email'] ?? '';
        $_SESSION['name'] = $jwtUser['name'] ?? '';
        $_SESSION['must_change_password'] = !empty($account['must_change_password']) ? 1 : 0;
        
        // Role-specific IDs
        if (isset($jwtUser['teacher_id'])) {
            $_SESSION['teacher_id'] = $jwtUser['teacher_id'];
        }
        if (isset($jwtUser['student_id'])) {
            $_SESSION['student_id'] = $jwtUser['student_id'];
        }
        // Stamp activity so the restored session starts its inactivity clock
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

// Check if user is logged in and is an admin (API - returns JSON errors)
function checkAdminAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Try to restore from JWT
        if (!restoreSessionFromJWT()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Not logged in']);
            exit;
        }
    }
    // Check session timeout
    if (!checkSessionTimeout()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Not admin']);
        exit;
    }
    return true;
}

// Check if user is logged in and is an admin (page - redirects on failure)
function checkAdminPage() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        if (!restoreSessionFromJWT()) {
            header('Location: login.php');
            exit;
        }
    }
    // Check session timeout
    if (!checkSessionTimeout()) {
        header('Location: login.php?timeout=1');
        exit;
    }
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ../unauthorized.php');
        exit;
    }
    return true;
}
/**
 * Teacher Authentication Check
 * Include this file in all teacher pages
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a teacher
function checkTeacherAuth() {
    // Prevent browser caching so auth checks always run on the server
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Try to restore from JWT
        if (!restoreSessionFromJWT()) {
            header('Location: login.php');
            exit;
        }
    }
    // Check session timeout
    if (!checkSessionTimeout()) {
        header('Location: login.php?timeout=1');
        exit;
    }
    if ($_SESSION['role'] !== 'teacher') {
        header('Location: ../unauthorized.php');
        exit;
    }
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME']) !== 'change_password.php') {
        header('Location: change_password.php');
        exit;
    }
    
    return true;
}

// Get teacher ID from session
function getTeacherId() {
    return $_SESSION['teacher_id'] ?? null;
}

// Get teacher info
function getTeacherInfo() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'teacher_id' => $_SESSION['teacher_id'] ?? null,
        'name' => $_SESSION['name'] ?? 'Teacher',
        'email' => $_SESSION['email'] ?? ''
    ];
}

// Check if user is logged in and is a student
function checkStudentAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Try to restore from JWT
        if (!restoreSessionFromJWT()) {
            header('Location: login.php');
            exit;
        }
    }
    // Check session timeout
    if (!checkSessionTimeout()) {
        header('Location: login.php?timeout=1');
        exit;
    }
    if ($_SESSION['role'] !== 'student') {
        header('Location: ../unauthorized.php');
        exit;
    }
    
    return true;
}

// Check student auth for API requests (returns JSON error)
function checkStudentAuthAPI() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Try to restore from JWT
        if (!restoreSessionFromJWT()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Not logged in']);
            exit;
        }
    }
    if ($_SESSION['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Not a student']);
        exit;
    }
    return true;
}

// Get student ID from session
function getStudentId() {
    return $_SESSION['student_id'] ?? null;
}

// Get student info
function getStudentInfo() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'student_id' => $_SESSION['student_id'] ?? null,
        'name' => $_SESSION['name'] ?? 'Student',
        'email' => $_SESSION['email'] ?? ''
    ];
}

// Check teacher auth for API requests (returns JSON error)
function checkTeacherAuthAPI() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Try to restore from JWT
        if (!restoreSessionFromJWT()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Not logged in']);
            exit;
        }
    }
    if ($_SESSION['role'] !== 'teacher') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Not a teacher']);
        exit;
    }
    return true;
}

// Check teacher or admin auth for API requests (returns JSON error)
function checkTeacherOrAdminAuthAPI() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Try to restore from JWT
        if (!restoreSessionFromJWT()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Not logged in']);
            exit;
        }
    }

    if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Teacher or admin access required']);
        exit;
    }

    return true;
}

// ─── Teacher RBAC ─────────────────────────────────────────────────────────────

/**
 * All defined teacher permissions.
 */
function getAllTeacherPermissions() {
    return [
        'can_manage_questions' => 'Manage Questions',
        'can_manage_quizzes'   => 'Manage Quizzes',
        'can_view_reports'     => 'View Reports & Game Progress',
        'can_manage_classes'   => 'Manage Classes',
    ];
}

/**
 * Fetch the granted permissions for the current teacher from the DB.
 * Returns an array of permission keys (strings).
 */
function getTeacherPermissions($db) {
    $teacher_id = $_SESSION['teacher_id'] ?? null;
    if (!$teacher_id) return [];
    try {
        $stmt = $db->prepare("SELECT permission FROM teacher_permissions WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('RBAC getTeacherPermissions error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check a single permission for the current teacher.
 * Returns true/false (does NOT exit).
 */
function hasTeacherPermission($db, $permission) {
    $teacher_id = $_SESSION['teacher_id'] ?? null;
    if (!$teacher_id) return false;
    try {
        $stmt = $db->prepare(
            "SELECT 1 FROM teacher_permissions WHERE teacher_id = ? AND permission = ? LIMIT 1"
        );
        $stmt->execute([$teacher_id, $permission]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('RBAC hasTeacherPermission error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enforce a single permission for a page request.
 * Redirects to unauthorized.php if the teacher lacks it.
 */
function requireTeacherPermission($db, $permission) {
    if (!hasTeacherPermission($db, $permission)) {
        header('Location: ../unauthorized.php?reason=permission');
        exit;
    }
    return true;
}

// ──────────────────────────────────────────────────────────────────────────────

// Ensure the current session teacher is the owner of the quiz_id provided.
// If not owner, respond with 403 and exit.
function ensureQuizOwner($db, $quiz_id) {
    if (!$quiz_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Quiz ID required']);
        exit;
    }

    // If session role is not teacher, deny — owner-only
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    $teacher_id = $_SESSION['teacher_id'] ?? null;
    $stmt = $db->prepare("SELECT teacher_id FROM quizzes WHERE quiz_id = ? LIMIT 1");
    $stmt->execute([$quiz_id]);
    $row = $stmt->fetch();
    if (!$row || intval($row['teacher_id']) !== intval($teacher_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
    return true;
}
