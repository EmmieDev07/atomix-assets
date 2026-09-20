<?php
/**
 * Auth API — login, captcha, me, logout
 * Stateless: uses JWT (Bearer token or cookie).
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/jwt_helper.php';
require_once '../config/math_captcha.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── GET captcha ───────────────────────────────────────────────
        case 'captcha':
            $_SESSION['math_captcha'] = MathCaptcha::generate();
            $c = $_SESSION['math_captcha'];
            echo json_encode([
                'success'  => true,
                'question' => "{$c['num1']} {$c['operator']} {$c['num2']} = ?"
            ]);
            break;

        // ── POST login ────────────────────────────────────────────────
        case 'login':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $username      = trim($input['username']       ?? '');
            $password      = $input['password']            ?? '';
            $captchaAnswer = trim($input['captcha_answer'] ?? '');
            $role          = trim($input['role']           ?? 'admin');

            if (!$username || !$password) {
                throw new Exception('Username and password are required');
            }
            if (!in_array($role, ['admin', 'teacher'], true)) {
                throw new Exception('Invalid role');
            }
            if (!isset($_SESSION['math_captcha']) ||
                !MathCaptcha::verify($captchaAnswer, $_SESSION['math_captcha']['answer'])) {
                throw new Exception('Please solve the math problem correctly');
            }

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT u.*, t.teacher_id
                 FROM users u
                 LEFT JOIN teachers t ON u.user_id = t.user_id
                 WHERE u.username = ? AND u.role = ? AND u.status = 'active'
                 LIMIT 1"
            );
            $stmt->execute([$username, $role]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                throw new Exception('Invalid username or password');
            }
            if ($role !== 'admin' && empty($user['email_verified_at'])) {
                throw new Exception('Please verify your email before logging in');
            }

            $userData = [
                'user_id' => $user['user_id'],
                'email'   => $user['email'],
                'name'    => $user['username'],
                'role'    => $user['role'],
            ];
            if ($role === 'teacher' && $user['teacher_id']) {
                $userData['teacher_id'] = $user['teacher_id'];
            }

            $tokens = JWTHelper::generateTokenPair($userData);
            $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
               ->execute([$user['user_id']]);

            // Refresh captcha
            $_SESSION['math_captcha'] = MathCaptcha::generate();

            echo json_encode([
                'success'       => true,
                'token'         => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'user'          => [
                    'user_id'    => $user['user_id'],
                    'name'       => $user['username'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                    'teacher_id' => $user['teacher_id'] ?? null,
                ]
            ]);
            break;

        // ── GET me ────────────────────────────────────────────────────
        case 'me':
            $jwtUser = JWTHelper::authenticate();
            if (!$jwtUser) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            echo json_encode(['success' => true, 'user' => $jwtUser]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
