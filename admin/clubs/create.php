<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('clubs.create');

$page_title = 'Nouveau club';
$active_menu = 'clubs';

$error = '';
$d = ['nom_club'=>'', 'description'=>'', 'prof_responsable_id'=>''];

$profs = prepareQuery("SELECT id, nom, prenom, matricule FROM profs WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $d['nom_club'] = clean_input($_POST['nom_club'] ?? '');
        $d['description'] = clean_input($_POST['description'] ?? '');
        $d['prof_responsable_id'] = (int)($_POST['prof_responsable_id'] ?? 0);
        $statut = ($_POST['statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif';

        if (empty($d['nom_club'])) {
            $error = 'Le nom du club est obligatoire.';
        } elseif ($d['prof_responsable_id'] <= 0) {
            $error = 'Veuillez sélectionner un professeur responsable.';
        } else {
            try {
                $logo = null;
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
                        'INSERT INTO clubs (nom_club, description, prof_responsable_id, logo, date_creation, statut)
                         VALUES (:n, :d, :p, :l, CURRENT_DATE, :s)',
                        ['n'=>$d['nom_club'], 'd'=>$d['description'], 'p'=>$d['prof_responsable_id'], 'l'=>$logo, 's'=>$statut]
                    );
                    $clubId = (int)getDB()->lastInsertId();
                    log_activity('clubs.create', 'Création du club ' . $d['nom_club'] . ' (ID ' . $clubId . ')');
                    set_flash('success', 'Club créé avec succès.');
                    header('Location: view.php?id=' . $clubId);
                    exit;
                }
            } catch (Exception $ex) {
                $error = 'Erreur lors de la création : ' . $ex->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-people-group me-2"></i>Créer un club</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Nom du club</label>
                    <input type="text" name="nom_club" class="form-control" value="<?= e($d['nom_club']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Professeur responsable</label>
                    <select name="prof_responsable_id" class="form-select" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($profs as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $d['prof_responsable_id']==$p['id']?'selected':'' ?>><?= e($p['prenom'] . ' ' . $p['nom']) ?> (<?= e($p['matricule']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="actif" selected>Actif</option>
                        <option value="inactif">Inactif</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo (optionnel)</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= e($d['description']) ?></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Créer le club</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
