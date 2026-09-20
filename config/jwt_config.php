<?php
/**
 * JWT Configuration
 * Atomix Learning Platform
 * 
 * IMPORTANT: In production, store JWT_SECRET_KEY in environment variables
 */

// Generate a secure secret key (use openssl rand -base64 64 in terminal to generate)
define('JWT_SECRET_KEY', 'atomix_jwt_secret_key_2026_change_this_in_production_' . md5(__DIR__));

// Token expiration times (in seconds)
define('JWT_ACCESS_TOKEN_EXPIRY', 3600);        // 1 hour
define('JWT_REFRESH_TOKEN_EXPIRY', 3600);        // 1 hour

// Token issuer
define('JWT_ISSUER', 'atomix-learning-platform');

// Algorithm for signing tokens
define('JWT_ALGORITHM', 'HS256');

// Cookie settings
define('JWT_COOKIE_SECURE', false);              // Set to true in production with HTTPS
define('JWT_COOKIE_HTTPONLY', true);             // Prevent JavaScript access
define('JWT_COOKIE_SAMESITE', 'Strict');         // CSRF protection

// Role-specific cookie names (prevents conflicts between admin, teacher, student)
define('JWT_ADMIN_ACCESS_COOKIE', 'atomix_admin_access_token');
define('JWT_ADMIN_REFRESH_COOKIE', 'atomix_admin_refresh_token');

define('JWT_TEACHER_ACCESS_COOKIE', 'atomix_teacher_access_token');
define('JWT_TEACHER_REFRESH_COOKIE', 'atomix_teacher_refresh_token');

define('JWT_STUDENT_ACCESS_COOKIE', 'atomix_student_access_token');
define('JWT_STUDENT_REFRESH_COOKIE', 'atomix_student_refresh_token');

// Legacy constants (kept for backwards compatibility)
define('JWT_COOKIE_NAME', 'atomix_jwt');
define('JWT_REFRESH_COOKIE_NAME', 'atomix_refresh_token');
