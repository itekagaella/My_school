<?php
/**
 * Vérification de l'authentification à deux facteurs (2FA)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Si pas de 2FA en attente
if (!isset($_SESSION['pending_2fa']) || empty($_SESSION['pending_2fa'])) {
    if (isset($_SESSION['user_id'])) {
        $role = $_SESSION['user_role'] ?? '';
        if (!$role) {
            try {
                $r = prepareQuery('SELECT role FROM utilisateurs WHERE id = :id', ['id' => $_SESSION['user_id']])->fetch();
                $role = $r['role'] ?? '';
            } catch (Throwable $e) {
            }
        }
        $target = role_home((string)$role);
        if ($role !== '' && $target !== 'index.php') {
            header('Location: ' . BASE_URL . $target);
            exit;
        }
        // Rôle vide/inconnu : une redirection vers index.php bouclerait.
        logout('Votre compte n\'est associé à aucun espace valide. Contactez l\'administrateur.');
    }
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $code = clean_input($_POST['code'] ?? '');
        if (empty($code)) {
            $error = 'Veuillez saisir le code.';
        } else {
            if (empty($_SESSION['2fa_attempts'])) $_SESSION['2fa_attempts'] = ['count' => 0, 'start' => time()];
            if ($_SESSION['2fa_attempts']['count'] >= MAX_LOGIN_ATTEMPTS) {
                if (time() - $_SESSION['2fa_attempts']['start'] < LOCKOUT_MINUTES * 60) {
                    $remaining = ceil((LOCKOUT_MINUTES * 60 - (time() - $_SESSION['2fa_attempts']['start'])) / 60);
                    $error = "Trop de tentatives 2FA. Réessayez dans {$remaining} minute(s).";
                } else {
                    $_SESSION['2fa_attempts'] = ['count' => 0, 'start' => time()];
                }
            }

            if (empty($error)) {
                $userId = $_SESSION['pending_2fa'];
                try {
                    $user = prepareQuery(
                        'SELECT * FROM utilisateurs WHERE id = :id',
                        ['id' => $userId]
                    )->fetch();
                } catch (Throwable $e) {
                    $user = null;
                }

                if (!$user || empty($user['dernier_code_otp']) || $user['dernier_code_exp'] < date('Y-m-d H:i:s', time())) {
                    unset($_SESSION['pending_2fa'], $_SESSION['2fa_attempts'], $_SESSION['otp_demo'], $_SESSION['2fa_user_meta'], $_SESSION['pending_2fa_user_role']);
                    set_flash('error', 'Le code de vérification a expiré ou le compte est introuvable. Veuillez vous reconnecter.');
                    header('Location: ' . BASE_URL . 'index.php');
                    exit;
                }

                if (!password_verify($code, $user['dernier_code_otp'])) {
                    $_SESSION['2fa_attempts']['count']++;
                    $error = 'Code de vérification incorrect (tentative ' . $_SESSION['2fa_attempts']['count'] . '/' . MAX_LOGIN_ATTEMPTS . ').';
                } else {
                    try {
                        prepareQuery(
                            'UPDATE utilisateurs SET dernier_code_otp = NULL, dernier_code_exp = NULL WHERE id = :id',
                            ['id' => $userId]
                        );
                    } catch (Throwable $e) {
                    }

                    unset($_SESSION['pending_2fa'], $_SESSION['2fa_attempts'], $_SESSION['otp_demo'], $_SESSION['2fa_user_meta']);

                    // Une seule régénération : deux appels successifs envoient
                    // deux cookies de session différents et certains navigateurs
                    // gardent le mauvais (session vide => retour à la connexion).
                    @session_regenerate_id(true);
                    $_SESSION = [];
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_role'] = $user['role'] ?? '';
                    $_SESSION['last_activity'] = time();
                    $_SESSION['login_time'] = time();

                    try {
                        $token = bin2hex(random_bytes(32));
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
                        unset($_SESSION['session_token']);
                    }

                    try {
                        log_activity('auth.login', 'Connexion réussie');
                    } catch (Throwable $e) {
                    }

                    unset($_SESSION['pending_2fa_user_role']);

                    $target = role_home((string)($_SESSION['user_role'] ?? ''));
                    if ($target === 'index.php') {
                        // Rôle vide/inconnu : rediriger vers index.php bouclerait.
                        logout('Votre compte n\'est associé à aucun espace valide. Contactez l\'administrateur.');
                    }
                    header('Location: ' . BASE_URL . $target);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification - My_School</title>
    <link href="../assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo-circle"><i class="fa-solid fa-shield-halved"></i></div>
            <h1>Vérification</h1>
            <p>Entrez le code à 6 chiffres envoyé par email</p>
        </div>

        <?php if (!empty($_SESSION['otp_demo']) && $_SESSION['otp_demo']['exp'] > time()): ?>
            <?php if (function_exists('demo_disclose') && demo_disclose()): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-flask me-1"></i>
                    <strong>Mode démo :</strong> aucun serveur SMTP configuré.
                    Voici votre code de vérification : <strong class="fs-5"><?= e($_SESSION['otp_demo']['code']) ?></strong>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Aucun serveur SMTP n'est configuré : le code est consultable par l'administrateur.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="mb-4">
                <label class="form-label">Code de vérification</label>
                <input type="text" name="code" class="form-control form-control-lg text-center" maxlength="6" inputmode="numeric" placeholder="••••••" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-check me-2"></i>Vérifier
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="logout.php" class="small text-decoration-none">Annuler et se déconnecter</a>
        </div>
    </div>
</body>
</html>
