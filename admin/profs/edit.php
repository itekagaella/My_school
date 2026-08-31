<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('profs.edit');

$id = (int)get('id', 0);
$prof = prepareQuery('SELECT * FROM profs WHERE id = :id', ['id' => $id])->fetch();
if (!$prof) {
    set_flash('error', 'Professeur introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Modifier un professeur';
$active_menu = 'profs';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $nom = clean_input($_POST['nom'] ?? '');
        $prenom = clean_input($_POST['prenom'] ?? '');
        $specialite = clean_input($_POST['specialite'] ?? '');
        $tel = clean_input($_POST['tel'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $adresse = clean_input($_POST['adresse'] ?? '');

        if (empty($nom) || empty($prenom) || empty($specialite)) set_flash('error', 'Champs obligatoires manquants.');
        else {
            if ($email && $email !== $prof['email']) {
                $exists = prepareQuery('SELECT id FROM profs WHERE email = :e AND id != :id', ['e'=>$email, 'id'=>$id])->fetch();
                if ($exists) {
                    set_flash('error', 'Un professeur existe déjà avec cet email.');
                    header('Location: edit.php?id=' . $id);
                    exit;
                }
            }
            prepareQuery(
                'UPDATE profs SET nom=:n, prenom=:p, specialite=:sp, tel=:t, email=:e, adresse=:a, updated_at=NOW() WHERE id=:id',
                ['n'=>$nom,'p'=>$prenom,'sp'=>$specialite,'t'=>$tel,'e'=>$email,'a'=>$adresse,'id'=>$id]
            );
            if ($prof['user_id']) {
                prepareQuery('UPDATE utilisateurs SET nom=:n, prenom=:p WHERE id=:uid', ['n'=>$nom,'p'=>$prenom,'uid'=>$prof['user_id']]);
            }
            log_activity('profs.edit', 'Modification du professeur ' . $prof['matricule']);
            set_flash('success', 'Professeur mis à jour.');
            header('Location: view.php?id=' . $id);
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-user-pen me-2"></i>Modifier : <?= e($prof['prenom'] . ' ' . $prof['nom']) ?> (<?= e($prof['matricule']) ?>)</div>
    <div class="card-body">
        <?php display_flash(); ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Prénom</label>
                    <input type="text" name="prenom" class="form-control" value="<?= e($prof['prenom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Nom</label>
                    <input type="text" name="nom" class="form-control" value="<?= e($prof['nom']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Spécialité</label>
                    <input type="text" name="specialite" class="form-control" value="<?= e($prof['specialite']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="tel" class="form-control" value="<?= e($prof['tel']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($prof['email']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= e($prof['adresse']) ?>">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                <a href="view.php?id=<?= $prof['id'] ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
