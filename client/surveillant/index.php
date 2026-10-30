<?php
/**
 * Espace Surveillant — Tableau de bord
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['surveillant']);
$user = current_user();
$page_title = 'Tableau de bord';
$active_menu = 'dashboard';

$pers = prepareQuery('SELECT * FROM personnel WHERE user_id = :u', ['u' => $user['id']])->fetch();
if (!$pers) {
    logout('Profil personnel introuvable. Contactez l\'administrateur.');
}

$date = date('Y-m-d');
$resume = prepareQuery(
    "SELECT statut, COUNT(*) AS nb FROM presences WHERE date_presence = :d GROUP BY statut",
    ['d' => $date]
)->fetchAll();
$parStatut = [];
foreach ($resume as $r) { $parStatut[$r['statut']] = (int)$r['nb']; }

$nbAbsents = $parStatut['absent'] ?? 0;
$nbRetards = $parStatut['retard'] ?? 0;
$nbPresents = $parStatut['present'] ?? 0;

$nbIncidentsOuverts = prepareQuery("SELECT COUNT(*) AS nb FROM incidents_discipline WHERE statut = 'ouvert'")->fetch()['nb'] ?? 0;
$nbEleves = prepareQuery("SELECT COUNT(*) AS nb FROM eleves WHERE statut = 'actif'")->fetch()['nb'] ?? 0;

$derniersIncidents = prepareQuery(
    "SELECT i.*, e.nom, e.prenom, e.matricule FROM incidents_discipline i
     JOIN eleves e ON e.id = i.eleve_id
     ORDER BY i.date_incident DESC, i.id DESC LIMIT 6"
)->fetchAll();

$absentsAujourdhui = prepareQuery(
    "SELECT e.nom, e.prenom, e.matricule, " . classe_label_sql('c') . " AS nom_classe, p.statut, p.motif
     FROM presences p JOIN eleves e ON e.id = p.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     WHERE p.date_presence = :d AND p.statut IN ('absent','retard')
     ORDER BY e.nom, e.prenom LIMIT 10",
    ['d' => $date]
)->fetchAll();

$gravites = gravites_incident();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-eye me-2 text-primary"></i>Poste de surveillance</h4>
    <span class="text-muted small"><i class="fa-regular fa-calendar me-1"></i><?= date('d/m/Y') ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-green">
            <div><div class="stat-label">Présents aujourd'hui</div><div class="stat-number"><?= $nbPresents ?></div></div>
            <i class="fa-solid fa-user-check stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-red">
            <div><div class="stat-label">Absents</div><div class="stat-number"><?= $nbAbsents ?></div></div>
            <i class="fa-solid fa-user-xmark stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-orange">
            <div><div class="stat-label">Retards</div><div class="stat-number"><?= $nbRetards ?></div></div>
            <i class="fa-solid fa-clock stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-purple">
            <div><div class="stat-label">Incidents ouverts</div><div class="stat-number"><?= (int)$nbIncidentsOuverts ?></div></div>
            <i class="fa-solid fa-triangle-exclamation stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-user-xmark me-2"></i>Absences & retards du jour</span>
                <a href="presences.php" class="btn btn-sm btn-outline-primary">Détail</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($absentsAujourdhui)): ?>
                    <div class="text-center text-muted py-4">Aucune absence enregistrée aujourd'hui.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($absentsAujourdhui as $a): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= e($a['prenom'] . ' ' . $a['nom']) ?><br><small class="text-muted"><?= e($a['matricule'] . ($a['nom_classe'] ? ' — ' . $a['nom_classe'] : '')) ?></small></span>
                                <span class="badge bg-<?= $a['statut']==='absent'?'danger':'warning' ?>"><?= $a['statut']==='absent'?'Absent':'Retard' ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-triangle-exclamation me-2"></i>Derniers incidents</span>
                <a href="incidents.php" class="btn btn-sm btn-outline-primary">Tout voir</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($derniersIncidents)): ?>
                    <div class="text-center text-muted py-4">Aucun incident signalé.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($derniersIncidents as $i): $gc = ['faible'=>'info','moyenne'=>'warning','grave'=>'danger'][$i['gravite']] ?? 'secondary'; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= e($i['prenom'] . ' ' . $i['nom']) ?><br><small class="text-muted"><?= e($i['type_incident']) ?> — <?= date('d/m/Y', strtotime($i['date_incident'])) ?></small></span>
                                <span class="badge bg-<?= $gc ?>"><?= e($gravites[$i['gravite']] ?? $i['gravite']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
