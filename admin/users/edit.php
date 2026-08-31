<?php
/**
 * Modification d'un utilisateur (Admin)
 * Inclut : réinitialisation du mot de passe, déblocage du compte, 2FA
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('users.edit');

$page_title = 'Modifier un utilisateur';
$active_menu = 'users_users';

$id = (int)get('id', 0);
$user = prepareQuery('SELECT * FROM utilisateurs WHERE id = :id', ['id' => $id])->fetch();
if (!$user) {
    set_flash('error', 'Utilisateur introuvable.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } elseif ($action === 'update') {
        $nom = clean_input($_POST['nom'] ?? '');
        $prenom = clean_input($_POST['prenom'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? $user['role'];
        $actif = isset($_POST['actif']);
        $deux_facteurs = isset($_POST['deux_facteurs']);

        if (empty($nom) || empty($prenom) || empty($email)) {
            set_flash('error', 'Champs obligatoires manquants.');
        } elseif (!is_valid_email($email)) {
            set_flash('error', 'Email invalide.');
        } elseif (!in_array($role, ['admin','eleve','prof','personnel'])) {
            set_flash('error', 'Rôle invalide.');
        } else {
            $dup = prepareQuery('SELECT id FROM utilisateurs WHERE email = :e AND id <> :id', ['e'=>$email,'id'=>$id])->fetch();
            if ($dup) {
                set_flash('error', 'Cet email est déjà utilisé par un autre compte.');
            } else {
                prepareQuery(
                    'UPDATE utilisateurs SET nom=:n, prenom=:p, email=:e, role=:r, actif=:a, deux_facteurs=:d, updated_at=NOW() WHERE id=:id',
                    ['n'=>$nom,'p'=>$prenom,'e'=>$email,'r'=>$role,'a'=>$actif,'d'=>$deux_facteurs,'id'=>$id]
                );
                log_activity('users.edit', 'Modification de l\'utilisateur ' . $email);
                set_flash('success', 'Utilisateur mis à jour.');
            }
        }
    } elseif ($action === 'reset_password') {
        $newPw = $_POST['new_password'] ?? '';
        if (strlen($newPw) < 8) {
            set_flash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
        } else {
            prepareQuery(
                'UPDATE utilisateurs SET password_hash=:h, tentatives_connexion=0, compte_verrouille=FALSE WHERE id=:id',
                ['h'=>password_hash($newPw, PASSWORD_BCRYPT), 'id'=>$id]
            );
            log_activity('users.reset_password', 'Réinitialisation du mot de passe de l\'utilisateur ' . $user['email']);
            set_flash('success', 'Mot de passe réinitialisé.');
        }
    } elseif ($action === 'unlock') {
        prepareQuery(
            'UPDATE utilisateurs SET compte_verrouille=FALSE, tentatives_connexion=0, verrou_date=NULL WHERE id=:id',
            ['id'=>$id]
        );
        log_activity('users.unlock', 'Déblocage du compte ' . $user['email']);
        set_flash('success', 'Compte débloqué.');
    } elseif ($action === 'toggle_2fa') {
        $newVal = !$user['deux_facteurs'];
        prepareQuery('UPDATE utilisateurs SET deux_facteurs=:d WHERE id=:id', ['d'=>$newVal,'id'=>$id]);
        set_flash('success', $newVal ? '2FA activée.' : '2FA désactivée.');
    }
    header('Location: edit.php?id=' . $id);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-user-pen me-2"></i>Informations</div>
            <div class="card-body">
                <?php display_flash(); ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Prénom</label>
                            <input type="text" name="prenom" class="form-control" value="<?= e($user['prenom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?= e($user['nom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Rôle</label>
                            <select name="role" class="form-select">
                                <?php foreach (['eleve'=>'Élève','prof'=>'Professeur','personnel'=>'Personnel','admin'=>'Administrateur'] as $r=>$l): ?>
                                    <option value="<?= $r ?>" <?= $user['role']===$r?'selected':'' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= $user['actif']?'checked':'' ?>>
                                <label class="form-check-label" for="actif">Compte actif</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="deux_facteurs" id="2fa" <?= $user['deux_facteurs']?'checked':'' ?>>
                                <label class="form-check-label" for="2fa">Authentification à deux facteurs</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                        <a href="index.php" class="btn btn-secondary">Retour</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Statut -->
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-shield-halved me-2"></i>Sécurité</div>
            <div class="card-body">
                <p><strong>Statut :</strong>
                    <?php if ($user['compte_verrouille']): ?><span class="badge bg-danger">Verrouillé</span>
                    <?php elseif ($user['actif']): ?><span class="badge bg-success">Actif</span>
                    <?php else: ?><span class="badge bg-secondary">Inactif</span><?php endif; ?>
                </p>
                <p><strong>2FA :</strong>
                    <?= $user['deux_facteurs'] ? '<span class="badge bg-success">Activée</span>' : '<span class="badge bg-secondary">Désactivée</span>' ?>
                </p>
                <p class="mb-1"><strong>Dernière connexion :</strong><br>
                    <small class="text-muted"><?= $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : 'Jamais' ?></small>
                </p>
                <hr>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="unlock">
                    <button class="btn btn-outline-warning w-100 mb-2" type="submit" <?= $user['compte_verrouille'] ? '' : 'disabled' ?>>
                        <i class="fa-solid fa-unlock me-1"></i>Débloquer le compte
                    </button>
                </form>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_2fa">
                    <button class="btn btn-outline-primary w-100" type="submit">
                        <i class="fa-solid fa-shield-halved me-1"></i><?= $user['deux_facteurs'] ? 'Désactiver' : 'Activer' ?> 2FA
                    </button>
                </form>
            </div>
        </div>

        <!-- Réinitialiser mot de passe -->
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-key me-2"></i>Réinitialiser mot de passe</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <div class="mb-2">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                        <div class="form-text">L'utilisateur devra utiliser ce mot de passe.</div>
                    </div>
                    <button type="submit" class="btn btn-outline-danger w-100"><i class="fa-solid fa-key me-1"></i>Réinitialiser</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
