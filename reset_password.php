<?php
/**
 * Réinitialisation du mot de passe avec OTP
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL);
    exit;
}

$email = $_SESSION['reset_email'] ?? '';
$tokenFromUrl = $_GET['token'] ?? '';

// Si vient avec un token (ancien lien), rediriger vers forgot
if (!empty($tokenFromUrl) && empty($email)) {
    header('Location: ' . BASE_URL . 'forgot_password.php');
    exit;
}

if (empty($email)) {
    header('Location: ' . BASE_URL . 'forgot_password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - My_School</title>
    <link href="assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo-circle"><i class="fa-solid fa-shield-halved"></i></div>
            <h1>Vérification par code</h1>
            <p>Entrez le code reçu par email et votre nouveau mot de passe</p>
        </div>

        <?php
        $message = null;
        $type = 'info';
        $done = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $message = 'Session expirée, veuillez réessayer.';
                $type = 'error';
            } else {
                $otp = trim($_POST['otp'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm'] ?? '';

                if ($password !== $confirm) {
                    $message = 'Les mots de passe ne correspondent pas.';
                    $type = 'error';
                } else {
                    $result = reset_password_with_otp($email, $otp, $password);
                    if ($result[0]) {
                        unset($_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['reset_otp_sent_at']);
                        $message = $result[1];
                        $type = 'success';
                        $done = true;
                    } else {
                        $message = $result[1];
                        $type = 'error';
                    }
                }
            }
        }
        ?>

        <?php if ($message): ?>
            <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if (!$done): ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Code à 6 chiffres</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                    <input type="text" name="otp" class="form-control text-center" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus placeholder="000000">
                </div>
                <div class="form-text">Envoyé à <?= e($email) ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Nouveau mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                </div>
                <div class="form-text">Minimum 8 caractères.</div>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirmer le mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="confirm" class="form-control" minlength="8" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-check me-2"></i>Réinitialiser mon mot de passe
            </button>
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="small text-decoration-none"><i class="fa-solid fa-rotate-left me-1"></i>Renvoyer un code</a>
            </div>
        </form>
        <?php else: ?>
            <div class="text-center">
                <a href="index.php" class="btn btn-primary w-100"><i class="fa-solid fa-right-to-bracket me-2"></i>Se connecter</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
