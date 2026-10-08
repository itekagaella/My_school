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
    <!-- Google Fonts : Plus Jakarta Sans (local) -->
    <link href="assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <!-- Font Awesome (local) -->
    <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
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
        $profils_valides = ['eleve', 'prof', 'personnel', 'admin'];
        $profil = clean_input($_POST['profil'] ?? $_GET['profil'] ?? '');
        if (!in_array($profil, $profils_valides, true)) $profil = '';
        $mode_admin = ($profil === 'admin');

        $profil_label = ['eleve' => 'Élève', 'prof' => 'Professeur', 'personnel' => 'Personnel', 'admin' => 'Administrateur'];
        $profil_icone = ['eleve' => 'fa-user-graduate', 'prof' => 'fa-chalkboard-user', 'personnel' => 'fa-briefcase'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $error = 'Session expirée, veuillez réessayer.';
            } else {
                $profil = clean_input($_POST['profil'] ?? '');
                $email = clean_input($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if (!in_array($profil, $profils_valides, true)) {
                    $error = 'Sélectionnez votre profil de connexion.';
                } elseif (empty($email) || empty($password)) {
                    $error = 'Veuillez remplir tous les champs.';
                } else {
                    $result = login($email, $password, $profil);
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
                        // Conserver l'email saisi, pas le profil si le rendu doit rester cohérent
                        $mode_admin = ($profil === 'admin');
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
            <?php if ($mode_admin): ?>
                <input type="hidden" name="profil" value="admin">
                <div class="auth-role-admin">
                    <i class="fa-solid fa-shield-halved me-2"></i>Connexion à l'espace administration
                </div>
            <?php else: ?>
                <fieldset class="auth-role-picker">
                    <legend>Je me connecte en tant que</legend>
                    <div class="auth-role-grid">
                        <?php foreach (['eleve', 'prof', 'personnel'] as $p): ?>
                            <label class="auth-role-tile" for="profil-<?= $p ?>">
                                <input type="radio" name="profil" id="profil-<?= $p ?>" value="<?= $p ?>" <?= $profil === $p ? 'checked' : '' ?> required>
                                <i class="fa-solid <?= $profil_icone[$p] ?>"></i>
                                <span><?= $profil_label[$p] ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>
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
            <?php if ($mode_admin): ?>
                <a href="index.php" class="fw-semibold text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i>Connexion à l'espace client</a>
            <?php else: ?>
                Besoin d'un compte ? Contactez l'administrateur.
                <br><a href="index.php?profil=admin" class="fw-semibold text-decoration-none">Espace administration</a>
            <?php endif; ?>
        </p>
    </div>
</body>
</html>
