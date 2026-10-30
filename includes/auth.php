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
function login(string $email, string $password, ?string $profilAttendu = null): array
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

    // Le profil sélectionné sur la page de connexion doit correspondre au rôle réel du compte.
    // L'admin peut se connecter directement sans sélection de profil.
    if ($profilAttendu === 'admin' && $user['role'] === 'admin') {
        $profilOk = true;
    } elseif ($profilAttendu !== 'admin') {
        $profilOk = ($user['role'] === $profilAttendu)
            || ($profilAttendu === 'personnel' && in_array($user['role'], personnel_roles(), true));
    } else {
        $profilOk = ($user['role'] === 'admin');
    }
    if ($profilAttendu !== null && !$profilOk) {
        $role_label = role_labels();
        $label = $role_label[$user['role']] ?? $user['role'];
        log_connexion($user['id'], false, 'Profil sélectionné incompatible (' . $profilAttendu . ')');
        return [false, 'Ce compte est un compte ' . $label . ', sélectionnez le bon profil.'];
    }

    // Authentification réussie : réinitialiser les tentatives
    prepareQuery(
        'UPDATE utilisateurs SET tentatives_connexion = 0, derniere_connexion = NOW()
         WHERE id = :id',
        ['id' => $user['id']]
    );
    log_connexion($user['id'], true);

    // Auto-réparation : tout compte de la famille « personnel » doit avoir une
    // fiche personnel pour accéder à son espace (créée ici si elle manque).
    if (in_array($user['role'], personnel_roles(), true)) {
        ensure_personnel_profile((int)$user['id']);
    }

    // Si 2FA activée, générer et stocker le code OTP dans une session temporaire
    if ($user['deux_facteurs']) {
        // Autoriser sans 2FA juste après réinitialisation de mot de passe
        if (!empty($_SESSION['skip_2fa_after_reset']) && $_SESSION['skip_2fa_after_reset'] == $user['id'] && (!empty($_SESSION['skip_2fa_after_reset_time']) && (time() - $_SESSION['skip_2fa_after_reset_time']) < 600)) {
            unset($_SESSION['skip_2fa_after_reset'], $_SESSION['skip_2fa_after_reset_time']);
            // Nettoyer aussi un éventuel état 2FA resté d'une tentative précédente.
            unset(
                $_SESSION['pending_2fa'],
                $_SESSION['pending_2fa_user_role'],
                $_SESSION['2fa_user_meta'],
                $_SESSION['2fa_attempts'],
                $_SESSION['otp_demo'],
                $_SESSION['user_id'],
                $_SESSION['session_token'],
                $_SESSION['user_role']
            );
            start_user_session($user['id']);
            return [true, 'Connexion réussie.', $user];
        }

        // Ne pas nettoyer la session ici - ça cause des boucles
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(false);
        }

        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 min
        prepareQuery(
            'UPDATE utilisateurs SET dernier_code_otp = :otp, dernier_code_exp = :exp WHERE id = :id',
            ['otp' => password_hash($otp, PASSWORD_BCRYPT), 'exp' => $expires, 'id' => $user['id']]
        );

        // Envoyer le code par email (réel si MAIL_ENABLED, sinon boîte de démo)
        $_SESSION['pending_2fa'] = $user['id'];
        $_SESSION['pending_2fa_user_role'] = $user['role'];
        $_SESSION['2fa_user_meta'] = ['nom' => $user['nom'], 'prenom' => $user['prenom'], 'email' => $user['email']];
        send_otp_email($user['email'], $user['nom'], $otp);
        // Sans SMTP, le code serait inconnu de l'utilisateur : on l'expose sur
        // la page de vérification (la session a déjà prouvé le mot de passe).
        if (last_mail_delivered()) {
            unset($_SESSION['otp_demo']);
        } else {
            $_SESSION['otp_demo'] = ['code' => $otp, 'exp' => time() + 300];
        }
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

    // Sauvegarder le rôle pour éviter les redirections en boucle
    try {
        $ur = prepareQuery('SELECT role FROM utilisateurs WHERE id = :id', ['id' => $userId])->fetch();
        if ($ur && !empty($ur['role'])) {
            $_SESSION['user_role'] = $ur['role'];
        }
    } catch (Throwable $e) {
    }

    // Enregistrer un token de session en base
    // L'expiration est calculée par PostgreSQL (NOW() + intervalle) car elle est
    // ensuite comparée à NOW() : mélanger date() PHP et NOW() SQL rend la session
    // invalide dès que les fuseaux de PHP et de la BDD diffèrent.
    $token = generate_token();
    try {
        prepareQuery(
            'INSERT INTO sessions_utilisateur (user_id, token, ip_address, user_agent, expires_at)
             VALUES (:uid, :token, :ip, :ua, NOW() + make_interval(secs => :ttl))',
            [
                'uid' => $userId,
                'token' => hash('sha256', $token),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ttl' => SESSION_TIMEOUT,
            ]
        );
        $_SESSION['session_token'] = hash('sha256', $token);
    } catch (Throwable $e) {
        // Sans jeton en base, la session reste utilisable (validate_session_token
        // l'ignore) au lieu de provoquer une erreur fatale après la connexion.
        unset($_SESSION['session_token']);
        error_log('Session 2FA non enregistrée en base : ' . $e->getMessage());
    }
    try {
        log_activity('auth.login', 'Connexion réussie');
    } catch (Throwable $e) {
    }
}

