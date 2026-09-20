<?php
/**
 * Email Service - Atomix Learning Platform
 * Sends welcome emails to newly created students using PHPMailer + SMTP.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail_config.php';
require_once __DIR__ . '/../config/app_config.php';

function sendStudentWelcomeEmail(
    string $studentEmail,
    string $studentName,
    string $rawPassword,
    string $teacherName,
    string $teacherEmail
): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($studentEmail, $studentName);
        $mail->addReplyTo($teacherEmail, $teacherName);

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Atomix - Your Account Details';
        $mail->Body    = buildWelcomeEmailHtml($studentName, $studentEmail, $rawPassword, $teacherName, $teacherEmail);
        $mail->AltBody = buildWelcomeEmailText($studentName, $studentEmail, $rawPassword, $teacherName, $teacherEmail);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('EmailService: Could not send to ' . $studentEmail . ' - ' . $mail->ErrorInfo);
        return false;
    }
}

function buildWelcomeEmailHtml(
    string $studentName,
    string $studentEmail,
    string $rawPassword,
    string $teacherName,
    string $teacherEmail
): string {
    $sName  = htmlspecialchars($studentName,  ENT_QUOTES, 'UTF-8');
    $sEmail = htmlspecialchars($studentEmail, ENT_QUOTES, 'UTF-8');
    $sPass  = htmlspecialchars($rawPassword,  ENT_QUOTES, 'UTF-8');
    $tName  = htmlspecialchars($teacherName,  ENT_QUOTES, 'UTF-8');
    $tEmail = htmlspecialchars($teacherEmail, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Welcome to Atomix</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">'
        . '<tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">'
        . '<tr><td style="background:#1a73e8;padding:28px 32px;text-align:center;">'
        . '<h1 style="margin:0;color:#fff;font-size:26px;">Atomix Learning Platform</h1></td></tr>'
        . '<tr><td style="padding:32px;">'
        . '<p style="font-size:16px;color:#333;">Hello <strong>' . $sName . '</strong>,</p>'
        . '<p style="font-size:15px;color:#555;">Your teacher, <strong>' . $tName . '</strong>'
        . ' (<a href="mailto:' . $tEmail . '" style="color:#1a73e8;">' . $tEmail . '</a>),'
        . ' has created an account for you on the <strong>Atomix Learning Platform</strong>.</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;border-left:4px solid #1a73e8;border-radius:4px;margin:24px 0;">'
        . '<tr><td style="padding:20px;">'
        . '<p style="margin:0 0 10px 0;font-size:15px;color:#333;"><strong>Your Login Details</strong></p>'
        . '<p style="margin:4px 0;font-size:14px;color:#555;"><strong>Email / Username:</strong> ' . $sEmail . '</p>'
        . '<p style="margin:4px 0;font-size:14px;color:#555;"><strong>Password:</strong> ' . $sPass . '</p>'
        . '</td></tr></table>'
        . '<p style="font-size:14px;color:#e53935;">You will be asked to change your password after your first login.</p>'
        . '<p style="font-size:14px;color:#555;">If you have questions, contact your teacher at'
        . ' <a href="mailto:' . $tEmail . '" style="color:#1a73e8;">' . $tEmail . '</a>.</p>'
        . '<p style="font-size:14px;color:#555;margin-top:24px;">Best regards,<br><strong>The Atomix Team</strong></p>'
        . '</td></tr>'
        . '<tr><td style="background:#f4f6f9;padding:16px 32px;text-align:center;">'
        . '<p style="margin:0;font-size:12px;color:#999;">This email was sent automatically.</p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function buildWelcomeEmailText(
    string $studentName,
    string $studentEmail,
    string $rawPassword,
    string $teacherName,
    string $teacherEmail
): string {
    return "Welcome to Atomix Learning Platform!\n\n"
        . "Hello {$studentName},\n\n"
        . "Your teacher, {$teacherName} ({$teacherEmail}), has created an account for you.\n\n"
        . "Your Login Details:\n"
        . "  Email / Username : {$studentEmail}\n"
        . "  Password         : {$rawPassword}\n\n"
        . "IMPORTANT: You will be asked to change your password after your first login.\n\n"
        . "If you have questions, contact your teacher at {$teacherEmail}.\n\n"
        . "Best regards,\nThe Atomix Team";
}

function buildAppBaseUrl(): string {
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname($scriptPath), '/');
    if ($basePath === '/' || $basePath === '.' || $basePath === '') {
        $basePath = '';
    } elseif (preg_match('~/(admin|api|student|teacher)$~', $basePath)) {
        $basePath = preg_replace('~/(admin|api|student|teacher)$~', '', $basePath);
    }

    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function createEmailVerificationTokenData(int $expiresHours = 24): array {
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $expiresHours . ' hours');

    return [
        'token' => $token,
        'token_hash' => hash('sha256', $token),
        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
    ];
}

function isValidEmailAddress(string $email): bool {
    $email = trim($email);
    if ($email === '' || mb_strlen($email) > 255) {
        return false;
    }

    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return false;
    }

    [$localPart, $domain] = $parts;
    $localPart = trim($localPart);
    $domain = trim($domain);
    if ($localPart === '' || $domain === '') {
        return false;
    }

    if (function_exists('idn_to_ascii')) {
        $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($asciiDomain !== false) {
            $domain = $asciiDomain;
        }
    }

    return filter_var($localPart . '@' . $domain, FILTER_VALIDATE_EMAIL) !== false;
}

function isEmailDomainDeliverable(string $email, ?string &$reason = null): bool {
    $reason = null;
    $domain = trim(substr(strrchr($email, '@') ?: '', 1), ". \t\n\r\0\x0B");
    if ($domain === '') {
        $reason = 'Email must contain a valid domain.';
        return false;
    }

    $reservedDomains = [
        'localhost',
        'localdomain',
        'example.com',
        'example.net',
        'example.org',
        'invalid',
        'test',
        'school.local',
    ];

    if (in_array(strtolower($domain), $reservedDomains, true)) {
        $reason = 'Please use a real email address, not a placeholder or local domain.';
        return false;
    }

    if (function_exists('idn_to_ascii')) {
        $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($asciiDomain !== false) {
            $domain = $asciiDomain;
        }
    }

    $dnsChecked = false;
    if (function_exists('checkdnsrr')) {
        if (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')) {
            return true;
        }
        $dnsChecked = true;
    }

    if (function_exists('getmxrr')) {
        $mxHosts = [];
        if (getmxrr($domain, $mxHosts) && !empty($mxHosts)) {
            return true;
        }
        $dnsChecked = true;
    }

    if (function_exists('dns_get_record')) {
        $records = dns_get_record($domain, DNS_MX);
        if (!empty($records)) {
            return true;
        }
        $dnsChecked = true;
    }

    if (!$dnsChecked) {
        return true;
    }

    $reason = 'Email domain is not active or cannot receive mail.';
    return false;
}

function sendAccountVerificationEmail(
    string $recipientEmail,
    string $recipientName,
    ?string $rawPassword,
    string $verificationUrl,
    string $senderName
): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Atomix Account';

        $rName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
        $rEmail = htmlspecialchars($recipientEmail, ENT_QUOTES, 'UTF-8');
        $rPass = $rawPassword !== null ? htmlspecialchars($rawPassword, ENT_QUOTES, 'UTF-8') : null;
        $sName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $verifyUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Verify Your Account</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">'
            . '<tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">'
            . '<tr><td style="background:#1a73e8;padding:28px 32px;text-align:center;">'
            . '<h1 style="margin:0;color:#fff;font-size:26px;">Atomix Learning Platform</h1></td></tr>'
            . '<tr><td style="padding:32px;">'
            . '<p style="font-size:16px;color:#333;">Hello <strong>' . $rName . '</strong>,</p>'
            . '<p style="font-size:15px;color:#555;">Your account was created by <strong>' . $sName . '</strong>.</p>';

        if ($rPass !== null) {
            $mail->Body .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;border-left:4px solid #1a73e8;border-radius:4px;margin:24px 0;">'
                . '<tr><td style="padding:20px;">'
                . '<p style="margin:0 0 10px 0;font-size:15px;color:#333;"><strong>Your Login Details</strong></p>'
                . '<p style="margin:4px 0;font-size:14px;color:#555;"><strong>Email / Username:</strong> ' . $rEmail . '</p>'
                . '<p style="margin:4px 0;font-size:14px;color:#555;"><strong>Temporary Password:</strong> ' . $rPass . '</p>'
                . '</td></tr></table>';
        }

        $mail->Body .= '<p style="font-size:14px;color:#555;">Before logging in, verify your email address using the button below.</p>'
            . '<p style="text-align:center;margin:28px 0;">'
            . '<a href="' . $verifyUrl . '" style="display:inline-block;background:#1a73e8;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;">Verify Email</a>'
            . '</p>'
            . '<p style="font-size:12px;color:#64748b;word-break:break-all;">If the button does not work, open this link: ' . $verifyUrl . '</p>'
            . '<p style="font-size:14px;color:#e53935;">You will be asked to change your password after your first login.</p>'
            . '<p style="font-size:14px;color:#555;margin-top:24px;">Best regards,<br><strong>The Atomix Team</strong></p>'
            . '</td></tr>'
            . '<tr><td style="background:#f4f6f9;padding:16px 32px;text-align:center;">'
            . '<p style="margin:0;font-size:12px;color:#999;">This email was sent automatically.</p>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        $mail->AltBody = "Hello {$recipientName},\n\n"
            . "Your account was created by {$senderName}.\n\n";

        if ($rawPassword !== null) {
            $mail->AltBody .= "Your Login Details:\n"
                . "  Email / Username : {$recipientEmail}\n"
                . "  Temporary Password: {$rawPassword}\n\n";
        }

        $mail->AltBody .= "Before logging in, verify your email address:\n"
            . "{$verificationUrl}\n\n"
            . "You will be asked to change your password after your first login.\n\n"
            . "Best regards,\nThe Atomix Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('EmailService: Could not send verification email to ' . $recipientEmail . ' - ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send verification code via email (numeric code path)
 */
