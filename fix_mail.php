<?php
// Patch rapide pour forcer lecture depuis .env
require_once __DIR__ . '/includes/mailer.php';
mailer_charger_env();
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'itekagaella29@gmail.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Myschool');
define('MAIL_TIMEOUT', (int)(getenv('MAIL_TIMEOUT') ?: 10));
define('MAIL_TRANSPORT', getenv('MAIL_TRANSPORT') ?: 'smtp');