/**
 * Vérifie le code 2FA saisi (avec limite de tentatives anti brute-force)
 */
function verify_2fa(string $code): array
{
    if (!isset($_SESSION['pending_2fa'])) {
        if (isset($_SESSION['user_id'])) {
            return [false, 'Session déjà connectée. Veuillez actualiser.'];
        }
        return [false, 'Session 2FA expirée. Veuillez vous reconnecter.'];
    }

    // Limite de tentatives 2FA (5 max / fenêtre de 5 min)
    if (empty($_SESSION['2fa_attempts'])) $_SESSION['2fa_attempts'] = ['count' => 0, 'start' => time()];
    if ($_SESSION['2fa_attempts']['count'] >= MAX_LOGIN_ATTEMPTS) {
        if (time() - $_SESSION['2fa_attempts']['start'] < LOCKOUT_MINUTES * 60) {
            $remaining = ceil((LOCKOUT_MINUTES * 60 - (time() - $_SESSION['2fa_attempts']['start'])) / 60);
            return [false, "Trop de tentatives 2FA. Réessayez dans {$remaining} minute(s)."];
        }
        $_SESSION['2fa_attempts'] = ['count' => 0, 'start' => time()];
    }

    $userId = $_SESSION['pending_2fa'];
    $user = prepareQuery(
        'SELECT * FROM utilisateurs WHERE id = :id',
        ['id' => $userId]
    )->fetch();

    if (!$user || !$user['dernier_code_otp'] || $user['dernier_code_exp'] < date('Y-m-d H:i:s', time())) {
        unset($_SESSION['pending_2fa'], $_SESSION['2fa_attempts'], $_SESSION['otp_demo']);
        return [false, 'Code expiré. Veuillez vous reconnecter.'];
    }

    if (!password_verify($code, $user['dernier_code_otp'])) {
        $_SESSION['2fa_attempts']['count']++;
        return [false, 'Code de vérification incorrect (tentative ' . $_SESSION['2fa_attempts']['count'] . '/' . MAX_LOGIN_ATTEMPTS . ').'];
    }

    // Code validé
    prepareQuery(
        'UPDATE utilisateurs SET dernier_code_otp = NULL, dernier_code_exp = NULL WHERE id = :id',
        ['id' => $userId]
    );
    unset($_SESSION['pending_2fa'], $_SESSION['2fa_attempts'], $_SESSION['otp_demo'], $_SESSION['2fa_user_meta']);

    // Démarrer la session sans régénération agressive pour éviter les boucles
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(false);
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();

    try {
        $ur = prepareQuery('SELECT role FROM utilisateurs WHERE id = :id', ['id' => $userId])->fetch();
        if ($ur && !empty($ur['role'])) {
            $_SESSION['user_role'] = $ur['role'];
        }
    } catch (Throwable $e) {
    }

    $token = generate_token();
    try {
        prepareQuery(
            'INSERT INTO sessions_utilisateur (user_id, token, ip_address, user_agent, expires_at)
             VALUES (:uid, :token, :ip, :ua, NOW() + make_interval(secs => :ttl))',
            [
                'uid' => $userId,
                'token' => hash('sha256', $token),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ttl' => SESSION_TIMEOUT,
            ]
        );
        $_SESSION['session_token'] = hash('sha256', $token);
    } catch (Throwable $e) {
        // En cas d'erreur, continuer sans token DB pour éviter le blocage
        unset($_SESSION['session_token']);
    }
    log_activity('auth.login', 'Connexion réussie');
    return [true, 'Authentification à deux facteurs réussie.'];
}

