<?php
/**
 * Mail Configuration
 * Atomix Learning Platform
 *
 * Fill in your SMTP credentials before sending emails.
 */

// ── SMTP Settings ──────────────────────────────────────────────────────────────
define('MAIL_HOST',       'smtp.gmail.com');   // e.g. smtp.gmail.com / smtp.office365.com
define('MAIL_PORT',       587);                // 587 for TLS, 465 for SSL
define('MAIL_USERNAME',   'atomixinteractive933@gmail.com'); // SMTP login username
define('MAIL_PASSWORD',   'dtoucbdukqbpenro');               // SMTP App Password
define('MAIL_ENCRYPTION', 'tls');                           // 'tls' or 'ssl'
define('MAIL_FROM_EMAIL', 'atomixinteractive933@gmail.com'); // Sender "From" address
define('MAIL_FROM_NAME',  'Atomix Learning Platform'); // Sender display name
