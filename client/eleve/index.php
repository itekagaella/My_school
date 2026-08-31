<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Tableau de bord';
$active_menu = 'dashboard';

$eleve = prepareQuery(
    'SELECT e.*, c.nom_classe, c.niveau FROM eleves e
    LEFT JOIN classes c ON c.id = e.classe_id
    WHERE e.user_id = :uid',
    ['uid'=>$user['id']]
)->fetch();

if (!$eleve) {
    set_flash('error', 'Profil élève introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$eleve_id = $eleve['id'];
$classe_id = $eleve['classe_id'];

$nb_matieres = 0;
if ($classe_id) {
    $r = prepareQuery(
        'SELECT COUNT(*) AS nb FROM matieres WHERE classe_id=:cid',
        ['cid'=>$classe_id]
    )->fetch();
    $nb_matieres = $r['nb'] ?? 0;
}

$moyenne = 0;
$r = prepareQuery('SELECT sp_calculer_moyenne(:eid) AS moy', ['eid'=>$eleve_id])->fetch();
$moyenne = $r['moy'] ?? 0;

$nb_absences = prepareQuery(
    "SELECT COUNT(*) AS nb FROM presences WHERE eleve_id=:eid AND statut IN ('absent','retard')",
    ['eid'=>$eleve_id]
)->fetch()['nb'] ?? 0;

$jour_actuel = strftime('%A', strtotime('today'));
$jour_map = [
    'Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
    'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'
];
$jour_fr = $jour_map[$jour_actuel] ?? '';

$prochain_cours = null;
if ($classe_id && $jour_fr) {
    $prochain_cours = prepareQuery(
        "SELECT h.*, m.nom_matiere, m.code AS matiere_code, p.nom AS prof_nom, p.prenom AS prof_prenom
        FROM horaires h
        JOIN matieres m ON m.id = h.matiere_id
        LEFT JOIN profs p ON p.id = h.prof_id
        WHERE h.classe_id=:cid AND h.jour=:jour AND h.statut='publie'
        ORDER BY h.heure_debut ASC LIMIT 1",
        ['cid'=>$classe_id, 'jour'=>$jour_fr]
    )->fetch();
}

$dernieres_notes = prepareQuery(
    "SELECT n.*, m.nom_matiere, m.code AS matiere_code
    FROM notes n
    JOIN matieres m ON m.id = n.matiere_id
    WHERE n.eleve_id=:eid
    ORDER BY n.created_at DESC LIMIT 8",
    ['eid'=>$eleve_id]
)->fetchAll();

$dernieres_comms = prepareQuery(
    "SELECT * FROM communications
    WHERE statut='publie' AND (destinataires='tous' OR destinataires LIKE '%role:eleve%')
    ORDER BY date_publication DESC LIMIT 5"
)->fetchAll();

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
                        <div class="text-muted small">Matières</div>
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
                        <i class="fa-solid fa-chart-line text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Moyenne générale</div>
                        <div class="fs-4 fw-bold"><?= number_format((float)$moyenne, 2) ?>/20</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-user-xmark text-danger"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Absences / Retards</div>
                        <div class="fs-4 fw-bold"><?= $nb_absences ?></div>
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
                        <i class="fa-solid fa-clock text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Prochain cours</div>
                        <?php if ($prochain_cours): ?>
                            <div class="fw-bold fs-6"><?= e($prochain_cours['nom_matiere']) ?></div>
                            <small class="text-muted"><?= e(substr($prochain_cours['heure_debut'],0,5).' - '.substr($prochain_cours['heure_fin'],0,5)) ?></small>
                        <?php else: ?>
                            <div class="text-muted small">-</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fa-solid fa-file-pen me-2"></i>Dernières notes</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($dernieres_notes)): ?>
                    <div class="text-center text-muted py-4">Aucune note enregistrée</div>
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
                <h6 class="mb-0"><i class="fa-solid fa-bullhorn me-2"></i>Communications</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($dernieres_comms)): ?>
                    <div class="text-center text-muted py-4">Aucune communication</div>
                <?php else: foreach ($dernieres_comms as $comm):
                    $type_icon = ['annonce'=>'fa-megaphone','alerte'=>'fa-triangle-exclamation','info'=>'fa-circle-info'];
                    $type_color = ['annonce'=>'primary','alerte'=>'danger','info'=>'info'];
                ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-start">
                            <i class="fa-solid <?= $type_icon[$comm['type']] ?? 'fa-info-circle' ?> text-<?= $type_color[$comm['type']] ?? 'info' ?> me-2 mt-1"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($comm['titre']) ?></div>
                                <small class="text-muted"><?= mb_substr($comm['contenu'],0,80) ?><?= mb_strlen($comm['contenu'])>80?'...':'' ?></small>
                                <div><small class="text-muted"><?= date('d/m/Y H:i', strtotime($comm['date_publication'])) ?></small></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
