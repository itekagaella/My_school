<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Emploi du temps';
$active_menu = 'horaires';

$eleve = prepareQuery(
    'SELECT e.*, c.nom_classe FROM eleves e
    LEFT JOIN classes c ON c.id = e.classe_id
    WHERE e.user_id = :uid',
    ['uid'=>$user['id']]
)->fetch();

if (!$eleve || !$eleve['classe_id']) {
    set_flash('error', 'Profil élève ou classe introuvable.');
    header('Location: ' . BASE_URL . 'client/eleve/index.php');
    exit;
}

$eleve_id = $eleve['id'];
$classe_id = $eleve['classe_id'];

$jour_filter = clean_input(get('jour', ''));
$jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

$where = "h.classe_id = :cid AND h.statut = 'publie'";
$params = ['cid' => $classe_id];
if ($jour_filter !== '' && in_array($jour_filter, $jours)) {
    $where .= ' AND h.jour = :jour';
    $params['jour'] = $jour_filter;
}

$horaires = prepareQuery(
    "SELECT h.*, m.nom_matiere, m.code AS matiere_code, p.nom AS prof_nom, p.prenom AS prof_prenom
     FROM horaires h
     JOIN matieres m ON m.id = h.matiere_id
     LEFT JOIN profs p ON p.id = h.prof_id
     WHERE $where
     ORDER BY h.jour, h.heure_debut",
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Emploi du temps</h4>
    <form method="get" action="" class="d-flex gap-2 align-items-center">
        <select name="jour" class="form-select" onchange="this.form.submit()" style="width:180px;">
            <option value="">Tous les jours</option>
            <?php foreach ($jours as $j): ?>
                <option value="<?= $j ?>" <?= $jour_filter===$j?'selected':'' ?>><?= $j ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-school me-2"></i><?= e($eleve['nom_classe'] ?? 'Ma classe') ?></span>
        <span class="badge bg-primary"><?= count($horaires) ?> créneau(x)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Jour</th>
                        <th>Heures</th>
                        <th>Matière</th>
                        <th>Professeur</th>
                        <th>Salle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($horaires)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun créneau publié<?= $jour_filter ? ' pour ce jour' : '' ?>.</td></tr>
                    <?php else: foreach ($horaires as $h): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($h['jour']) ?></td>
                            <td class="small text-nowrap">
                                <i class="fa-regular fa-clock text-muted me-1"></i>
                                <?= e(substr($h['heure_debut'],0,5)) ?> - <?= e(substr($h['heure_fin'],0,5)) ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary me-1"><?= e($h['matiere_code']) ?></span>
                                <?= e($h['nom_matiere']) ?>
                            </td>
                            <td class="small"><?= e(($h['prof_prenom'] ?? '') . ' ' . ($h['prof_nom'] ?? '-')) ?></td>
                            <td class="small"><i class="fa-solid fa-location-dot text-muted me-1"></i><?= e($h['salle'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
