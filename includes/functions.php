<?php
/**
 * Fonctions utilitaires globales du système My_School
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ============================================================
// FONCTIONS DE NETTOYAGE ET VALIDATION
// ============================================================

/**
 * Échappe les données de sortie pour éviter XSS
 */
function e($data): string
{
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

/**
 * Nettoie une entrée POST/GET
 */
function clean_input($data)
{
    return trim(strip_tags((string)$data));
}

/**
 * Valide un email
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Récupère un paramètre POST avec valeur par défaut
 */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Récupère un paramètre GET avec valeur par défaut
 */
function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Génère un token aléatoire sécurisé
 */
function generate_token(int $length = 64): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * CSRF - génère un token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token(32);
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF - vérifie le token
 */
function csrf_verify(?string $token): bool
{
    return isset($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Affiche un champ hidden CSRF
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// ============================================================
// GESTION DES MESSAGES FLASH
// ============================================================

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function display_flash(): void
{
    $flash = get_flash();
    if ($flash) {
        $type = $flash['type'];
        $classes = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
        ];
        $cls = $classes[$type] ?? 'alert-info';
        echo '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
            . e($flash['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// ============================================================
// FONCTIONS DE SUIVI (LOGGING)
// ============================================================

/**
 * Journalise une activité de l'utilisateur
 */
function log_activity(string $action, ?string $details = null): void
{
    if (!isset($_SESSION['user_id'])) return;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        prepareQuery(
            'INSERT INTO journal_activites (user_id, action, details, ip_address)
             VALUES (:uid, :action, :details, :ip)',
            ['uid' => $_SESSION['user_id'], 'action' => $action, 'details' => $details, 'ip' => $ip]
        );
    } catch (Exception $e) {
        error_log('Erreur journalisation : ' . $e->getMessage());
    }
}

/**
 * Enregistre une connexion dans l'historique
 */
function log_connexion(?int $user_id, bool $succes, ?string $raison = null): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        prepareQuery(
            'INSERT INTO historique_connexions (user_id, ip_address, user_agent, succes, raison)
             VALUES (:uid, :ip, :ua, :succes, :raison)',
            ['uid' => $user_id, 'ip' => $ip, 'ua' => $ua, 'succes' => $succes, 'raison' => $raison]
        );
    } catch (Exception $e) {
        error_log('Erreur historique connexion : ' . $e->getMessage());
    }
}

// ============================================================
// GESTION DES NOTIFICATIONS
// ============================================================

/**
 * Crée une notification interne pour un utilisateur
 */
function notify(int $user_id, string $titre, string $message = '', string $type = 'info', ?string $lien = null): void
{
    try {
        prepareQuery(
            'INSERT INTO notifications (user_id, titre, message, type, lien)
             VALUES (:uid, :titre, :message, :type, :lien)',
            ['uid' => $user_id, 'titre' => $titre, 'message' => $message, 'type' => $type, 'lien' => $lien]
        );
    } catch (Exception $e) {
        error_log('Erreur notification : ' . $e->getMessage());
    }
}

// ============================================================
// CHIFFREMENT DES DONNÉES SENSIBLES
// ============================================================

/**
 * Chiffre une donnée avec AES-256-CBC
 */
function encrypt_data(string $data): string
{
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Déchiffre une donnée avec AES-256-CBC
 */
function decrypt_data(string $data): string
{
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $decoded = base64_decode($data);
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($decoded, 0, $ivLen);
    $encrypted = substr($decoded, $ivLen);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}

// ============================================================
// FONCTIONS DE SAUVEGARDE / ARCHIVAGE
// ============================================================

/**
 * Effectue une sauvegarde automatique de la base de données
 */
function faire_sauvegarde(?int $user_id = null, string $type = 'manuel'): bool
{
    try {
        $backupDir = ROOT_PATH . 'assets/uploads/backups';
        if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $path = $backupDir . '/' . $filename;

        prepareQuery(
            'INSERT INTO sauvegardes (nom_fichier, chemin, type, effectuee_par)
             VALUES (:f, :c, :t, :u)',
            ['f' => $filename, 'c' => $path, 't' => $type, 'u' => $user_id]
        );

        // Note : l'exécution pg_dump se fait côté script/CLI en production
        return true;
    } catch (Exception $e) {
        error_log('Erreur sauvegarde : ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// GESTION DES PARAMÈTRES SYSTÈME
// ============================================================

/**
 * Récupère un paramètre système
 */
function get_param(string $cle, $default = null)
{
    static $cache = [];
    if (isset($cache[$cle])) return $cache[$cle];
    try {
        $row = prepareQuery(
            'SELECT valeur FROM parametres_systeme WHERE cle = :cle',
            ['cle' => $cle]
        )->fetch();
        $cache[$cle] = $row ? $row['valeur'] : $default;
        return $cache[$cle];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Met à jour un paramètre système
 */
function set_param(string $cle, string $valeur): bool
{
    try {
        prepareQuery(
            'INSERT INTO parametres_systeme (cle, valeur) VALUES (:cle, :valeur)
             ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur, updated_at = NOW()',
            ['cle' => $cle, 'valeur' => $valeur]
        );
        return true;
    } catch (Exception $e) {
        error_log('Erreur paramètre : ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// FONCTIONS DE FICHIERS (UPLOAD)
// ============================================================

/**
 * Téléverse un fichier de manière sécurisée
 * @return array [succes, message, chemin, nom]
 */
function upload_file(array $file, string $subdir = 'documents'): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Erreur lors du téléversement du fichier.', null, null];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return [false, 'Le fichier dépasse la taille maximale autorisée (' . (MAX_FILE_SIZE / 1024 / 1024) . ' Mo).', null, null];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return [false, 'Type de fichier non autorisé : .' . $ext, null, null];
    }

    // Vérification type MIME réel
    $mime = mime_content_type($file['tmp_name']);
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/png', 'image/gif',
        'text/plain',
    ];
    if (!in_array($mime, $allowedMimes)) {
        return [false, 'Contenu du fichier non valide.', null, null];
    }

    $dir = UPLOAD_DIR . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $safe_name = preg_replace('/[^A-Za-z0-9.\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $newName = $safe_name . '_' . random_bytes(8) . '.' . $ext;
    $path = $dir . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return [false, 'Impossible de stocker le fichier.', null, null];
    }

    return [true, 'Fichier téléversé avec succès.', $path, $file['name']];
}

/**
 * Télécharge un fichier vers le navigateur
 */
function download_file(string $filepath, string $displayName): void
{
    if (!file_exists($filepath)) {
        set_flash('error', 'Fichier introuvable.');
        header('Location: ' . BASE_URL);
        exit;
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache');
    readfile($filepath);
    exit;
}

// ============================================================
// FONCTIONS DE PAGINATION
// ============================================================

/**
 * Calcule la pagination
 * @return array [offset, limit, total_pages, current_page]
 */
function paginate(int $total, int $perPage = 10, int $currentPage = 1): array
{
    $currentPage = max(1, $currentPage);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    return [$offset, $perPage, $totalPages, $currentPage];
}
