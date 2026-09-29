<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Mes présences';
$active_menu = 'presences';

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

$presences = prepareQuery(
    "SELECT p.*, pr.nom AS prof_nom, pr.prenom AS prof_prenom
     FROM presences p
     LEFT JOIN profs pr ON pr.id = p.prof_id
     WHERE p.eleve_id = :eid
     ORDER BY p.date_presence DESC, p.id DESC",
    ['eid'=>$eleve_id]
)->fetchAll();

$compteurs = ['present'=>0,'absent'=>0,'retard'=>0,'excuse'=>0];
foreach (prepareQuery(
    "SELECT statut, COUNT(*) AS nb FROM presences WHERE eleve_id = :eid GROUP BY statut",
    ['eid'=>$eleve_id]
)->fetchAll() as $row) {
    if (isset($compteurs[$row['statut']])) $compteurs[$row['statut']] = (int)$row['nb'];
}

$badges = ['present'=>'success','absent'=>'danger','retard'=>'warning','excuse'=>'info'];
$icons = ['present'=>'fa-user-check','absent'=>'fa-user-xmark','retard'=>'fa-hourglass-half','excuse'=>'fa-notes-medical'];

$cartes = [
    ['present','Présences'],
    ['absent','Absences'],
    ['retard','Retards'],
    ['excuse','Excuses'],
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="row g-3 mb-4">
    <?php $stat_variants = ['present'=>'stat-green','absent'=>'stat-red','retard'=>'stat-orange','excuse'=>'stat-teal']; ?>
    <?php foreach ($cartes as [$cle, $label]): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card <?= $stat_variants[$cle] ?? 'stat-slate' ?>">
                <div>
                    <div class="stat-label"><?= $label ?></div>
                    <div class="stat-number"><?= $compteurs[$cle] ?></div>
                </div>
                <i class="fa-solid <?= $icons[$cle] ?> stat-icon"></i>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="fa-solid fa-clipboard-check me-2"></i>Historique des présences</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Professeur</th>
                        <th>Statut</th>
                        <th>Motif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($presences)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucune présence enregistrée.</td></tr>
                    <?php else: foreach ($presences as $p): ?>
                        <tr>
                            <td class="small text-nowrap"><?= date('d/m/Y', strtotime($p['date_presence'])) ?></td>
                            <td class="small"><?= e(($p['prof_prenom'] ?? '') . ' ' . ($p['prof_nom'] ?? '-')) ?></td>
                            <td>
                                <span class="badge bg-<?= $badges[$p['statut']] ?? 'secondary' ?> text-capitalize">
                                    <i class="fa-solid <?= $icons[$p['statut']] ?? 'fa-circle' ?> me-1"></i><?= e($p['statut']) ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= e($p['motif'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
