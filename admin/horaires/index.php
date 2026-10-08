<?php
/**
 * Gestion des horaires (Admin) - vue emploi du temps
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('horaires.view');

$page_title = 'Gestion des horaires';
$active_menu = 'horaires';

$classe_id = (int)get('classe_id', 0);

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
if ($classe_id === 0 && !empty($classes)) $classe_id = $classes[0]['id'];

// Créneaux de la classe sélectionnée
$horaires = [];
if ($classe_id > 0) {
    $horaires = prepareQuery(
        "SELECT h.*, m.nom_matiere, m.code, p.nom AS prof_nom, p.prenom AS prof_prenom
         FROM horaires h
         JOIN matieres m ON m.id = h.matiere_id
         LEFT JOIN profs p ON p.id = h.prof_id
         WHERE h.classe_id = :cl
         ORDER BY h.jour, h.heure_debut",
        ['cl' => $classe_id]
    )->fetchAll();
}

$jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$heures = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00'];

// Structure emploi du temps [jour][heure]
$emploidu = [];
foreach ($jours as $j) foreach ($heures as $h) $emploidu[$j][$h] = [];
foreach ($horaires as $h) {
    $empStart = substr($h['heure_debut'], 0, 5);
    // Relier à la première heure du créneau
    $emploidu[$h['jour']][$empStart][] = $h;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form method="get" action="" class="d-flex gap-2 align-items-center">
        <label class="fw-semibold mb-0">Classe :</label>
        <select name="classe_id" class="form-select" onchange="this.form.submit()" style="width:200px;">
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classe_id===$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a href="create.php?classe_id=<?= $classe_id ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Ajouter un créneau</a>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-calendar-days me-2"></i>Emploi du temps</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-bordered horaire-table m-0">
            <thead class="table-light">
                <tr>
                    <th style="width:80px;text-align:center;">Heure</th>
                    <?php foreach ($jours as $j): ?><th style="text-align:center;"><?= $j ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($heures as $h): ?>
                <tr>
                    <td style="text-align:center;font-weight:600;background:#f8fafc;"><?= $h ?></td>
                    <?php foreach ($jours as $j): ?>
                        <td style="height:70px;vertical-align:top;padding:2px;">
                            <?php if (!empty($emploidu[$j][$h])): foreach ($emploidu[$j][$h] as $cr): ?>
                                <div class="horaire-cell p-1" style="border-left:3px solid <?= $cr['statut']==='publie' ? '#16a34a' : ($cr['statut']==='annule' ? '#dc2626' : '#d97706') ?>;">
                                    <span class="matiere"><?= e($cr['nom_matiere']) ?></span>
                                    <span class="prof"><?= e($cr['prof_prenom'] ?? '') ?> <?= e($cr['prof_nom'] ?? '') ?></span>
                                    <span class="salle"><i class="fa-solid fa-location-dot"></i> <?= e($cr['salle'] ?? '') ?></span>
                                    <span class="salle"><?= $cr['heure_debut'] ?>-<?= $cr['heure_fin'] ?></span>
                                    <a href="edit.php?id=<?= $cr['id'] ?>" class="small text-primary"><i class="fa-solid fa-pen"></i></a>
                                </div>
                            <?php endforeach; endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-2"></i>Liste des créneaux</span>
        <a href="index.php?classe_id=<?= $classe_id ?>&view=list" class="btn btn-sm btn-outline-primary">Vue liste</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Jour</th><th>Heures</th><th>Matière</th><th>Prof</th><th>Salle</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($horaires)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun créneau pour cette classe.</td></tr>
                    <?php else: foreach ($horaires as $h): ?>
                        <tr>
                            <td><?= e($h['jour']) ?></td>
                            <td class="small"><?= $h['heure_debut'] ?> - <?= $h['heure_fin'] ?></td>
                            <td><strong><?= e($h['nom_matiere']) ?></strong> <span class="badge bg-light text-dark"><?= e($h['code']) ?></span></td>
                            <td class="small"><?= e($h['prof_prenom'] ?? '') ?> <?= e($h['prof_nom'] ?? '') ?></td>
                            <td class="small"><?= e($h['salle'] ?? '') ?></td>
                            <td>
                                <?php
                                $badge = ['brouillon'=>'bg-warning text-dark','publie'=>'bg-success','annule'=>'bg-secondary'];
                                echo '<span class="badge ' . $badge[$h['statut']] . '">' . e($h['statut']) . '</span>';
                                ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($h['statut'] !== 'publie'): ?>
                                    <form method="post" action="publish.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="horaire_id" value="<?= $h['id'] ?>">
                                        <input type="hidden" name="classe_id" value="<?= $classe_id ?>">
                                        <button class="btn btn-sm btn-outline-success" title="Publier"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                                <a href="edit.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                                <a href="delete.php?id=<?= $h['id'] ?>&classe_id=<?= $classe_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Supprimer ce créneau ?')"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
