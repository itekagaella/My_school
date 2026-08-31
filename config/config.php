<?php
/**
 * Configuration principale du système My_School
 */

// Base URL de l'application (détection automatique, surchargeable avant include)
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appDir = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

    $relativeToDocRoot = '';
    if ($docRoot !== '' && strpos($appDir, $docRoot) === 0) {
        $relativeToDocRoot = '/' . ltrim(substr($appDir, strlen($docRoot)), '/');
    }

    $base = $scheme . '://' . $host . $relativeToDocRoot . '/';
    define('BASE_URL', $base);
}
define('ROOT_PATH', dirname(__DIR__) . '/');

// Configuration sessions (sécurisées)
session_name('MYSCHOOL_SESSION');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '0'); // Met à '1' en production HTTPS
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '1800'); // 30 minutes
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => false,
    'samesite' => 'Lax'
]);

// Ouverture de session
session_start();

// Configuration serveur
date_default_timezone_set('Africa/Kinshasa');
setlocale(LC_TIME, 'fr_FR');

// Fuseau horaire BDD
define('APP_TIMEZONE', 'Africa/Kinshasa');

// Seuil de verrouillage compte (tentatives échouées)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// Inactivité max avant déconnexion automatique (secondes)
define('SESSION_TIMEOUT', 1800);

// Paramètres d'upload
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 Mo
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt']);
define('UPLOAD_DIR', ROOT_PATH . 'assets/uploads/');

// Configuration email (utiliser PHPMailer en production)
define('MAIL_ENABLED', false);
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM', 'no-reply@myschool.edu');
define('MAIL_FROM_NAME', 'My_School');

// Clé de chiffrement (à changer en production)
define('ENCRYPTION_KEY', 'changez_cette_cle_secrete_chaque_production');
