<?php
/**
 * Demande de réinitialisation de mot de passe
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) header('Location: ' . BASE_URL);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - My_School</title>
    <!-- Google Fonts : Plus Jakarta Sans (local) -->
    <link href="assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <!-- Font Awesome (local) -->
    <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo-circle"><i class="fa-solid fa-key"></i></div>
            <h1>Mot de passe oublié</h1>
            <p>Entrez votre email pour recevoir un lien de réinitialisation</p>
        </div>

        <?php
        $message = null;
        $type = 'info';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $message = 'Session expirée, veuillez réessayer.';
                $type = 'error';
            } else {
                $email = clean_input($_POST['email'] ?? '');
                if (!is_valid_email($email)) {
                    $message = 'Veuillez entrer un email valide.';
                    $type = 'error';
                } else {
                    $result = request_password_reset($email);
                    $message = $result[1];
                    $type = $result[0] ? 'success' : 'error';
                }
            }
        }
        ?>

        <?php if (!empty($_SESSION['reset_link_demo']) && $_SESSION['reset_link_demo']['exp'] > time()): ?>
            <?php if (demo_disclose()): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-flask me-1"></i>
                    <strong>Mode démo (accès local) :</strong> aucun serveur SMTP configuré.
                    Lien de réinitialisation :
                    <a href="<?= e($_SESSION['reset_link_demo']['url']) ?>">cliquez ici pour continuer</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?= $type === 'success' ? 'success' : ($type === 'error' ? 'danger' : 'info') ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Adresse email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" required autofocus>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-paper-plane me-2"></i>Envoyer le lien
            </button>
            <div class="text-center mt-3">
                <a href="index.php" class="small text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i>Retour à la connexion</a>
            </div>
        </form>
    </div>
</body>
</html>