function sendAccountVerificationCodeEmail(string $recipientEmail, string $recipientName, string $code, string $senderName = MAIL_FROM_NAME): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Atomix verification code';

        $rName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
        $sName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $codeEscaped = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Verification Code</title></head><body style="font-family:Arial,sans-serif;background:#f4f6f9;padding:24px;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:28px;">'
            . '<h2 style="color:#1a73e8;margin-top:0;">Verify your Atomix account</h2>'
            . '<p>Hello <strong>' . $rName . '</strong>,</p>'
            . '<p>Your verification code is:</p>'
            . '<div style="font-size:28px;font-weight:700;background:#f0f4ff;padding:12px 18px;border-radius:6px;display:inline-block;letter-spacing:4px;">' . $codeEscaped . '</div>'
            . '<p style="margin-top:18px;color:#555;">Enter this code on the login screen to verify your email. It expires in 24 hours.</p>'
            . '<p style="margin-top:22px;">Best regards,<br><strong>' . $sName . '</strong></p>'
            . '</div></body></html>';

        $mail->AltBody = "Hello {$recipientName},\n\nYour verification code: {$code}\n\nEnter this code on the login screen to verify your email.\n\nBest regards,\n{$senderName}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('EmailService: Could not send verification code to ' . $recipientEmail . ' - ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send a password reset email to a user (student or teacher).
 *
 * @param string $recipientEmail  User's email address
 * @param string $recipientName   User's full name
 * @param string $newPassword     New plain-text password
 * @param string $senderName      Admin or teacher name who reset it
 * @return bool
 */
