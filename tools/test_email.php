<?php
/**
 * Quick email test – delete this file after testing!
 * Access via: http://localhost/finalweb/tools/test_email.php
 */
require_once __DIR__ . '/../includes/email_service.php';

$result = sendStudentWelcomeEmail(
    'atomixinteractive933@gmail.com', // ← change to YOUR email to receive the test
    'Juan dela Cruz',
    'testpass123',
    'Teacher Maria Santos',
    'teacher@school.edu'
);

if ($result) {
    echo '✅ Email sent successfully! Check your inbox.';
} else {
    echo '❌ Email failed. Check PHP error log for details.';
}
