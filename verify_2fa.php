<?php
/**
 * Vérification de l'authentification à deux facteurs (2FA)
 */
require_once __DIR__ . '/includes/auth.php';

// Rediriger si pas de 2FA en attente
if (!isset($_SESSION['pending_2fa']) || empty($_SESSION['pending_2fa'])) {
    if (isset($_SESSION['user_id']) && empty($_SESSION['pending_2fa'])) {
        $role = $_SESSION['user_role'] ?? '';
        if (!$role) {
            try {
                $r = prepareQuery('SELECT role FROM utilisateurs WHERE id = :id', ['id' => $_SESSION['user_id']])->fetch();
                $role = $r['role'] ?? '';
            } catch (Throwable $e) {
            }
        }
        $redirects = [
            'admin' => 'admin/index.php',
            'eleve' => 'client/eleve/index.php',
            'prof'  => 'client/prof/index.php',
            'personnel' => 'client/personnel/index.php',
            'surveillant' => 'client/surveillant/index.php',
            'finance' => 'client/finance/index.php',
            'discipline' => 'client/discipline/index.php',
        ];
        header('Location: ' . BASE_URL . ($redirects[$role] ?? 'index.php'));
        exit;
    }
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification - My_School</title>
    <!-- Google Fonts : Plus Jakarta Sans (local) -->
    <link href="assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <!-- Font Awesome (local) -->
    <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo-circle"><i class="fa-solid fa-shield-halved"></i></div>
            <h1>Vérification</h1>
            <p>Entrez le code à 6 chiffres envoyé par email</p>
        </div>

        <?php
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $error = 'Session expirée, veuillez réessayer.';
            } else {
                $code = clean_input($_POST['code'] ?? '');
                if (empty($code)) {
                    $error = 'Veuillez saisir le code.';
                } else {
                    $result = verify_2fa($code);
                    if ($result[0]) {
                        set_flash('success', 'Authentification réussie.');
                        $role = $_SESSION['user_role'] ?? $_SESSION['pending_2fa_user_role'] ?? null;
                        if (!$role && isset($_SESSION['user_id'])) {
                            try {
                                $r = prepareQuery('SELECT role FROM utilisateurs WHERE id = :id', ['id' => $_SESSION['user_id']])->fetch();
                                $role = $r['role'] ?? '';
                            } catch (Throwable $e) {
                                $role = '';
                            }
                        }
                        $role = $role ?: '';
                        $redirects = [
                            'admin' => 'admin/index.php',
                            'eleve' => 'client/eleve/index.php',
                            'prof'  => 'client/prof/index.php',
                            'personnel' => 'client/personnel/index.php',
                            'surveillant' => 'client/surveillant/index.php',
                            'finance' => 'client/finance/index.php',
                            'discipline' => 'client/discipline/index.php',
                        ];
                        unset($_SESSION['pending_2fa_user_role']);
                        $target = $redirects[$role] ?? ('client/' . $role . '/index.php');
                        if ($role === 'admin') {
                            $target = 'admin/index.php';
                        }
                        header('Location: ' . BASE_URL . $target);
                        exit;
                    } else {
                        $error = $result[1];
                    }
                }
            }
        }
        ?>

        <?php if (!empty($_SESSION['otp_demo']) && $_SESSION['otp_demo']['exp'] > time()): ?>
            <?php if (demo_disclose()): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-flask me-1"></i>
                    <strong>Mode démo :</strong> aucun serveur SMTP configuré (<code>MAIL_ENABLED=false</code>).
                    Voici votre code de vérification : <strong class="fs-5"><?= e($_SESSION['otp_demo']['code']) ?></strong>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Aucun serveur SMTP n'est configuré : le code est consultable par
                    l'administrateur (Admin &gt; Journal &gt; Boîte email).
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
