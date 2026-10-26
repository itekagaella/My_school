<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();

$page_title = 'Mon profil';
$active_menu = 'profil';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'infos') {
        $nom = clean_input($_POST['nom'] ?? '');
        $prenom = clean_input($_POST['prenom'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        if ($nom && $prenom && is_valid_email($email)) {
            prepareQuery('UPDATE utilisateurs SET nom=:n, prenom=:p, email=:e, updated_at=NOW() WHERE id=:id',
                ['n'=>$nom,'p'=>$prenom,'e'=>$email,'id'=>$user['id']]);

            $erreurPhoto = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $r = upload_file($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_EXTENSIONS, ALLOWED_IMAGE_MIMES);
                if ($r[0]) {
                    supprimer_photo_profil($user['avatar'] ?? null);
                    prepareQuery('UPDATE utilisateurs SET avatar=:a WHERE id=:id',
                        ['a'=>str_replace(ROOT_PATH, '', $r[2]),'id'=>$user['id']]);
                } else {
                    $erreurPhoto = $r[1];
                }
            }

            log_activity('user.profile_update', 'Mise à jour du profil');
            set_flash('success', 'Profil mis à jour.');
            if ($erreurPhoto !== null) {
                set_flash('error', 'Photo de profil non enregistrée : ' . $erreurPhoto);
            }
        } else {
            set_flash('error', 'Veuillez renseigner des informations valides.');
        }
        header('Location: profil.php');
        exit;
    }

    if ($action === 'avatar_delete') {
        supprimer_photo_profil($user['avatar'] ?? null);
        prepareQuery('UPDATE utilisateurs SET avatar=NULL, updated_at=NOW() WHERE id=:id', ['id'=>$user['id']]);
        log_activity('user.avatar_delete', 'Suppression de la photo de profil');
        set_flash('success', 'Photo de profil supprimée.');
        header('Location: profil.php');
        exit;
    }

    if ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (password_verify($old, $user['password_hash']) && strlen($new) >= 8) {
            prepareQuery('UPDATE utilisateurs SET password_hash=:h, updated_at=NOW() WHERE id=:id',
                ['h'=>password_hash($new, PASSWORD_BCRYPT),'id'=>$user['id']]);
            log_activity('user.password_change', 'Changement de mot de passe');
            set_flash('success', 'Mot de passe modifié.');
        } else {
            set_flash('error', 'Ancien mot de passe incorrect ou nouveau trop court (min 8 caractères).');
        }
        header('Location: profil.php');
        exit;
    }
}

$connexions = prepareQuery(
    'SELECT * FROM historique_connexions WHERE user_id=:id ORDER BY date_connexion DESC LIMIT 10',
    ['id'=>$user['id']])->fetchAll();

$is_admin = $user['role'] === 'admin';
require_once __DIR__ . '/../includes/header.php';
if ($is_admin) {
    require_once __DIR__ . '/../includes/sidebar_admin.php';
} else {
    require_once __DIR__ . '/../includes/sidebar_client.php';
}
?>
<?php display_flash(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <?php $avatarUrl = photo_profil_url($user['avatar'] ?? null); ?>
                <img id="avatarPreview" src="<?= $avatarUrl ? e($avatarUrl) : '' ?>" alt="Photo de profil"
                     class="rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover;<?= $avatarUrl ? '' : 'display:none;' ?>">
                <div id="avatarInitials" class="rounded-circle bg-primary text-white mb-3"
                     style="width:120px;height:120px;font-size:48px;<?= $avatarUrl ? 'display:none;' : 'display:inline-flex;align-items:center;justify-content:center;' ?>">
                    <?= e(strtoupper(mb_substr($user['prenom'],0,1) . mb_substr($user['nom'],0,1))) ?>
                </div>
                <?php if ($avatarUrl): ?>
                    <form method="post" action="" class="mb-2" onsubmit="return confirm('Supprimer votre photo de profil ?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="avatar_delete">
                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer la photo</button>
                    </form>
                <?php endif; ?>
                <h5><?= e($user['prenom'].' '.$user['nom']) ?></h5>
                <span class="badge bg-<?= $user['role']==='admin'?'danger':'primary' ?>"><?= e(strtoupper($user['role'])) ?></span>
                <p class="text-muted small mt-2 mb-0"><?= e($user['email']) ?></p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">Dernières connexions</div>
            <ul class="list-group list-group-flush">
                <?php if (empty($connexions)): ?>
                    <li class="list-group-item text-muted text-center">Aucune</li>
                <?php else: foreach ($connexions as $c): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <div>
                            <span class="badge bg-<?= $c['succes']?'success':'danger' ?>"><?= $c['succes']?'OK':'échec' ?></span>
                            <small><?= e($c['ip_address']) ?></small>
                        </div>
                        <small class="text-muted"><?= date('d/m H:i', strtotime($c['date_connexion'])) ?></small>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">Mes informations</div>
            <div class="card-body">
                <form method="post" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="infos">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?= e($user['nom']) ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control" value="<?= e($user['prenom']) ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Photo de profil (optionnel)</label>
                        <input type="file" name="avatar" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp">
                        <div class="form-text">PNG, JPG, GIF ou WebP — max <?= e(format_taille(upload_max_bytes())) ?>.</div></div>
                    <button class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Enregistrer</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Changer le mot de passe</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3"><label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="old_password" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8"></div>
                    <button class="btn btn-warning"><i class="fa-solid fa-key me-1"></i>Changer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
