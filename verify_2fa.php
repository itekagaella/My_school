<?php
/**
 * Vérification de l'authentification à deux facteurs (2FA)
 */
require_once __DIR__ . '/includes/auth.php';

// Rediriger si pas de 2FA en attente ou déjà connecté
if (!isset($_SESSION['pending_2fa'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
if (is_logged_in()) {
    header('Location: ' . BASE_URL);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification - My_School</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
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
