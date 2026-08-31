<?php
/**
 * Système d'authentification et de contrôle d'accès
 * (authentification, 2FA, verrouillage, sessions, permissions)
 */

require_once __DIR__ . '/functions.php';

// Vérification de la déconnexion automatique (inactivité)
function check_auto_logout(): void
{
    if (isset($_SESSION['last_activity']) && isset($_SESSION['user_id'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            logout('Déconnexion automatique après 30 minutes d\'inactivité.');
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Authentifie un utilisateur
 * @return array [succes, message, user?]
 */
function login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    // Récupérer l'utilisateur
    $user = prepareQuery(
        'SELECT * FROM utilisateurs WHERE email = :email',
        ['email' => $email]
    )->fetch();

    if (!$user) {
        log_connexion(null, false, 'Email inexistant');
        return [false, 'Identifiants incorrects.'];
    }

    // Vérifier le verrouillage du compte
    if ($user['compte_verrouille']) {
        $verrouDate = strtotime($user['verrou_date']);
        $cooldown = LOCKOUT_MINUTES * 60;
        if (time() - $verrouDate < $cooldown) {
            $remaining = ceil(($cooldown - (time() - $verrouDate)) / 60);
            log_connexion($user['id'], false, 'Compte verrouillé');
            return [false, "Compte verrouillé. Réessayez dans {$remaining} minute(s)."];
        }
        // Période de verrouillage expirée, déverrouiller
        prepareQuery(
            'UPDATE utilisateurs SET compte_verrouille = FALSE, tentatives_connexion = 0, verrou_date = NULL
             WHERE id = :id',
            ['id' => $user['id']]
        );
    }

    // Vérifier que le compte est actif
    if (!$user['actif']) {
        log_connexion($user['id'], false, 'Compte inactif');
        return [false, 'Ce compte a été désactivé par l\'administrateur.'];
    }

    // Vérifier le mot de passe (hash bcrypt)
    if (!password_verify($password, $user['password_hash'])) {
        // Incrémenter les tentatives
        $tentatives = $user['tentatives_connexion'] + 1;
        if ($tentatives >= MAX_LOGIN_ATTEMPTS) {
            prepareQuery(
                'UPDATE utilisateurs SET tentatives_connexion = :t, compte_verrouille = TRUE, verrou_date = NOW()
                 WHERE id = :id',
                ['t' => $tentatives, 'id' => $user['id']]
            );
            log_connexion($user['id'], false, 'Compte verrouillé après tentatives');
            return [false, "Trop de tentatives. Compte verrouillé pendant " . LOCKOUT_MINUTES . " minutes."];
        }
        prepareQuery(
            'UPDATE utilisateurs SET tentatives_connexion = :t WHERE id = :id',
            ['t' => $tentatives, 'id' => $user['id']]
        );
        log_connexion($user['id'], false, 'Mot de passe incorrect');
        return [false, 'Identifiants incorrects. Il reste ' . (MAX_LOGIN_ATTEMPTS - $tentatives) . ' tentative(s).'];
    }

    // Authentification réussie : réinitialiser les tentatives
    prepareQuery(
        'UPDATE utilisateurs SET tentatives_connexion = 0, derniere_connexion = NOW()
         WHERE id = :id',
        ['id' => $user['id']]
    );
    log_connexion($user['id'], true);

    // Si 2FA activée, générer et stocker le code OTP dans une session temporaire
    if ($user['deux_facteurs']) {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 min
        prepareQuery(
            'UPDATE utilisateurs SET dernier_code_otp = :otp, dernier_code_exp = :exp WHERE id = :id',
            ['otp' => password_hash($otp, PASSWORD_BCRYPT), 'exp' => $expires, 'id' => $user['id']]
        );

        // Envoyer le code par email (simulé / PHPMailer en production)
        $_SESSION['pending_2fa'] = $user['id'];
        $_SESSION['2fa_user_meta'] = ['nom' => $user['nom'], 'prenom' => $user['prenom'], 'email' => $user['email']];
        send_otp_email($user['email'], $user['nom'], $otp);
        return [true, '2FA_REQUIRED'];
    }

    // Démarrer la session de l'utilisateur
    start_user_session($user['id']);

    return [true, 'Connexion réussie.', $user];
}

/**
 * Démarre une session utilisateur sécurisée
 */
function start_user_session(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();

    // Enregistrer un token de session en base
    $token = generate_token();
    $expires = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
    prepareQuery(
        'INSERT INTO sessions_utilisateur (user_id, token, ip_address, user_agent, expires_at)
         VALUES (:uid, :token, :ip, :ua, :exp)',
        [
            'uid' => $userId,
            'token' => hash('sha256', $token),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'exp' => $expires,
        ]
    );
    log_activity('auth.login', 'Connexion réussie');
}

/**
 * Vérifie le code 2FA saisi
 */
function verify_2fa(string $code): array
{
    if (!isset($_SESSION['pending_2fa'])) {
        return [false, 'Session 2FA expirée.'];
    }
    $userId = $_SESSION['pending_2fa'];
    $user = prepareQuery(
        'SELECT * FROM utilisateurs WHERE id = :id',
        ['id' => $userId]
    )->fetch();

    if (!$user || !$user['dernier_code_otp'] || $user['dernier_code_exp'] < date('Y-m-d H:i:s', time())) {
        unset($_SESSION['pending_2fa']);
        return [false, 'Code expiré. Veuillez vous reconnecter.'];
    }

    if (!password_verify($code, $user['dernier_code_otp'])) {
        return [false, 'Code de vérification incorrect.'];
    }

    // Code validé
    prepareQuery(
        'UPDATE utilisateurs SET dernier_code_otp = NULL, dernier_code_exp = NULL WHERE id = :id',
        ['id' => $userId]
    );
    unset($_SESSION['pending_2fa']);

    start_user_session($userId);
    return [true, 'Authentification à deux facteurs réussie.'];
}

/**
 * Déconnecte l'utilisateur
 */
function logout(string $raison = 'Déconnexion manuelle'): void
{
    if (isset($_SESSION['user_id'])) log_activity('auth.logout', $raison);
    $flash = ['type' => 'info', 'message' => $raison];
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    // Relancer une session pour stocker le flash après la déconnexion
    session_start();
    session_regenerate_id(true);
    $_SESSION['flash'] = $flash;
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Récupère l'utilisateur courant (avec cache)
 */
function current_user(): ?array
{
    if (!is_logged_in()) return null;
    static $user = false;
    if ($user === false) {
        $user = prepareQuery(
            'SELECT * FROM utilisateurs WHERE id = :id',
            ['id' => $_SESSION['user_id']]
        )->fetch();
        if (!$user) {
            logout('Session invalide');
        }
    }
    return $user ?: null;
}

/**
 * Récupère le rôle de l'utilisateur courant
 */
function current_role(): ?string
{
    $user = current_user();
    return $user['role'] ?? null;
}

/**
 * Vérifie que l'utilisateur est connecté, sinon redirige
 */
function require_login(): void
{
    check_auto_logout();
    if (!is_logged_in()) {
        set_flash('warning', 'Veuillez vous connecter pour accéder à cette page.');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Vérifie que l'utilisateur a l'un des rôles requis, sinon redirige
 */
function require_role(array $roles): void
{
    require_login();
    $role = current_role();
    if (!in_array($role, $roles)) {
        http_response_code(403);
        // Rediriger vers le dashboard de son propre rôle
        $redirects = [
            'admin' => 'admin/index.php',
            'eleve' => 'client/eleve/index.php',
            'prof'  => 'client/prof/index.php',
            'personnel' => 'client/personnel/index.php',
        ];
        header('Location: ' . BASE_URL . ($redirects[$role] ?? 'index.php'));
        exit;
    }
}

/**
 * Vérifie si l'utilisateur courant possède une permission (vérification en base)
 */
function has_permission(string $permission): bool
{
    if (!is_logged_in()) return false;
    $user = current_user();
    // Un admin a toutes les permissions
    if ($user['role'] === 'admin') return true;

    static $cache = [];
    if (isset($cache[$user['id']][$permission])) return $cache[$user['id']][$permission];

    $row = prepareQuery(
        'SELECT COUNT(*) AS nb
         FROM utilisateurs u
         JOIN roles r ON r.nom_role = u.role
         JOIN role_permissions rp ON rp.role_id = r.id
         JOIN permissions p ON p.id = rp.permission_id
         WHERE u.id = :uid AND p.nom_permission = :perm',
        ['uid' => $user['id'], 'perm' => $permission]
    )->fetch();

    $result = ($row['nb'] ?? 0) > 0;
    $cache[$user['id']][$permission] = $result;
    return $result;
}

/**
 * Vérifie une permission, sinon retourne 403
 */
function require_permission(string $permission): void
{
    require_login();
    if (!has_permission($permission)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;padding:80px;color:#721c24;">
             <h1>403 - Accès refusé</h1><p>Vous n\'avez pas la permission d\'effectuer cette action.</p>
             <a href="' . BASE_URL . '">Retour à l\'accueil</a></div>');
    }
}

/**
 * Génère et envoie un token de réinitialisation de mot de passe
 */
function request_password_reset(string $email): array
{
    $email = strtolower(trim($email));
    $user = prepareQuery(
        'SELECT * FROM utilisateurs WHERE email = :email',
        ['email' => $email]
    )->fetch();

    // Réponse générique pour éviter l'énumération des comptes
    if (!$user) {
        return [true, 'Si l\'email existe, un lien de réinitialisation a été envoyé.'];
    }

    $token = generate_token(32);
    $expires = date('Y-m-d H:i:s', time() + 3600);
    prepareQuery(
        'INSERT INTO password_resets (user_id, token, expires_at)
         VALUES (:uid, :token, :exp)',
        ['uid' => $user['id'], 'token' => hash('sha256', $token), 'exp' => $expires]
    );

    send_reset_email($user['email'], $user['nom'], $token);
    log_activity('auth.password_reset_request', 'Demande de réinitialisation de mot de passe');
    return [true, 'Si l\'email existe, un lien de réinitialisation a été envoyé.'];
}

/**
 * Réinitialise le mot de passe avec un token valide
 */
function reset_password(string $token, string $newPassword): array
{
    $row = prepareQuery(
        'SELECT * FROM password_resets WHERE token = :token AND used = FALSE',
        ['token' => hash('sha256', $token)]
    )->fetch();

    if (!$row || $row['expires_at'] < date('Y-m-d H:i:s', time())) {
        return [false, 'Lien de réinitialisation invalide ou expiré.'];
    }
    if (strlen($newPassword) < 8) {
        return [false, 'Le mot de passe doit contenir au moins 8 caractères.'];
    }

    prepareQuery(
        'UPDATE utilisateurs SET password_hash = :hash, tentatives_connexion = 0,
         compte_verrouille = FALSE, verrou_date = NULL WHERE id = :uid',
        ['hash' => password_hash($newPassword, PASSWORD_BCRYPT), 'uid' => $row['user_id']]
    );
    prepareQuery(
        'UPDATE password_resets SET used = TRUE WHERE id = :id',
        ['id' => $row['id']]
    );
    log_activity('auth.password_reset', 'Mot de passe réinitialisé');
    return [true, 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'];
}

// ============================================================
// ENVOI D'EMAILS (simulé - à remplacer par PHPMailer)
// ============================================================

function send_email(string $to, string $subject, string $body): bool
{
    if (!MAIL_ENABLED) return true; // Mode simulation

    // En production : utiliser PHPMailer
    // mail($to, $subject, $body, "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">");
    return true;
}

function send_otp_email(string $to, string $name, string $otp): void
{
    $subject = 'Code de vérification My_School';
    $body = "Bonjour {$name},\n\nVotre code de vérification est : {$otp}\n" .
            "Ce code expire dans 5 minutes.\n\nMy_School";
    send_email($to, $subject, $body);
}

function send_reset_email(string $to, string $name, string $token): void
{
    $link = BASE_URL . 'reset_password.php?token=' . urlencode($token);
    $subject = 'Réinitialisation de mot de passe My_School';
    $body = "Bonjour {$name},\n\nCliquez sur ce lien pour réinitialiser votre mot de passe :\n{$link}\n" .
            "Ce lien expire dans 1 heure.\n\nMy_School";
    send_email($to, $subject, $body);
}
