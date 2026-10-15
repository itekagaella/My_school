<?php
/**
 * Fonctions utilitaires globales du système My_School
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/backup.php';

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
 * Un jeton vide ou absent est toujours refusé : sans cette règle, une session
 * qui n'a jamais affiché de formulaire (donc sans jeton) accepterait un POST
 * avec un champ csrf_token vide.
 */
function csrf_verify(?string $token): bool
{
    $attendu = $_SESSION['csrf_token'] ?? '';
    return $attendu !== '' && $token !== null && hash_equals($attendu, $token);
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
    // bin2hex obligatoire : random_bytes() renvoie des octets bruts, qui
    // produisaient des noms de fichiers illisibles et faisaient échouer
    // l'insertion en base (« invalid byte sequence for encoding "UTF8" »).
    $newName = $safe_name . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
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
// RÔLES ET PROFILS UTILISATEUR
// ============================================================

/**
 * Rôles appartenant à la famille « personnel » (agents non-enseignants).
 * Le profil générique « personnel » est conservé pour compatibilité.
 */
function personnel_roles(): array
{
    return ['personnel', 'surveillant', 'finance', 'discipline'];
}

/**
 * Libellés lisibles des rôles (au pluriel pour les listes déroulantes).
 */
function role_labels(): array
{
    return [
        'admin'      => 'administrateur',
        'eleve'      => 'élève',
        'prof'       => 'professeur',
        'personnel'  => 'personnel',
        'surveillant'=> 'surveillant',
        'finance'    => 'gestionnaire financier',
        'discipline' => 'responsable discipline',
    ];
}

/**
 * Libellés courts (singulier, affichage badge/menu).
 */
function role_labels_court(): array
{
    return [
        'admin'      => 'Administrateur',
        'eleve'      => 'Élève',
        'prof'       => 'Professeur',
        'personnel'  => 'Personnel',
        'surveillant'=> 'Surveillant',
        'finance'    => 'Finance',
        'discipline' => 'Discipline',
    ];
}

/**
 * Icône Font Awesome associée à un rôle.
 */
function role_icons(): array
{
    return [
        'admin'      => 'fa-shield-halved',
        'eleve'      => 'fa-user-graduate',
        'prof'       => 'fa-chalkboard-user',
        'personnel'  => 'fa-briefcase',
        'surveillant'=> 'fa-eye',
        'finance'    => 'fa-coins',
        'discipline' => 'fa-gavel',
    ];
}

/**
 * Dashboard d'accueil (chemin relatif) selon le rôle.
 */
function role_home(string $role): string
{
    $map = [
        'admin'      => 'admin/index.php',
        'eleve'      => 'client/eleve/index.php',
        'prof'       => 'client/prof/index.php',
        'personnel'  => 'client/personnel/index.php',
        'surveillant'=> 'client/surveillant/index.php',
        'finance'    => 'client/finance/index.php',
        'discipline' => 'client/discipline/index.php',
    ];
    return $map[$role] ?? 'index.php';
}

/**
 * Espace client (dossier) associé à un rôle de la famille personnel.
 * Tous les agents partagent certaines pages (horaires, communications, documents)
 * situées dans client/personnel/ ; seules les pages métier sont propres au rôle.
 */
function role_base_dir(string $role): string
{
    $map = [
        'eleve'      => 'client/eleve',
        'prof'       => 'client/prof',
        'personnel'  => 'client/personnel',
        'surveillant'=> 'client/personnel',
        'finance'    => 'client/personnel',
        'discipline' => 'client/personnel',
    ];
    return $map[$role] ?? 'client/personnel';
}

/**
 * Liste [role => libellé] pour les listes déroulantes d'administration.
 */
function roles_selectable(): array
{
    return [
        'eleve'      => 'Élève',
        'prof'       => 'Professeur',
        'surveillant'=> 'Personnel — Surveillant',
        'finance'    => 'Personnel — Finance',
        'discipline' => 'Personnel — Discipline',
        'personnel'  => 'Personnel — Générique',
        'admin'      => 'Administrateur',
    ];
}

/**
 * Valide qu'un rôle fait partie des rôles système autorisés.
 */
function role_is_valid(string $role): bool
{
    return array_key_exists($role, roles_selectable());
}

/**
 * Profils d'accès proposés lors de la création d'un membre du personnel.
 */
function personnel_profil_options(): array
{
    return [
        'surveillant'=> 'Surveillant — surveillance des élèves',
        'finance'    => 'Gestionnaire financier — frais & paiements',
        'discipline' => 'Responsable discipline — incidents & sanctions',
        'personnel'  => 'Personnel — générique',
    ];
}

/**
 * Garantit qu'un utilisateur de la famille « personnel » possède une fiche
 * dans la table personnel. La fiche est créée automatiquement si elle manque
 * (compte créé depuis le module Utilisateurs, import, etc.), afin que les
 * espaces surveillant / finance / discipline restent accessibles.
 * Renvoie la fiche (existante ou créée), ou null si l'utilisateur est inconnu.
 */
function ensure_personnel_profile(int $userId): ?array
{
    $pers = prepareQuery('SELECT * FROM personnel WHERE user_id = :u', ['u' => $userId])->fetch();
    if ($pers) {
        return $pers;
    }

    $u = prepareQuery('SELECT nom, prenom, email FROM utilisateurs WHERE id = :id', ['id' => $userId])->fetch();
    if (!$u) {
        return null;
    }

    // Même convention que le module Personnel : matricule dérivé de l'id.
    $matricule = 'P' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
    if (prepareQuery('SELECT 1 FROM personnel WHERE matricule = :m', ['m' => $matricule])->fetch()) {
        $matricule .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    }

    prepareQuery(
        "INSERT INTO personnel (user_id, matricule, nom, prenom, email, fonction, statut, created_at)
         VALUES (:u, :m, :n, :p, :e, 'Personnel', 'actif', NOW())",
        ['u' => $userId, 'm' => $matricule, 'n' => $u['nom'], 'p' => $u['prenom'], 'e' => $u['email']]
    );

    return prepareQuery('SELECT * FROM personnel WHERE user_id = :u', ['u' => $userId])->fetch();
}

// ============================================================
// FINANCES
// ============================================================

/**
 * Devise affichée (paramétrable via « Admin > Paramètres », clé « devise »).
 */
function devise(): string
{
    $d = trim((string) get_param('devise', 'FBu'));
    return $d !== '' ? $d : 'FBu';
}

/**
 * Formate un montant monétaire (ex. 12 500,00 FBu).
 */
function format_montant($montant): string
{
    return number_format((float)$montant, 2, ',', ' ') . ' ' . devise();
}

/**
 * Génère un numéro de reçu unique de la forme REC-AAAAMMJJ-0001.
 */
function generer_numero_recu(): string
{
    $prefix = 'REC-' . date('Ymd') . '-';
    $row = prepareQuery(
        "SELECT recu_numero FROM paiements WHERE recu_numero LIKE :p ORDER BY id DESC LIMIT 1",
        ['p' => $prefix . '%']
    )->fetch();
    $seq = 1;
    if ($row && preg_match('/-(\d+)$/', $row['recu_numero'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Modes de paiement autorisés.
 * Seul le paiement en espèces est accepté.
 */
function modes_paiement(): array
{
    return [
        'especes' => 'Espèces',
    ];
}

/**
 * Solde financier d'un élève : montant attendu (frais obligatoires actifs du
 * niveau de sa classe + frais globaux) moins le total déjà payé.
 *
 * @return array{attendu: float, paye: float, solde: float}
 */
function eleve_solde(int $eleveId): array
{
    try {
        $row = prepareQuery(
            "SELECT
                COALESCE((SELECT SUM(f.montant) FROM frais_scolaires f
                          WHERE f.actif = TRUE AND f.obligatoire = TRUE
                            AND (f.niveau IS NULL OR f.niveau = c.niveau)), 0) AS attendu,
                COALESCE((SELECT SUM(pa.montant) FROM paiements pa WHERE pa.eleve_id = e.id), 0) AS paye
             FROM eleves e
             LEFT JOIN classes c ON c.id = e.classe_id
             WHERE e.id = :id",
            ['id' => $eleveId]
        )->fetch();
    } catch (Exception $e) {
        return ['attendu' => 0.0, 'paye' => 0.0, 'solde' => 0.0];
    }
    $attendu = (float)($row['attendu'] ?? 0);
    $paye = (float)($row['paye'] ?? 0);
    return ['attendu' => $attendu, 'paye' => $paye, 'solde' => $attendu - $paye];
}

/**
 * Envoie des données tabulaires en CSV téléchargeable (séparateur « ; »).
 *
 * @param string $filename Nom du fichier
 * @param array<int,string> $headers En-têtes de colonnes
 * @param array<int,array<int,mixed>> $rows Lignes
 */
function export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // BOM UTF-8 pour Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

// ============================================================
// DISCIPLINE
// ============================================================

/**
 * Niveaux de gravité d'un incident disciplinaire.
 */
function gravites_incident(): array
{
    return ['faible' => 'Faible', 'moyenne' => 'Moyenne', 'grave' => 'Grave'];
}

/**
 * Statuts d'un incident disciplinaire.
 */
function statuts_incident(): array
{
    return ['ouvert' => 'Ouvert', 'traite' => 'Traité', 'clos' => 'Clos'];
}

/**
 * Statuts d'une sanction.
 */
function statuts_sanction(): array
{
    return ['en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
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

// ============================================================
// SYSTÈME SCOLAIRE BURUNDAIS
// ============================================================

/**
 * Classes comprises entre le primaire et l'université (réforme « École fondamentale »).
 * Retourne [cycle => [nom_classe, ...]] dans l'ordre pédagogique.
 */
function classes_burundi(): array
{
    return [
        'Enseignement fondamental (cycle 4)' => [
            '7ème année (fondamental)',
            '8ème année (fondamental)',
            '9ème année (fondamental)',
        ],
        'Enseignement post-fondamental' => [
            '1ère année (post-fondamental)',
            '2ème année (post-fondamental)',
            '3ème année (post-fondamental)',
        ],
    ];
}

/**
 * Liste plate ordonnée : [nom_classe => cycle]
 */
function classes_burundi_list(): array
{
    $liste = [];
    foreach (classes_burundi() as $cycle => $noms) {
        foreach ($noms as $nom) {
            $liste[$nom] = $cycle;
        }
    }
    return $liste;
}

/**
 * Niveau (cycle) d'un nom de classe, '' si la classe n'existe pas dans le système
 */
function niveau_pour_classe(string $nomClasse): string
{
    $liste = classes_burundi_list();
    return $liste[$nomClasse] ?? '';
}

/**
 * Séries de l'enseignement secondaire (le libellé « Autre » est géré par l'interface)
 */
function sections_burundi(): array
{
    return ['Sciences', 'Lettres', 'Pédagogie', 'Commerciale', 'Économie', 'Techniques'];
}

/**
 * Tri SQL des classes dans l'ordre pédagogique (classes hors liste en dernier)
 *
 * @param string $col colonne ou alias contenant le nom de classe (littéral du code uniquement)
 */
function classes_order_sql(string $col = 'nom_classe'): string
{
    $sql = 'CASE ' . $col;
    $rang = 1;
    foreach (array_keys(classes_burundi_list()) as $nom) {
        $sql .= " WHEN '" . str_replace("'", "''", $nom) . "' THEN " . $rang++;
    }
    return $sql . ' ELSE 99 END';
}

/**
 * Filtre SQL des communications visibles par un élève
 *
 * Reprend la règle appliquée à l'ouverture d'une communication
 * (client/communication.php) : statut publié et destinataires « tous »,
 * « role:eleve » ou « classe:{sa classe} ». Utilisé par la liste des
 * communications et le tableau de bord pour rester cohérent.
 *
 * @param ?int $classeId classe de l'élève (null si le profil n'en a pas)
 * @param string $alias alias éventuel de la table communications
 */
function communications_where_eleve(?int $classeId, string $alias = ''): string
{
    $p = $alias !== '' ? $alias . '.' : '';
    $sql = $p . "statut = 'publie' AND ("
        . $p . "destinataires = 'tous'"
        . " OR ',' || " . $p . "destinataires || ',' LIKE '%,role:eleve,%'";
    if ($classeId !== null && $classeId > 0) {
        $sql .= " OR ',' || " . $p . "destinataires || ',' LIKE '%,classe:" . $classeId . ",%'";
    }
    return $sql . ')';
}
