<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('clubs.edit');

$id = (int)get('id', 0);
$club = prepareQuery('SELECT * FROM clubs WHERE id = :id', ['id' => $id])->fetch();
if (!$club) {
    set_flash('error', 'Club introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Modifier un club';
$active_menu = 'clubs';
$profs = prepareQuery("SELECT id, nom, prenom, matricule FROM profs WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $nom_club = clean_input($_POST['nom_club'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        $prof_responsable_id = (int)($_POST['prof_responsable_id'] ?? 0);
        $statut = ($_POST['statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif';

        if (empty($nom_club)) {
            $error = 'Le nom du club est obligatoire.';
        } elseif ($prof_responsable_id <= 0) {
            $error = 'Veuillez sélectionner un professeur responsable.';
        } else {
            try {
                $logo = $club['logo'];
                if (!empty($_FILES['logo']['name'])) {
                    $upload = upload_file($_FILES['logo'], 'clubs');
                    if (!$upload[0]) {
                        $error = $upload[1];
                    } else {
                        $logo = $upload[2];
                    }
                }

                if (!$error) {
                    prepareQuery(
                        'UPDATE clubs SET nom_club=:n, description=:d, prof_responsable_id=:p, logo=:l, statut=:s, updated_at=NOW() WHERE id=:id',
                        ['n'=>$nom_club, 'd'=>$description, 'p'=>$prof_responsable_id, 'l'=>$logo, 's'=>$statut, 'id'=>$id]
                    );
                    log_activity('clubs.edit', 'Modification du club ' . $club['nom_club']);
                    set_flash('success', 'Club mis à jour.');
                    header('Location: view.php?id=' . $id);
                    exit;
                }
            } catch (Exception $ex) {
                $error = 'Erreur lors de la mise à jour : ' . $ex->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-pen me-2"></i>Modifier : <?= e($club['nom_club']) ?></div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php display_flash(); ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Nom du club</label>
                    <input type="text" name="nom_club" class="form-control" value="<?= e($club['nom_club']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Professeur responsable</label>
                    <select name="prof_responsable_id" class="form-select" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($profs as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $club['prof_responsable_id']==$p['id']?'selected':'' ?>><?= e($p['prenom'] . ' ' . $p['nom']) ?> (<?= e($p['matricule']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="actif" <?= $club['statut']==='actif'?'selected':'' ?>>Actif</option>
                        <option value="inactif" <?= $club['statut']==='inactif'?'selected':'' ?>>Inactif</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo (laisser vide pour conserver)</label>
                    <?php if ($club['logo'] && file_exists(ROOT_PATH . $club['logo'])): ?>
                        <div class="mb-2"><img src="<?= BASE_URL . e($club['logo']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:50%;" alt=""></div>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= e($club['description']) ?></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                <a href="view.php?id=<?= $club['id'] ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
