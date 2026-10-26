<?php
/**
 * Gestion financière — Frais scolaires (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['finance']);
require_permission('finance.view');

$page_title = 'Frais scolaires';
$active_menu = 'frais';

$canManage = has_permission('finance.manage');
$anneeDefaut = get_param('annee_scolaire', date('Y') . '-' . (date('Y') + 1));
$niveaux = array_keys(classes_burundi());

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $libelle = clean_input($_POST['libelle'] ?? '');
            $description = clean_input($_POST['description'] ?? '');
            $montant = (float)str_replace(',', '.', (string)($_POST['montant'] ?? '0'));
            $niveau = clean_input($_POST['niveau'] ?? '');
            $annee = clean_input($_POST['annee_scolaire'] ?? $anneeDefaut);
            $obligatoire = isset($_POST['obligatoire']);
            $actif = isset($_POST['actif']);

            if ($libelle === '' || $montant < 0) {
                set_flash('error', 'Libellé requis et montant positif.');
            } else {
                if ($id > 0) {
                    prepareQuery(
                        'UPDATE frais_scolaires SET libelle=:l, description=:d, montant=:m, niveau=:n,
                         annee_scolaire=:a, obligatoire=:o, actif=:ac, updated_at=NOW() WHERE id=:id',
                        ['l'=>$libelle,'d'=>$description,'m'=>$montant,'n'=>($niveau ?: null),'a'=>$annee,
                         'o'=>$obligatoire,'ac'=>$actif,'id'=>$id]
                    );
                    log_activity('finance.frais_update', 'Frais #' . $id . ' modifié');
                    set_flash('success', 'Frais mis à jour.');
                } else {
                    prepareQuery(
                        'INSERT INTO frais_scolaires (libelle, description, montant, niveau, annee_scolaire, obligatoire, actif)
                         VALUES (:l, :d, :m, :n, :a, :o, :ac)',
                        ['l'=>$libelle,'d'=>$description,'m'=>$montant,'n'=>($niveau ?: null),'a'=>$annee,
                         'o'=>$obligatoire,'ac'=>$actif]
                    );
                    log_activity('finance.frais_create', 'Création du frais ' . $libelle);
                    set_flash('success', 'Frais créé.');
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            prepareQuery('DELETE FROM frais_scolaires WHERE id = :id', ['id' => $id]);
            log_activity('finance.frais_delete', 'Suppression du frais #' . $id);
            set_flash('success', 'Frais supprimé.');
        }
        header('Location: frais.php');
        exit;
    }
}

$edit = null;
if (($editId = (int)get('edit', 0)) > 0) {
    $edit = prepareQuery('SELECT * FROM frais_scolaires WHERE id = :id', ['id' => $editId])->fetch();
}

$frais = prepareQuery(
    "SELECT f.*, (SELECT COALESCE(SUM(montant),0) FROM paiements WHERE frais_id = f.id) AS encaisse
     FROM frais_scolaires f
     ORDER BY f.annee_scolaire DESC, f.libelle"
)->fetchAll();

$devise = devise();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Frais scolaires</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
</div>

<div class="row g-3">
    <?php if ($canManage): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid <?= $edit ? 'fa-pen' : 'fa-plus' ?> me-2"></i><?= $edit ? 'Modifier le frais' : 'Nouveau frais' ?></div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="mb-2">
                        <label class="form-label required">Libellé</label>
                        <input type="text" name="libelle" class="form-control" value="<?= e($edit['libelle'] ?? '') ?>" required placeholder="Ex: Frais de scolarité 7ème">
                    </div>
                    <div class="mb-2">
                        <label class="form-label required">Montant (<?= e($devise) ?>)</label>
                        <input type="number" step="0.01" min="0" name="montant" class="form-control" value="<?= e($edit['montant'] ?? '0') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Niveau / cycle</label>
                        <select name="niveau" class="form-select">
                            <option value="">Tous les niveaux</option>
                            <?php foreach ($niveaux as $niv): ?>
                                <option value="<?= e($niv) ?>" <?= ($edit['niveau'] ?? '') === $niv ? 'selected' : '' ?>><?= e($niv) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label required">Année scolaire</label>
                        <?= select_annee_scolaire('annee_scolaire', $edit['annee_scolaire'] ?? $anneeDefaut, ['class' => 'form-select', 'required' => true]) ?>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= e($edit['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="obligatoire" id="obligatoire" <?= (!isset($edit) || $edit['obligatoire']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="obligatoire">Frais obligatoire</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= (!isset($edit) || $edit['actif']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="actif">Actif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                    <?php if ($edit): ?><a href="frais.php" class="btn btn-secondary w-100 mt-2">Annuler</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= $canManage ? 'col-lg-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-list me-2"></i>Frais définis (<?= count($frais) ?>)</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Libellé</th><th>Niveau</th><th>Montant</th><th>Année</th><th>Encaissé</th><th>Statut</th><?php if ($canManage): ?><th class="text-end"></th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($frais)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Aucun frais défini.</td></tr>
                        <?php else: foreach ($frais as $f): ?>
                            <tr>
                                <td>
                                    <?= e($f['libelle']) ?>
                                    <?php if ($f['obligatoire']): ?><span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1">Obligatoire</span><?php endif; ?>
                                    <?php if (!empty($f['description'])): ?><br><small class="text-muted"><?= e(mb_substr($f['description'],0,70)) ?></small><?php endif; ?>
                                </td>
                                <td class="small"><?= e($f['niveau'] ?: 'Tous') ?></td>
                                <td class="fw-semibold"><?= e(format_montant($f['montant'])) ?></td>
                                <td class="small text-muted"><?= e($f['annee_scolaire']) ?></td>
                                <td class="small text-success"><?= e(format_montant($f['encaisse'])) ?></td>
                                <td><span class="badge bg-<?= $f['actif'] ? 'success' : 'secondary' ?>"><?= $f['actif'] ? 'Actif' : 'Inactif' ?></span></td>
                                <?php if ($canManage): ?>
                                <td class="text-end text-nowrap">
                                    <a href="frais.php?edit=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                    <form method="post" action="" class="d-inline" onsubmit="return confirmDelete('Supprimer ce frais ?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