/**
 * Déconnecte l'utilisateur (révoque aussi le token session en base)
 */
function logout(string $raison = 'Déconnexion manuelle'): void
{
    if (isset($_SESSION['user_id'])) {
        log_activity('auth.logout', $raison);
        // Révoquer le token de session en base
        if (isset($_SESSION['session_token'])) {
            try {
                prepareQuery(
                    'DELETE FROM sessions_utilisateur WHERE user_id = :uid AND token = :token',
                    ['uid' => $_SESSION['user_id'], 'token' => $_SESSION['session_token']]
                );
            } catch (Exception $e) {
                error_log('Erreur révocation session : ' . $e->getMessage());
            }
        }
    }
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
 * Vérifie que le token de session en base est toujours valide
 */
function validate_session_token(): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (!isset($_SESSION['session_token'])) {
        return true;
    }
    try {
        $row = prepareQuery(
            'SELECT COUNT(*) AS nb FROM sessions_utilisateur
             WHERE user_id = :uid AND token = :token AND expires_at > NOW()',
            ['uid' => $_SESSION['user_id'], 'token' => $_SESSION['session_token']]
        )->fetch();
        return (int)($row['nb'] ?? 0) > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Récupère l'utilisateur courant (avec cache)
 */
function current_user(): ?array
{
    if (!is_logged_in()) return null;
    static $user = false;
    if ($user === false) {
        // Valider le token de session en base (révocation possible côté serveur)
        try {
            if (!validate_session_token()) {
                if (isset($_SESSION['session_token'])) {
                    logout('Session expirée');
                }
            }
        } catch (Throwable $e) {
            // Ignorer
        }
        try {
            $user = prepareQuery(
                'SELECT * FROM utilisateurs WHERE id = :id AND actif = TRUE',
                ['id' => $_SESSION['user_id']]
            )->fetch();
            if (!$user) {
                return null;
            }
        } catch (Throwable $e) {
            return null;
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
    if (!isset($_SESSION['user_id'])) {
        set_flash('warning', 'Veuillez vous connecter pour accéder à cette page.');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
    // La session peut référencer un compte supprimé ou désactivé. Sans ce
    // contrôle, les pages renvoient vers index.php qui renvoie lui-même vers
    // l'espace du rôle => boucle de redirections (« supprimez les cookies »).
    if (current_user() === null) {
        logout('Votre session n\'est plus valide. Veuillez vous reconnecter.');
    }
}

/**
 * Vérifie que l'utilisateur a l'un des rôles requis, sinon redirige
 */
function require_role(array $roles): void
{
    require_login();
    $role = current_role();
    if (!in_array($role, $roles, true)) {
        // Rediriger vers le dashboard de son propre rôle : la redirection doit
        // être envoyée sans code 403, sinon les navigateurs l'ignorent.
        $target = role_home((string)$role);
        if ($role !== null && $role !== '' && $target !== 'index.php') {
            header('Location: ' . BASE_URL . $target);
            exit;
        }
        // Rôle inconnu : ne jamais renvoyer vers index.php, qui redirigerait
        // aussitôt ici (boucle de redirections).
        logout('Votre compte n\'est associé à aucun espace valide. Contactez l\'administrateur.');
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
 * Génère et envoie un OTP de réinitialisation de mot de passe
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
        return [true, 'Si l\'email existe, un code de réinitialisation a été envoyé.'];
    }

    // Invalider d'anciens codes
    try {
        prepareQuery(
            "UPDATE password_resets SET used = TRUE WHERE user_id = :uid AND used = FALSE",
            ['uid' => $user['id']]
        );
    } catch (Throwable $e) {
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 600);

    prepareQuery(
        'INSERT INTO password_resets (user_id, token, expires_at)
         VALUES (:uid, :token, :exp)',
        ['uid' => $user['id'], 'token' => hash('sha256', $otp), 'exp' => $expires]
    );

    send_otp_reset_email($user['email'], trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')), $otp);

    $_SESSION['reset_email'] = $user['email'];
    $_SESSION['reset_user_id'] = (int)$user['id'];
    $_SESSION['reset_otp_sent_at'] = time();

    log_activity('auth.password_reset_request', 'Demande de réinitialisation de mot de passe par OTP');
    return [true, 'Un code à 6 chiffres a été envoyé à votre adresse email.'];
}

/**
 * Vérifie l'OTP et réinitialise le mot de passe
 */
function reset_password_with_otp(string $email, string $otp, string $newPassword): array
{
    $email = strtolower(trim($email));
    $otp = trim($otp);
    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        return [false, 'Code invalide.'];
    }
    if (strlen($newPassword) < 8) {
        return [false, 'Le mot de passe doit contenir au moins 8 caractères.'];
    }

    $user = prepareQuery(
        'SELECT * FROM utilisateurs WHERE email = :email',
        ['email' => $email]
    )->fetch();

    if (!$user) {
        return [false, 'Code invalide ou expiré.'];
    }

    $row = prepareQuery(
        'SELECT * FROM password_resets WHERE user_id = :uid AND token = :token AND used = FALSE ORDER BY id DESC LIMIT 1',
        ['uid' => $user['id'], 'token' => hash('sha256', $otp)]
    )->fetch();

    if (!$row || $row['expires_at'] < date('Y-m-d H:i:s', time())) {
        return [false, 'Code invalide ou expiré.'];
    }

    prepareQuery(
        'UPDATE utilisateurs SET password_hash = :hash, tentatives_connexion = 0,
         compte_verrouille = FALSE, verrou_date = NULL WHERE id = :uid',
        ['hash' => password_hash($newPassword, PASSWORD_BCRYPT), 'uid' => $user['id']]
    );
    prepareQuery(
        'UPDATE password_resets SET used = TRUE WHERE id = :id',
        ['id' => $row['id']]
    );
    // Autoriser la connexion sans 2FA juste après réinitialisation
    $_SESSION['skip_2fa_after_reset'] = $user['id'];
    $_SESSION['skip_2fa_after_reset_time'] = time();
    // Nettoyer les sessions de réinitialisation uniquement
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_otp_sent_at']);
    unset($_SESSION['reset_link_demo']);
    log_activity('auth.password_reset', 'Mot de passe réinitialisé par OTP');
    return [true, 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'];
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
    unset($_SESSION['reset_link_demo']);
    return [true, 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'];
}

// ============================================================
// ENVOI D'EMAILS
// MAIL_TRANSPORT=smtp → envoi réel via SMTP (Brevo, Gmail, hébergeur…)
// MAIL_TRANSPORT=log  → archivage dans storage/mail/ (boîte de démo,
//                        consultable dans Admin > Journal > Boîte email)
// Les emails de notification passent par la file d'attente
// (includes/email_notifications.php).
// ============================================================

function send_email(string $to, string $subject, string $body): bool
{
    mailer_charger_env();
    if (!function_exists('email_mettre_en_file')) {
        require_once __DIR__ . '/email_notifications.php';
    }

    $html = nl2br(e($body));
    $texte = $body;

    // Tout passe par la file d'attente comme les autres notifications
    $ok = email_mettre_en_file(
        $to,
        $subject,
        $html,
        $texte,
        'auth',
        null,
        'auth:' . md5($to . '|' . $subject . '|' . microtime(true))
    );

    // Trace dans la boîte locale
    email_store($to, $subject, $body, (bool)$ok, $ok ? 'en_attente' : 'echec');

    $GLOBALS['_mail_delivered'] = (bool)$ok;
    return (bool)$ok;
}

/**
 * Vrai si le dernier envoi a réellement été remis au transport.
 */
function last_mail_delivered(): bool
{
    return !empty($GLOBALS['_mail_delivered']);
}

/**
 * Vrai si la requête vient de la machine elle-même (127.0.0.1 / ::1).
 * Utilisé pour n'afficher des secrets (lien de réinitialisation) qu'en local.
 */
function is_local_request(): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return in_array($ip, ['127.0.0.1', '::1', 'localhost'], true);
}

/**
 * Autorise l'affichage à l'écran des secrets générés sans SMTP
 * (code OTP, lien de réinitialisation) : accès local ou DEMO_LINKS=true.
 * Ailleurs, ces secrets restent consultables dans Admin > Boîte email.
 */
function demo_disclose(): bool
{
    if (is_local_request()) return true;
    static $flag = null;
    if ($flag === null) {
        $flag = filter_var(config_env('DEMO_LINKS', 'false'), FILTER_VALIDATE_BOOLEAN);
    }
    return $flag;
}

function send_otp_email(string $to, string $name, string $otp): void
{
    mailer_charger_env();
    if (!function_exists('email_transporter')) {
        require_once __DIR__ . '/mailer.php';
    }

    $subject = 'Code de vérification My_School';
    $body = "Bonjour " . trim($name) . ",\n\nVotre code de vérification est : {$otp}\n" .
            "Ce code expire dans 5 minutes.\n\nSi vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.\n\nMy_School";

    $html = nl2br(e($body));
    $ok = email_transporter($to, $subject, $html, $body);
    email_store($to, $subject, $body, $ok, $ok ? 'sent' : 'echec');
    $GLOBALS['_mail_delivered'] = (bool)$ok;
}

function send_reset_email(string $to, string $name, string $token): void
{
    $link = BASE_URL . 'reset_password.php?token=' . urlencode($token);
    $subject = 'Réinitialisation de mot de passe My_School';
    $body = "Bonjour {$name},\n\nCliquez sur ce lien pour réinitialiser votre mot de passe :\n{$link}\n" .
            "Ce lien expire dans 1 heure.\n\nMy_School";
    send_email($to, $subject, $body);
}

function send_otp_reset_email(string $to, string $name, string $otp): void
{
    mailer_charger_env();
    if (!function_exists('email_transporter')) {
        require_once __DIR__ . '/mailer.php';
    }

    $subject = 'Code de réinitialisation My_School';
    $body  = "Bonjour " . trim($name) . ",\n\n";
    $body .= "Votre code de réinitialisation de mot de passe est :\n\n";
    $body .= "        " . $otp . "\n\n";
    $body .= "Ce code expire dans 10 minutes.\n\n";
    $body .= "Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.\n\n";
    $body .= "Cordialement,\nMy_School";

    $html = nl2br(e($body));
    $ok = email_transporter($to, $subject, $html, $body);
    email_store($to, $subject, $body, $ok, $ok ? 'sent' : 'echec');
    $GLOBALS['_mail_delivered'] = (bool)$ok;
}
