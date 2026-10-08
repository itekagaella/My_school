<?php
/**
 * Réinitialisation du mot de passe avec token
 */
require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
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
    <!-- Google Fonts : Plus Jakarta Sans (local) -->
    <link href="assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <!-- Font Awesome (local) -->
    <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo-circle"><i class="fa-solid fa-lock-open"></i></div>
            <h1>Nouveau mot de passe</h1>
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
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm'] ?? '';
                if ($password !== $confirm) {
                    $message = 'Les mots de passe ne correspondent pas.';
                    $type = 'error';
                } else {
                    $result = reset_password($token, $password);
                    if ($result[0]) {
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
            <input type="hidden" name="token" value="<?= e($token) ?>">
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
                <i class="fa-solid fa-check me-2"></i>Réinitialiser
            </button>
        </form>
        <?php else: ?>
            <div class="text-center">
                <a href="index.php" class="btn btn-primary w-100"><i class="fa-solid fa-right-to-bracket me-2"></i>Se connecter</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
