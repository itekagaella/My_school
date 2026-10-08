<?php
/**
 * Création d'un créneau horaire (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('horaires.create');

$page_title = 'Ajouter un créneau';
$active_menu = 'horaires';

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
$matieres = prepareQuery('SELECT * FROM matieres ORDER BY nom_matiere')->fetchAll();
$profs = prepareQuery('SELECT * FROM profs WHERE statut = :s ORDER BY nom', ['s'=>'actif'])->fetchAll();

$error = '';
$d = ['classe_id'=>(int)get('classe_id',0),'matiere_id'=>'','prof_id'=>'','jour'=>'Lundi','heure_debut'=>'08:00','heure_fin'=>'09:00','salle'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $d['classe_id'] = (int)($_POST['classe_id'] ?? 0);
        $d['matiere_id'] = (int)($_POST['matiere_id'] ?? 0);
        $d['prof_id'] = (int)($_POST['prof_id'] ?? 0);
        $d['jour'] = $_POST['jour'] ?? 'Lundi';
        $d['heure_debut'] = $_POST['heure_debut'] ?? '';
        $d['heure_fin'] = $_POST['heure_fin'] ?? '';
        $d['salle'] = clean_input($_POST['salle'] ?? '');

        if ($d['classe_id'] <= 0 || $d['matiere_id'] <= 0) set_flash('error', 'Classe et matière requises.');
        elseif (empty($d['heure_debut']) || empty($d['heure_fin'])) set_flash('error', 'Heures requises.');
        elseif ($d['heure_debut'] >= $d['heure_fin']) set_flash('error', 'L\'heure de fin doit être après l\'heure de début.');
        else {
            $annee = get_param('annee_scolaire', date('Y') . '-' . (date('Y')+1));
            if ($d['prof_id'] > 0) {
                // Vérifier conflit prof (surcharge au même créneau)
                $conflit = prepareQuery(
                    "SELECT COUNT(*) AS nb FROM horaires
                     WHERE prof_id = :p AND jour = :j
                       AND heure_debut < :fin AND heure_fin > :debut AND statut = 'publie'",
                    ['p'=>$d['prof_id'],'j'=>$d['jour'],'fin'=>$d['heure_fin'],'debut'=>$d['heure_debut']]
                )->fetch();
                if (($conflit['nb'] ?? 0) > 0) {
                    $error = 'Ce professeur est déjà occupé sur ce créneau.';
                }
            }
            if (!$error) {
                prepareQuery(
                    'INSERT INTO horaires (classe_id, matiere_id, prof_id, jour, heure_debut, heure_fin, salle, statut, annee_scolaire)
                     VALUES (:c,:m,:p,:j,:hd,:hf,:s,:st,:a)',
                    ['c'=>$d['classe_id'],'m'=>$d['matiere_id'],'p'=>$d['prof_id']>0?$d['prof_id']:null,'j'=>$d['jour'],
                     'hd'=>$d['heure_debut'],'hf'=>$d['heure_fin'],'s'=>$d['salle'],'st'=>'brouillon','a'=>$annee]
                );
                log_activity('horaires.create', 'Création d\'un créneau ' . $d['jour'] . ' ' . $d['heure_debut'] . '-'.$d['heure_fin']);
                set_flash('success', 'Créneau ajouté.');
                header('Location: index.php?classe_id=' . $d['classe_id']);
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Nouveau créneau horaire</div>
    <div class="card-body">
        <?php display_flash(); ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Classe</label>
                    <select name="classe_id" class="form-select" required>
                        <option value="0">-- Choisir --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $d['classe_id']==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Matière</label>
                    <select name="matiere_id" class="form-select" required>
                        <option value="0">-- Choisir --</option>
                        <?php foreach ($matieres as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $d['matiere_id']==$m['id']?'selected':'' ?>><?= e($m['nom_matiere']) ?> (<?= e($m['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Professeur</label>
                    <select name="prof_id" class="form-select">
                        <option value="0">-- Aucun --</option>
                        <?php foreach ($profs as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $d['prof_id']==$p['id']?'selected':'' ?>><?= e($p['prenom'] . ' ' . $p['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Jour</label>
                    <select name="jour" class="form-select">
                        <?php foreach (['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'] as $j): ?>
                            <option value="<?= $j ?>" <?= $d['jour']===$j?'selected':'' ?>><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label required">Heure début</label>
                    <input type="time" name="heure_debut" class="form-control" value="<?= e($d['heure_debut']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required">Heure fin</label>
                    <input type="time" name="heure_fin" class="form-control" value="<?= e($d['heure_fin']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Salle</label>
                    <input type="text" name="salle" class="form-control" value="<?= e($d['salle']) ?>" placeholder="Ex: Salle 12">
                </div>
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
