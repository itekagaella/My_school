<?php
/**
 * Page d'authentification principale
 * Redirige vers le bon tableau de bord selon le rôle
 */
require_once __DIR__ . '/includes/auth.php';

// Si déjà connecté, rediriger vers le bon espace
if (is_logged_in()) {
    $role = current_role();
    $redirects = [
        'admin' => 'admin/index.php',
        'eleve' => 'client/eleve/index.php',
        'prof'  => 'client/prof/index.php',
        'personnel' => 'client/personnel/index.php',
    ];
    header('Location: ' . BASE_URL . ($redirects[$role] ?? 'index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - My_School</title>
    <!-- Google Fonts : Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Styles personnalisés (Academix UI) -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo-circle"><i class="fa-solid fa-graduation-cap"></i></div>
            <h1>My_School</h1>
            <p>Système de Gestion Scolaire</p>
        </div>

        <?php
        $error = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $error = 'Session expirée, veuillez réessayer.';
            } else {
                $email = clean_input($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($email) || empty($password)) {
                    $error = 'Veuillez remplir tous les champs.';
                } else {
                    $result = login($email, $password);
                    if ($result[1] === '2FA_REQUIRED') {
                        set_flash('info', 'Un code de vérification a été envoyé par email.');
                        header('Location: ' . BASE_URL . 'verify_2fa.php');
                        exit;
                    } elseif ($result[0]) {
                        set_flash('success', 'Bienvenue !');
                        $role = current_role();
                        $redirects = [
                            'admin' => 'admin/index.php',
                            'eleve' => 'client/eleve/index.php',
                            'prof'  => 'client/prof/index.php',
                            'personnel' => 'client/personnel/index.php',
                        ];
                        header('Location: ' . BASE_URL . ($redirects[$role] ?? 'index.php'));
                        exit;
                    } else {
                        $error = $result[1];
                    }
                }
            }
        }
        ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php display_flash(); ?>

        <form method="post" action="" autocomplete="on">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Adresse email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="exemple@exemple.com" value="<?= e($email) ?>" required autofocus>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="forgot_password.php" class="small text-decoration-none fw-semibold text-muted">Mot de passe oublié ?</a>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Se connecter
            </button>
        </form>

        <div class="divider"></div>
        <p class="text-center text-muted small mb-0">
            Besoin d'un compte ? Contactez l'administrateur.
        </p>
    </div>
</body>
</html>
