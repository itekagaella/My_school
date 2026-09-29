<?php
/**
 * Connexion à la base de données PostgreSQL (PDO)
 */

require_once __DIR__ . '/config.php';

/**
 * Charge les variables d'environnement depuis le fichier .env
 * situé à la racine du projet (si présent).
 */
function load_env_file(string $path): void
{
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') continue;
        // Gestion des guillemets simples/doubles
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
load_env_file(__DIR__ . '/../.env');

/**
 * Paramètres de connexion PostgreSQL
 * Sources : variables d'environnement, fichier .env, puis valeurs par défaut
 * (les valeurs par défaut correspondent à une installation PostgreSQL locale
 *  standard, ce qui permet de démarrer sans fichier de configuration).
 */
function db_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $cfg = [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '5432',
        'dbname'   => getenv('DB_NAME') ?: 'my_school',
        'user'     => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'postgres',
    ];
    return $cfg;
}

/**
 * Établit une connexion PDO brute (sans effets de bord)
 *
 * @param string|null $dbname Base à cibler (défaut : base configurée)
 * @return PDO
 * @throws PDOException
 */
function db_connect(?string $dbname = null): PDO
{
    $cfg = db_config();
    $dbname = $dbname !== null && $dbname !== '' ? $dbname : $cfg['dbname'];

    $pdo = new PDO(
        "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$dbname}",
        $cfg['user'],
        $cfg['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Aligne le fuseau du serveur PostgreSQL sur celui de PHP.
    // Sans cela, NOW() côté BDD et date() côté PHP divergent dès que les deux
    // machines n'ont pas exactement le même fuseau (ex. Windows : heure locale
    // PostgreSQL vs Africa/Kinshasa), ce qui invalide les expirations de session.
    $tz = date_default_timezone_get();
    if ($tz !== '' && preg_match('/^[A-Za-z0-9_+\-\/]+$/', $tz)) {
        try {
            $pdo->exec("SET TIME ZONE '{$tz}'");
        } catch (PDOException $e) {
            error_log('Fuseau horaire PostgreSQL non-aligné : ' . $e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Indique si la base est joignable et si le schéma est installé
 * (utilisé par l'installeur et la page de connexion)
 */
function db_is_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;

    try {
        $pdo = db_connect();
        $stmt = $pdo->query("SELECT to_regclass('public.utilisateurs')");
        $ready = $stmt !== false && $stmt->fetchColumn() !== null;
    } catch (Exception $e) {
        error_log('Base indisponible : ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

/**
 * Récupère une connexion PDO à PostgreSQL
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = db_connect();
        } catch (PDOException $e) {
            // Journalisation sécurisée - pas d'exposition des credentials
            error_log('Erreur de connexion BDD : ' . $e->getMessage());
            db_error_page($e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Affiche une page d'erreur de connexion exploitable
 * (indique notamment si la base doit être créée via l'installeur)
 */
function db_error_page(string $detail = ''): void
{
    $cfg = db_config();
    $base = defined('BASE_URL') ? BASE_URL : './';
    $installHref = $base . 'install.php';

    // Message technique court, utile au diagnostic (jamais de mot de passe)
    $sqlstate = '';
    if (preg_match('/SQLSTATE\[(\w+)\]/', $detail, $m)) $sqlstate = $m[1];
    $dbMissing = (stripos($detail, 'does not exist') !== false)
        || (stripos($detail, 'n\'existe pas') !== false);

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    $dbname = htmlspecialchars($cfg['dbname'], ENT_QUOTES, 'UTF-8');
    $host = htmlspecialchars($cfg['host'] . ':' . $cfg['port'], ENT_QUOTES, 'UTF-8');
    $detailHtml = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    $sqlstateHtml = htmlspecialchars($sqlstate, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion à la base impossible - My_School</title>
<style>
 body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f4f6fb;margin:0;padding:40px 20px;color:#1f2933}
 .card{max-width:760px;margin:0 auto;background:#fff;border-radius:14px;padding:32px;box-shadow:0 8px 30px rgba(16,24,40,.08)}
 h1{font-size:20px;margin:0 0 8px;color:#b42318}
 code,pre{background:#f2f4f7;border-radius:6px;padding:2px 6px;font-size:13px}
 pre{padding:12px;overflow:auto}
 .btn{display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:11px 18px;border-radius:8px;margin-top:8px;font-weight:600}
 ul{line-height:1.7}
</style></head><body><div class="card">
<h1>Connexion à PostgreSQL impossible</h1>
<p>L'application n'a pas pu joindre la base <code>{$dbname}</code> sur <code>{$host}</code>.</p>
<pre>{$detailHtml}</pre>
HTML;

    if ($sqlstateHtml !== '') {
        echo '<p>Code SQLSTATE : <code>' . $sqlstateHtml . '</code></p>';
    }

    if ($dbMissing) {
        echo '<p><strong>La base n\'existe pas encore.</strong> Lancez l\'installateur :</p>'
            . '<a class="btn" href="' . htmlspecialchars($installHref, ENT_QUOTES, 'UTF-8') . '">Installer la base de données</a>';
    } else {
        echo '<ul>'
            . '<li>Le service PostgreSQL est-il démarré ? (services.msc sous Windows)</li>'
            . '<li>Le mot de passe de l\'utilisateur <code>postgres</code> est-il correct dans <code>.env</code> ?</li>'
            . '<li>Pour une base fraîche : <a href="' . htmlspecialchars($installHref, ENT_QUOTES, 'UTF-8') . '">ouvrir l\'installateur</a></li>'
            . '</ul>';
    }

    echo '</div></body></html>';
    exit;
}

/**
 * Exécute une requête préparée et retourne le statement
 *
 * @param string $sql Requête SQL avec placeholders
 * @param array $params Paramètres
 * @return PDOStatement
 */
function prepareQuery(string $sql, array $params = []): PDOStatement
{
    $db = getDB();
    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $type = PDO::PARAM_STR;
        if (is_int($value)) $type = PDO::PARAM_INT;
        elseif (is_bool($value)) $type = PDO::PARAM_BOOL;
        elseif ($value === null) $type = PDO::PARAM_NULL;
        $stmt->bindValue($key, $value, $type);
    }

    $stmt->execute();
    return $stmt;
}