function sendPasswordResetEmail(
    string $recipientEmail,
    string $recipientName,
    string $newPassword,
    string $senderName
): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'Atomix - Your Password Has Been Reset';

        $rName   = htmlspecialchars($recipientName,  ENT_QUOTES, 'UTF-8');
        $rEmail  = htmlspecialchars($recipientEmail, ENT_QUOTES, 'UTF-8');
        $rPass   = htmlspecialchars($newPassword,    ENT_QUOTES, 'UTF-8');
        $sName   = htmlspecialchars($senderName,     ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">'
            . '<tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;">'
            . '<tr><td style="background:#1a73e8;padding:28px 32px;text-align:center;">'
            . '<h1 style="margin:0;color:#fff;font-size:26px;">Atomix Learning Platform</h1></td></tr>'
            . '<tr><td style="padding:32px;">'
            . '<p style="font-size:16px;color:#333;">Hello <strong>' . $rName . '</strong>,</p>'
            . '<p style="font-size:15px;color:#555;"><strong>' . $sName . '</strong> has reset your account password.</p>'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;border-left:4px solid #1a73e8;border-radius:4px;margin:24px 0;">'
            . '<tr><td style="padding:20px;">'
            . '<p style="margin:0 0 10px 0;font-size:15px;color:#333;"><strong>Your New Login Details</strong></p>'
            . '<p style="margin:4px 0;font-size:14px;color:#555;"><strong>Email / Username:</strong> ' . $rEmail . '</p>'
            . '<p style="margin:4px 0;font-size:14px;color:#555;"><strong>New Password:</strong> ' . $rPass . '</p>'
            . '</td></tr></table>'
            . '<p style="font-size:14px;color:#e53935;">You will be asked to change this password after your next login.</p>'
            . '<p style="font-size:14px;color:#555;margin-top:24px;">Best regards,<br><strong>The Atomix Team</strong></p>'
            . '</td></tr>'
            . '<tr><td style="background:#f4f6f9;padding:16px 32px;text-align:center;">'
            . '<p style="margin:0;font-size:12px;color:#999;">This email was sent automatically.</p>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        $mail->AltBody = "Hello {$recipientName},\n\n"
            . "{$senderName} has reset your account password.\n\n"
            . "Your New Login Details:\n"
            . "  Email / Username : {$recipientEmail}\n"
            . "  New Password     : {$newPassword}\n\n"
            . "You will be asked to change this password after your next login.\n\n"
            . "Best regards,\nThe Atomix Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('EmailService: Could not send reset email to ' . $recipientEmail . ' - ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send an account verified confirmation email.
 */
function sendAccountVerifiedEmail(
    string $recipientEmail,
    string $recipientName,
    string $senderName = MAIL_FROM_NAME
): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Atomix account is verified';

        $rName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
        $sName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Account Verified</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">'
            . '<tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">'
            . '<tr><td style="background:#1a73e8;padding:28px 32px;text-align:center;">'
            . '<h1 style="margin:0;color:#fff;font-size:26px;">Atomix Learning Platform</h1></td></tr>'
            . '<tr><td style="padding:32px;">'
            . '<p style="font-size:16px;color:#333;">Hello <strong>' . $rName . '</strong>,</p>'
            . '<p style="font-size:15px;color:#555;">Your email address has been verified and your account is now active.</p>'
            . '<p style="font-size:14px;color:#555;margin-top:12px;">You can now log in at <a href="' . htmlspecialchars(buildAppBaseUrl(), ENT_QUOTES, 'UTF-8') . '" style="color:#1a73e8;">the Atomix platform</a>.</p>'
            . '<p style="font-size:14px;color:#555;margin-top:18px;">If you did not perform this action, contact support or your administrator.</p>'
            . '<p style="font-size:14px;color:#555;margin-top:24px;">Best regards,<br><strong>' . $sName . '</strong></p>'
            . '</td></tr>'
            . '<tr><td style="background:#f4f6f9;padding:16px 32px;text-align:center;">'
            . '<p style="margin:0;font-size:12px;color:#999;">This email was sent automatically.</p>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        $mail->AltBody = "Hello {$recipientName},\n\nYour email address has been verified and your account is now active.\n\nYou can log in at " . buildAppBaseUrl() . "\n\nIf you did not perform this action, contact your administrator.\n\nBest regards,\n{$senderName}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('EmailService: Could not send verified email to ' . $recipientEmail . ' - ' . $mail->ErrorInfo);
        return false;
    }
}