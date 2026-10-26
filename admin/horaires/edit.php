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
$salles = salles_options();
$creneaux = creneaux_cours();
$creneauActuel = creneau_standard($h['heure_debut'], $h['heure_fin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        $matiere_id = (int)($_POST['matiere_id'] ?? 0);
        $prof_id = (int)($_POST['prof_id'] ?? 0);
        $jour = $_POST['jour'] ?? 'Lundi';
        $creneauChoisi = (string)($_POST['creneau'] ?? '');
        $creneau = null;
        foreach ($creneaux as $c) {
            if (creneau_valeur($c) === $creneauChoisi) {
                $creneau = $c;
                break;
            }
        }
        if ($creneau !== null) {
            $heure_debut = $creneau['debut'];
            $heure_fin   = $creneau['fin'];
        } elseif ($creneauChoisi === 'existant') {
            // Ancien créneau hors norme : on le conserve tant que l'admin ne le change pas.
            $heure_debut = substr((string)$h['heure_debut'], 0, 5);
            $heure_fin   = substr((string)$h['heure_fin'], 0, 5);
        } else {
            $heure_debut = '';
            $heure_fin   = '';
        }
        $salle = clean_input($_POST['salle'] ?? '');
        if ($salle === '' && $classe_id > 0) {
            $salle = salle_par_defaut_classe($classe_id);
        }
        $salleValide = ($salle === '') || ($salle === (string)($h['salle'] ?? ''));
        if (!$salleValide) {
            $salleValide = (bool)prepareQuery(
                'SELECT 1 FROM salles WHERE nom = :n AND actif = TRUE',
                ['n' => $salle]
            )->fetchColumn();
        }

        if ($classe_id <= 0 || $matiere_id <= 0) set_flash('error', 'Classe et matière requises.');
        elseif ($heure_debut === '' || $heure_fin === '') set_flash('error', 'Sélectionnez un créneau horaire.');
        elseif ($heure_debut >= $heure_fin) set_flash('error', 'Heure de fin invalide.');
        elseif (!$salleValide) set_flash('error', 'Salle invalide : choisissez une salle dans la liste.');
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
                        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $h['classe_id']==$c['id']?'selected':'' ?>><?= e(classe_label($c['nom_classe'], $c['section'] ?? null)) ?></option><?php endforeach; ?>
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
                <div class="col-md-3"><label class="form-label required">Créneau</label>
                    <select name="creneau" class="form-select" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($creneaux as $c): ?>
                            <option value="<?= e(creneau_valeur($c)) ?>" <?= ($creneauActuel !== null && $creneauActuel['debut']===$c['debut'] && $creneauActuel['fin']===$c['fin']) ? 'selected' : '' ?>>
                                <?= e(creneau_label($c)) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($creneauActuel === null && !empty($h['heure_debut'])): ?>
                            <option value="existant" selected>Créneau existant : <?= e(substr($h['heure_debut'],0,5)) ?> - <?= e(substr($h['heure_fin'],0,5)) ?></option>
                        <?php endif; ?>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Salle</label>
                    <select name="salle" id="salle" class="form-select">
                        <option value="">-- Aucune --</option>
                        <?= salles_select_html($salles, $h['salle'] ?? '') ?>
                    </select></div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var classe = document.querySelector('select[name="classe_id"]');
    var salle = document.getElementById('salle');
    if (!classe || !salle) return;
    classe.addEventListener('change', function () {
        var option = salle.querySelector('option[data-classe-id="' + classe.value + '"]');
        if (option) salle.value = option.value;
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
