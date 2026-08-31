<?php
/**
 * Création d'un utilisateur (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('users.create');

$page_title = 'Nouvel utilisateur';
$active_menu = 'users_users';

$error = '';
$u = ['nom'=>'','prenom'=>'','email'=>'','role'=>'eleve','actif'=>true,'deux_facteurs'=>false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $u['nom'] = clean_input($_POST['nom'] ?? '');
        $u['prenom'] = clean_input($_POST['prenom'] ?? '');
        $u['email'] = strtolower(trim($_POST['email'] ?? ''));
        $u['role'] = $_POST['role'] ?? 'eleve';
        $u['actif'] = isset($_POST['actif']);
        $u['deux_facteurs'] = isset($_POST['deux_facteurs']);
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        // Validations
        if (empty($u['nom']) || empty($u['prenom']) || empty($u['email'])) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (!is_valid_email($u['email'])) {
            $error = 'Adresse email invalide.';
        } elseif (!in_array($u['role'], ['admin','eleve','prof','personnel'])) {
            $error = 'Rôle invalide.';
        } elseif (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($password !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            // Vérifier email unique
            $exists = prepareQuery('SELECT id FROM utilisateurs WHERE email = :e', ['e' => $u['email']])->fetch();
            if ($exists) {
                $error = 'Un compte existe déjà avec cette adresse email.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                prepareQuery(
                    'INSERT INTO utilisateurs (nom, prenom, email, password_hash, role, actif, deux_facteurs)
                     VALUES (:n, :p, :e, :h, :r, :a, :d)',
                    ['n'=>$u['nom'], 'p'=>$u['prenom'], 'e'=>$u['email'], 'h'=>$hash, 'r'=>$u['role'],
                     'a'=>$u['actif'], 'd'=>$u['deux_facteurs']]
                );
                log_activity('users.create', 'Création utilisateur ' . $u['email'] . ' (rôle ' . $u['role'] . ')');
                set_flash('success', 'Utilisateur créé avec succès.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Créer un utilisateur</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Prénom</label>
                    <input type="text" name="prenom" class="form-control" value="<?= e($u['prenom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Nom</label>
                    <input type="text" name="nom" class="form-control" value="<?= e($u['nom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Rôle</label>
                    <select name="role" class="form-select">
                        <option value="eleve" <?= $u['role']==='eleve'?'selected':'' ?>>Élève</option>
                        <option value="prof" <?= $u['role']==='prof'?'selected':'' ?>>Professeur</option>
                        <option value="personnel" <?= $u['role']==='personnel'?'selected':'' ?>>Personnel</option>
                        <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrateur</option>
                    </select>
                    <div class="form-text">Le rôle détermine les permissions d'accès.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Mot de passe</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                    <div class="form-text">Minimum 8 caractères.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Confirmer</label>
                    <input type="password" name="confirm" class="form-control" minlength="8" required>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= $u['actif']?'checked':'' ?>>
                        <label class="form-check-label" for="actif">Compte actif</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="deux_facteurs" id="2fa" <?= $u['deux_facteurs']?'checked':'' ?>>
                        <label class="form-check-label" for="2fa">Activer l'authentification à deux facteurs (2FA)</label>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Créer</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
