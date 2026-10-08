<?php
/**
 * Modification d'un créneau horaire (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('horaires.edit');

$id = (int)get('id', 0);
$h = prepareQuery('SELECT * FROM horaires WHERE id = :id', ['id' => $id])->fetch();
if (!$h) {
    set_flash('error', 'Créneau introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Modifier un créneau';
$active_menu = 'horaires';

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
$matieres = prepareQuery('SELECT * FROM matieres ORDER BY nom_matiere')->fetchAll();
$profs = prepareQuery('SELECT * FROM profs WHERE statut = :s ORDER BY nom', ['s'=>'actif'])->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        $matiere_id = (int)($_POST['matiere_id'] ?? 0);
        $prof_id = (int)($_POST['prof_id'] ?? 0);
        $jour = $_POST['jour'] ?? 'Lundi';
        $heure_debut = $_POST['heure_debut'] ?? '';
        $heure_fin = $_POST['heure_fin'] ?? '';
        $salle = clean_input($_POST['salle'] ?? '');

        if ($classe_id <= 0 || $matiere_id <= 0) set_flash('error', 'Classe et matière requises.');
        elseif (empty($heure_debut) || empty($heure_fin)) set_flash('error', 'Heures requises.');
        elseif ($heure_debut >= $heure_fin) set_flash('error', 'Heure de fin invalide.');
        else {
            prepareQuery(
                'UPDATE horaires SET classe_id=:c, matiere_id=:m, prof_id=:p, jour=:j,
                 heure_debut=:hd, heure_fin=:hf, salle=:s, updated_at=NOW() WHERE id=:id',
                ['c'=>$classe_id,'m'=>$matiere_id,'p'=>$prof_id>0?$prof_id:null,'j'=>$jour,
                 'hd'=>$heure_debut,'hf'=>$heure_fin,'s'=>$salle,'id'=>$id]
            );
            log_activity('horaires.edit', 'Modification du créneau #' . $id);
            set_flash('success', 'Créneau mis à jour.');
            header('Location: index.php?classe_id=' . $classe_id);
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-pen me-2"></i>Modifier le créneau</div>
    <div class="card-body">
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Classe</label>
                    <select name="classe_id" class="form-select" required>
                        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $h['classe_id']==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Matière</label>
                    <select name="matiere_id" class="form-select" required>
                        <?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>" <?= $h['matiere_id']==$m['id']?'selected':'' ?>><?= e($m['nom_matiere']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Professeur</label>
                    <select name="prof_id" class="form-select">
                        <option value="0">-- Aucun --</option>
                        <?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>" <?= $h['prof_id']==$p['id']?'selected':'' ?>><?= e($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Jour</label>
                    <select name="jour" class="form-select">
                        <?php foreach (['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'] as $j): ?>
                            <option value="<?= $j ?>" <?= $h['jour']===$j?'selected':'' ?>><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label required">Heure début</label>
                    <input type="time" name="heure_debut" class="form-control" value="<?= e($h['heure_debut']) ?>" required></div>
                <div class="col-md-3"><label class="form-label required">Heure fin</label>
                    <input type="time" name="heure_fin" class="form-control" value="<?= e($h['heure_fin']) ?>" required></div>
                <div class="col-md-3"><label class="form-label">Salle</label>
                    <input type="text" name="salle" class="form-control" value="<?= e($h['salle']) ?>"></div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
