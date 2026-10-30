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
    if ($docRoot !== '' && $appDir !== '' && strpos($appDir . '/', $docRoot . '/') === 0) {
        $relativeToDocRoot = rtrim(str_replace('\\', '/', substr($appDir, strlen($docRoot))), '/');
    }

    $base = $scheme . '://' . $host . $relativeToDocRoot . '/';
    // Normalise les doubles barres du chemin sans toucher au "://" du schéma
    $base = preg_replace('#(^[a-z]+://[^/]+)/{2,}#i', '$1/', $base);
    define('BASE_URL', $base);
}
define('ROOT_PATH', dirname(__DIR__) . '/');

// Configuration sessions (sécurisées)
session_name('MYSCHOOL_SESSION');
ini_set('session.cookie_httponly', '1');
$__is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
ini_set('session.cookie_secure', $__is_https ? '1' : '0');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '1800'); // 30 minutes
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $__is_https,
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

// Configuration email
if (!function_exists('config_env')) {
    function config_env(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }
        $envFile = __DIR__ . '/../.env';
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
                $envLine = trim($envLine);
                if ($envLine === '' || $envLine === '' || $envLine[0] === '#' || strpos($envLine, '=') === false) {
                    continue;
                }
                [$envKey, $envVal] = explode('=', $envLine, 2);
                if (trim($envKey) !== $key) {
                    continue;
                }
                $envVal = trim($envVal);
                if (strlen($envVal) >= 2 && (($envVal[0] === '"' && substr($envVal, -1) === '"') || ($envVal[0] === "'" && substr($envVal, -1) === "'"))) {
                    $envVal = substr($envVal, 1, -1);
                }
                return $envVal;
            }
        }
        return $default;
    }
}

$mailTransportCfg = strtolower(config_env('MAIL_TRANSPORT', ''));
if ($mailTransportCfg === '') {
    $mailTransportCfg = filter_var(config_env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN) ? 'mail' : 'log';
}
if (!in_array($mailTransportCfg, ['smtp', 'mail', 'log'], true)) {
    $mailTransportCfg = 'log';
}
define('MAIL_TRANSPORT', $mailTransportCfg);
define('MAIL_ENABLED', MAIL_TRANSPORT !== 'log');

define('MAIL_HOST', config_env('MAIL_HOST', '127.0.0.1'));
define('MAIL_PORT', (int)config_env('MAIL_PORT', '1025'));
define('MAIL_ENCRYPTION', strtolower(config_env('MAIL_ENCRYPTION', 'none')));
define('MAIL_USERNAME', config_env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', config_env('MAIL_PASSWORD', ''));
define('MAIL_FROM', config_env('MAIL_FROM', 'no-reply@myschool.edu'));
define('MAIL_FROM_NAME', config_env('MAIL_FROM_NAME', 'My_School'));
define('MAIL_TIMEOUT', max(3, (int)config_env('MAIL_TIMEOUT', '10')));
define('CRON_TOKEN', config_env('CRON_TOKEN', ''));

// Clé de chiffrement (à changer en production - surchargeable via .env)
// Charger .env si disponible (le fichier database.php le fait aussi, mais ici
// c'est nécessaire pour que la clé soit disponible dès config.php)
if (is_file(__DIR__ . '/../.env') && getenv('ENCRYPTION_KEY') === false && !array_key_exists('ENCRYPTION_KEY', $_ENV)) {
    $envLines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $envLine) {
        $envLine = trim($envLine);
        if (strpos($envLine, 'ENCRYPTION_KEY=') === 0) {
            $val = trim(substr($envLine, 15));
            if (strlen($val) >= 2 && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
                $val = substr($val, 1, -1);
            }
            putenv('ENCRYPTION_KEY=' . $val);
            $_ENV['ENCRYPTION_KEY'] = $val;
            break;
        }
    }
}
if (getenv('ENCRYPTION_KEY') !== false && getenv('ENCRYPTION_KEY') !== '') {
    define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY'));
} else {
    define('ENCRYPTION_KEY', 'changez_cette_cle_secrete_chaque_production');
}
