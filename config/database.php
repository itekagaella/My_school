<?php
/**
 * Connexion à la base de données PostgreSQL (PDO)
 */

require_once __DIR__ . '/config.php';

/**
 * Récupère une connexion PDO à PostgreSQL
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Paramètres de connexion PostgreSQL
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: 'my_school';
        $user = getenv('DB_USER') ?: 'postgres';
        $password = getenv('DB_PASSWORD') ?: 'postgres';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Journalisation sécurisée - pas d'exposition des credentials
            error_log('Erreur de connexion BDD : ' . $e->getMessage());
            http_response_code(500);
            die('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
        }
    }

    return $pdo;
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
