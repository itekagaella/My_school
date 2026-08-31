<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['prof']);
$user = current_user();
$page_title = 'Tableau de bord';
$active_menu = 'dashboard';

$prof = prepareQuery(
    'SELECT * FROM profs WHERE user_id = :uid',
    ['uid'=>$user['id']]
)->fetch();

if (!$prof) {
    set_flash('error', 'Profil professeur introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$prof_id = $prof['id'];

$nb_matieres = prepareQuery(
    'SELECT COUNT(*) AS nb FROM matieres WHERE prof_id=:pid',
    ['pid'=>$prof_id]
)->fetch()['nb'] ?? 0;

$nb_cours_semaine = prepareQuery(
    "SELECT COUNT(*) AS nb FROM horaires WHERE prof_id=:pid AND statut='publie'",
    ['pid'=>$prof_id]
)->fetch()['nb'] ?? 0;

$classe_ids_r = prepareQuery(
    'SELECT DISTINCT classe_id FROM matieres WHERE prof_id=:pid AND classe_id IS NOT NULL',
    ['pid'=>$prof_id]
)->fetchAll();
$nb_eleves = 0;
foreach ($classe_ids_r as $row) {
    $nb_eleves += prepareQuery(
        'SELECT COUNT(*) AS nb FROM eleves WHERE classe_id=:cid',
        ['cid'=>$row['classe_id']]
    )->fetch()['nb'] ?? 0;
}

$dernieres_notes = prepareQuery(
    "SELECT n.*, m.nom_matiere, m.code AS matiere_code
    FROM notes n
    JOIN matieres m ON m.id = n.matiere_id
    WHERE n.prof_id=:pid
    ORDER BY n.created_at DESC LIMIT 8",
    ['pid'=>$prof_id]
)->fetchAll();

$nb_notes = count($dernieres_notes);

$matieres_enseignees = prepareQuery(
    "SELECT m.*, c.nom_classe FROM matieres m
    LEFT JOIN classes c ON c.id = m.classe_id
    WHERE m.prof_id=:pid
    ORDER BY m.nom_matiere",
    ['pid'=>$prof_id]
)->fetchAll();

$jour_actuel = strftime('%A', strtotime('today'));
$jour_map = [
    'Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
    'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'
];
$jour_fr = $jour_map[$jour_actuel] ?? '';

$prochain_cours = null;
if ($jour_fr) {
    $prochain_cours = prepareQuery(
        "SELECT h.*, m.nom_matiere, m.code AS matiere_code, c.nom_classe
        FROM horaires h
        JOIN matieres m ON m.id = h.matiere_id
        LEFT JOIN classes c ON c.id = h.classe_id
        WHERE h.prof_id=:pid AND h.jour=:jour AND h.statut='publie'
        ORDER BY h.heure_debut ASC LIMIT 1",
        ['pid'=>$prof_id, 'jour'=>$jour_fr]
    )->fetch();
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-book text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Matières enseignées</div>
                        <div class="fs-4 fw-bold"><?= $nb_matieres ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-calendar-check text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Cours cette semaine</div>
                        <div class="fs-4 fw-bold"><?= $nb_cours_semaine ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-users text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Élèves total</div>
                        <div class="fs-4 fw-bold"><?= $nb_eleves ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-file-pen text-warning"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Notes saisies</div>
                        <div class="fs-4 fw-bold"><?= $nb_notes ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fa-solid fa-book me-2"></i>Matières enseignées</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($matieres_enseignees)): ?>
                    <div class="text-center text-muted py-4">Aucune matière assignée</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Matière</th>
                                    <th>Classe</th>
                                    <th>Coefficient</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matieres_enseignees as $m): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= e($m['code']) ?></span></td>
                                        <td><?= e($m['nom_matiere']) ?></td>
                                        <td><?= e($m['nom_classe'] ?? '-') ?></td>
                                        <td><?= e($m['coefficient'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fa-solid fa-file-pen me-2"></i>Dernières notes saisies</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($dernieres_notes)): ?>
                    <div class="text-center text-muted py-4">Aucune note saisie</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Matière</th>
                                    <th>Note</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieres_notes as $n): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary me-1"><?= e($n['matiere_code']) ?></span>
                                            <?= e($n['nom_matiere']) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $note_val = (float)$n['note'];
                                            $badge_note = $note_val >= 14 ? 'success' : ($note_val >= 10 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?= $badge_note ?>"><?= number_format($note_val, 2) ?>/20</span>
                                        </td>
                                        <td><span class="text-capitalize"><?= e($n['type_evaluation']) ?></span></td>
                                        <td><small class="text-muted"><?= date('d/m/Y', strtotime($n['date_evaluation'])) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fa-solid fa-clock me-2"></i>Prochain cours</h6>
            </div>
            <div class="card-body">
                <?php if ($prochain_cours): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                            <i class="fa-solid fa-calendar-day text-info fs-4"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold fs-5"><?= e($prochain_cours['nom_matiere']) ?></div>
                            <div class="text-muted"><?= e($prochain_cours['nom_classe'] ?? '') ?></div>
                        </div>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fa-solid fa-calendar text-muted me-2"></i><?= e($jour_fr) ?></li>
                        <li class="mb-2"><i class="fa-solid fa-clock text-muted me-2"></i><?= e(substr($prochain_cours['heure_debut'],0,5).' - '.substr($prochain_cours['heure_fin'],0,5)) ?></li>
                        <li><i class="fa-solid fa-location-dot text-muted me-2"></i><?= e($prochain_cours['salle'] ?? '-') ?></li>
                    </ul>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fa-solid fa-calendar-xmark fs-3 mb-2 d-block"></i>
                        Aucun cours prévu aujourd'hui
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
