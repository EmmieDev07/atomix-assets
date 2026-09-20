<?php
/**
 * JWT Helper Class
 * Atomix Learning Platform
 * 
 * Handles JWT token generation, validation, and management
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/jwt_config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class JWTHelper {
    
    /**
     * Get cookie names based on role
     * 
     * @param string $role User role (admin, teacher, student)
     * @return array Array with 'access' and 'refresh' cookie names
     */
    public static function getCookieNames(string $role): array {
        switch ($role) {
            case 'admin':
                return [
                    'access' => JWT_ADMIN_ACCESS_COOKIE,
                    'refresh' => JWT_ADMIN_REFRESH_COOKIE
                ];
            case 'teacher':
                return [
                    'access' => JWT_TEACHER_ACCESS_COOKIE,
                    'refresh' => JWT_TEACHER_REFRESH_COOKIE
                ];
            case 'student':
                return [
                    'access' => JWT_STUDENT_ACCESS_COOKIE,
                    'refresh' => JWT_STUDENT_REFRESH_COOKIE
                ];
            default:
                return [
                    'access' => JWT_COOKIE_NAME,
                    'refresh' => JWT_REFRESH_COOKIE_NAME
                ];
        }
    }
    /**
     * Generate an access token for a user
     * 
     * @param array $userData User data to encode in token
     * @return string JWT token
     */
    public static function generateAccessToken(array $userData): string {
        $issuedAt = time();
        $expiresAt = $issuedAt + JWT_ACCESS_TOKEN_EXPIRY;
        
        $payload = [
            'iss' => JWT_ISSUER,                    // Issuer
            'iat' => $issuedAt,                     // Issued at
            'exp' => $expiresAt,                    // Expiration
            'nbf' => $issuedAt,                     // Not before
            'sub' => $userData['user_id'],          // Subject (user ID)
            'data' => [
                'user_id' => $userData['user_id'],
                'role' => $userData['role'],
                'email' => $userData['email'] ?? '',
                'name' => $userData['name'] ?? '',
                // Role-specific IDs
                'teacher_id' => $userData['teacher_id'] ?? null,
                'student_id' => $userData['student_id'] ?? null,
            ]
        ];
        
        return JWT::encode($payload, JWT_SECRET_KEY, JWT_ALGORITHM);
    }
    
    /**
     * Generate a refresh token
     * 
     * @param int $userId User ID
     * @param string $role User role
     * @return string Refresh token
     */
    public static function generateRefreshToken(int $userId, string $role): string {
        $issuedAt = time();
        $expiresAt = $issuedAt + JWT_REFRESH_TOKEN_EXPIRY;
        
        $payload = [
            'iss' => JWT_ISSUER,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'nbf' => $issuedAt,
            'sub' => $userId,
            'type' => 'refresh',
            'role' => $role,
            'jti' => bin2hex(random_bytes(16))      // Unique token ID
        ];
        
        return JWT::encode($payload, JWT_SECRET_KEY, JWT_ALGORITHM);
    }
    
    /**
     * Validate and decode a JWT token
     * 
     * @param string $token JWT token to validate
     * @return object|null Decoded token payload or null if invalid
     */
    public static function validateToken(string $token): ?object {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, JWT_ALGORITHM));
            return $decoded;
        } catch (ExpiredException $e) {
            // Token has expired
            return null;
        } catch (SignatureInvalidException $e) {
            // Invalid signature
            return null;
        } catch (BeforeValidException $e) {
            // Token not yet valid
            return null;
        } catch (\Exception $e) {
            // Other errors
            return null;
        }
    }
    
    /**
     * Check if a token is expired
     * 
     * @param string $token JWT token
     * @return bool True if expired
     */
    public static function isTokenExpired(string $token): bool {
        try {
            // Temporarily decode without validation to check expiry
            $parts = explode('.', $token);
            if (count($parts) !== 3) return true;
            
            $payload = json_decode(base64_decode($parts[1]));
            return $payload->exp < time();
        } catch (\Exception $e) {
            return true;
        }
    }
    
    /**
     * Set JWT cookies with role-specific names
     * 
     * @param string $accessToken Access token
     * @param string $refreshToken Refresh token
     * @param string $role User role (admin, teacher, student)
     */
    public static function setTokenCookies(string $accessToken, string $refreshToken, string $role = ''): void {
        $cookies = self::getCookieNames($role);
        
        $cookieOptions = [
            'expires' => time() + JWT_ACCESS_TOKEN_EXPIRY,
            'path' => '/',
            'secure' => JWT_COOKIE_SECURE,
            'httponly' => JWT_COOKIE_HTTPONLY,
            'samesite' => JWT_COOKIE_SAMESITE
        ];
        
        setcookie($cookies['access'], $accessToken, $cookieOptions);
        
        // Refresh token has longer expiry
        $cookieOptions['expires'] = time() + JWT_REFRESH_TOKEN_EXPIRY;
        setcookie($cookies['refresh'], $refreshToken, $cookieOptions);
    }
    
    /**
     * Clear JWT cookies (logout)
     * 
     * @param string $role User role (admin, teacher, student)
     */
    public static function clearTokenCookies(string $role = ''): void {
        $cookies = self::getCookieNames($role);
        
        $cookieOptions = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => JWT_COOKIE_SECURE,
            'httponly' => JWT_COOKIE_HTTPONLY,
            'samesite' => JWT_COOKIE_SAMESITE
        ];
        
        setcookie($cookies['access'], '', $cookieOptions);
        setcookie($cookies['refresh'], '', $cookieOptions);
    }
    
    /**
     * Get token from request (cookie or Authorization header)
     * Checks all role-specific cookies and Authorization header
     * 
     * @return string|null Token or null if not found
     */
    public static function getTokenFromRequest(): ?string {
        // First check Authorization header (for API requests)
        $headers = self::getAuthorizationHeader();
        if ($headers && preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
            return $matches[1];
        }
        
        // Check role-specific cookies
        $roles = ['admin', 'teacher', 'student'];
        foreach ($roles as $role) {
            $cookies = self::getCookieNames($role);
            if (isset($_COOKIE[$cookies['access']])) {
                return $_COOKIE[$cookies['access']];
            }
        }
        
        // Legacy cookie fallback
        if (isset($_COOKIE[JWT_COOKIE_NAME])) {
            return $_COOKIE[JWT_COOKIE_NAME];
        }
        
        return null;
    }
    
    /**
     * Get refresh token from request
     * Checks all role-specific refresh cookies
     * 
     * @return string|null Refresh token or null
     */
    public static function getRefreshTokenFromRequest(): ?string {
        $roles = ['admin', 'teacher', 'student'];
        foreach ($roles as $role) {
            $cookies = self::getCookieNames($role);
            if (isset($_COOKIE[$cookies['refresh']])) {
                return $_COOKIE[$cookies['refresh']];
            }
        }
        
        // Legacy cookie fallback
        return $_COOKIE[JWT_REFRESH_COOKIE_NAME] ?? null;
    }
    
    /**
     * Get Authorization header
     * 
     * @return string|null
     */
    private static function getAuthorizationHeader(): ?string {
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        
        // Apache specific
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $headers = array_change_key_case($headers, CASE_LOWER);
            if (isset($headers['authorization'])) {
                return trim($headers['authorization']);
            }
        }
        
        return null;
    }
    
    /**
     * Authenticate request using JWT
     * Returns user data if valid, null otherwise
     * 
     * @return array|null User data or null
     */
    public static function authenticate(): ?array {
        $token = self::getTokenFromRequest();
        
        if (!$token) {
            return null;
        }
        
        $decoded = self::validateToken($token);
        
        if (!$decoded || !isset($decoded->data)) {
            return null;
        }
        
        return (array) $decoded->data;
    }
    
    /**
     * Try to refresh the access token using refresh token
     * 
     * @param callable $getUserCallback Callback to get fresh user data
     * @return array|null New tokens or null
     */
    public static function refreshAccessToken(callable $getUserCallback): ?array {
        $refreshToken = self::getRefreshTokenFromRequest();
        
        if (!$refreshToken) {
            return null;
        }
        
        $decoded = self::validateToken($refreshToken);
        
        if (!$decoded || !isset($decoded->type) || $decoded->type !== 'refresh') {
            return null;
        }
        
        // Get fresh user data
        $userData = $getUserCallback($decoded->sub, $decoded->role);
        
        if (!$userData) {
            return null;
        }
        
        // Generate new tokens
        $newAccessToken = self::generateAccessToken($userData);
        $newRefreshToken = self::generateRefreshToken($userData['user_id'], $userData['role']);
        
        // Set new cookies with role-specific names
        self::setTokenCookies($newAccessToken, $newRefreshToken, $userData['role']);
        
        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'user' => $userData
        ];
    }
    
    /**
     * Require authentication - redirect or return error if not authenticated
     * 
     * @param string $role Required role (optional)
     * @param string $redirectUrl URL to redirect to if not authenticated
     * @param bool $isApi Whether this is an API request
     * @return array User data if authenticated
     */
    public static function requireAuth(string $role = '', string $redirectUrl = '', bool $isApi = false): array {
        $userData = self::authenticate();
        
        if (!$userData) {
            if ($isApi) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid or expired token']);
                exit;
            }
            
            if ($redirectUrl) {
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
        
        // Check role if specified
        if ($role && $userData['role'] !== $role) {
            if ($isApi) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Insufficient permissions']);
                exit;
            }
            
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        
        return $userData;
    }
    
    /**
     * Generate both access and refresh tokens for a user
     * 
     * @param array $userData User data
     * @return array Access and refresh tokens
     */
    public static function generateTokenPair(array $userData): array {
        $accessToken = self::generateAccessToken($userData);
        $refreshToken = self::generateRefreshToken($userData['user_id'], $userData['role']);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => JWT_ACCESS_TOKEN_EXPIRY,
            'token_type' => 'Bearer'
        ];
    }
}
