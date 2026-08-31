<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Mes notes';
$active_menu = 'notes';

$eleve = prepareQuery(
    'SELECT * FROM eleves WHERE user_id = :uid',
    ['uid'=>$user['id']]
)->fetch();

if (!$eleve) {
    set_flash('error', 'Profil élève introuvable.');
    header('Location: ' . BASE_URL . 'client/eleve/index.php');
    exit;
}

$eleve_id = $eleve['id'];

$moyenne = 0;
$r = prepareQuery('SELECT sp_calculer_moyenne(:eid) AS moy', ['eid'=>$eleve_id])->fetch();
$moyenne = $r['moy'] ?? 0;

$notes = prepareQuery(
    "SELECT n.*, m.nom_matiere, m.code AS matiere_code
     FROM notes n
     JOIN matieres m ON m.id = n.matiere_id
     WHERE n.eleve_id = :eid
     ORDER BY n.date_evaluation DESC, n.id DESC",
    ['eid'=>$eleve_id]
)->fetchAll();

$moyennes_par_matiere = prepareQuery(
    "SELECT m.nom_matiere, m.code AS matiere_code, ROUND(AVG(n.note)::numeric, 2) AS moyenne, COUNT(n.id) AS nb_notes
     FROM notes n
     JOIN matieres m ON m.id = n.matiere_id
     WHERE n.eleve_id = :eid
     GROUP BY m.id, m.nom_matiere, m.code
     ORDER BY m.nom_matiere",
    ['eid'=>$eleve_id]
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-chart-line text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Moyenne générale</div>
                        <div class="fs-4 fw-bold"><?= number_format((float)$moyenne, 2) ?>/20</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-file-pen text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Nombre de notes</div>
                        <div class="fs-4 fw-bold"><?= count($notes) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-book text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Matières évaluées</div>
                        <div class="fs-4 fw-bold"><?= count($moyennes_par_matiere) ?></div>
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
                <h6 class="mb-0"><i class="fa-solid fa-file-pen me-2"></i>Toutes mes notes</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Matière</th>
                                <th>Type</th>
                                <th>Note</th>
                                <th>Appréciation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notes)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Aucune note enregistrée.</td></tr>
                            <?php else: foreach ($notes as $n):
                                $note_val = (float)$n['note'];
                                $badge_note = $note_val >= 14 ? 'success' : ($note_val >= 10 ? 'warning' : 'danger');
                            ?>
                                <tr>
                                    <td class="small text-nowrap"><?= date('d/m/Y', strtotime($n['date_evaluation'])) ?></td>
                                    <td>
                                        <span class="badge bg-secondary me-1"><?= e($n['matiere_code']) ?></span>
                                        <?= e($n['nom_matiere']) ?>
                                    </td>
                                    <td><span class="text-capitalize small"><?= e($n['type_evaluation']) ?></span></td>
                                    <td><span class="badge bg-<?= $badge_note ?>"><?= number_format($note_val, 2) ?>/20</span></td>
                                    <td class="small text-muted"><?= e($n['appreciation'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fa-solid fa-list-check me-2"></i>Récapitulatif par matière</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($moyennes_par_matiere)): ?>
                    <div class="text-center text-muted py-4">Aucune donnée</div>
                <?php else: foreach ($moyennes_par_matiere as $m):
                    $moy = (float)$m['moyenne'];
                    $badge = $moy >= 14 ? 'success' : ($moy >= 10 ? 'warning' : 'danger');
                ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e($m['nom_matiere']) ?></strong>
                                <br><small class="text-muted"><?= $m['nb_notes'] ?> note(s)</small>
                            </div>
                            <span class="badge bg-<?= $badge ?>"><?= number_format($moy, 2) ?>/20</span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
