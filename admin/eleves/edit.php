<?php
/**
 * Modification d'un élève (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('eleves.edit');

$id = (int)get('id', 0);
$el = prepareQuery('SELECT * FROM eleves WHERE id = :id', ['id' => $id])->fetch();
if (!$el) {
    set_flash('error', 'Élève introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Modifier un élève';
$active_menu = 'eleves';
$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $nom = clean_input($_POST['nom'] ?? '');
        $prenom = clean_input($_POST['prenom'] ?? '');
        $parent_nom = clean_input($_POST['parent_nom'] ?? '');
        $parent_tel = clean_input($_POST['parent_tel'] ?? '');
        $adresse = clean_input($_POST['adresse'] ?? '');
        $classe_id = (int)($_POST['classe_id'] ?? 0);

        if (empty($nom) || empty($prenom)) set_flash('error', 'Champs obligatoires manquants.');
        else {
            // Modifier avec requête préparée
            prepareQuery(
                'UPDATE eleves SET nom=:n, prenom=:p, classe_id=:c, parent_nom=:pn, parent_tel=:pt, adresse=:a, updated_at=NOW() WHERE id=:id',
                ['n'=>$nom,'p'=>$prenom,'c'=>$classe_id>0?$classe_id:null,'pn'=>$parent_nom,'pt'=>$parent_tel,'a'=>$adresse,'id'=>$id]
            );
            log_activity('eleves.edit', 'Modification de l\'élève ' . $el['matricule']);
            set_flash('success', 'Élève mis à jour.');
            header('Location: view.php?id=' . $id);
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-user-pen me-2"></i>Modifier : <?= e($el['prenom'] . ' ' . $el['nom']) ?> (<?= e($el['matricule']) ?>)</div>
    <div class="card-body">
        <?php display_flash(); ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Prénom</label>
                    <input type="text" name="prenom" class="form-control" value="<?= e($el['prenom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Nom</label>
                    <input type="text" name="nom" class="form-control" value="<?= e($el['nom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Classe</label>
                    <select name="classe_id" class="form-select">
                        <option value="0">-- Aucune --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $el['classe_id']==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= e($el['adresse']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Parent</label>
                    <input type="text" name="parent_nom" class="form-control" value="<?= e($el['parent_nom']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone parent</label>
                    <input type="text" name="parent_tel" class="form-control" value="<?= e($el['parent_tel']) ?>">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                <a href="view.php?id=<?= $el['id'] ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
