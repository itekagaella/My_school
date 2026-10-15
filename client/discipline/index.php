<?php
/**
 * Discipline — Tableau de bord (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['discipline']);
require_permission('discipline.view');

$page_title = 'Discipline';
$active_menu = 'dashboard';

$nbIncidents = prepareQuery('SELECT COUNT(*) AS nb FROM incidents_discipline')->fetch()['nb'] ?? 0;
$nbOuverts = prepareQuery("SELECT COUNT(*) AS nb FROM incidents_discipline WHERE statut = 'ouvert'")->fetch()['nb'] ?? 0;
$nbMois = prepareQuery(
    "SELECT COUNT(*) AS nb FROM incidents_discipline
     WHERE date_trunc('month', date_incident) = date_trunc('month', CURRENT_DATE)"
)->fetch()['nb'] ?? 0;
$nbSanctions = prepareQuery("SELECT COUNT(*) AS nb FROM sanctions WHERE statut = 'en_cours'")->fetch()['nb'] ?? 0;
$nbConvocations = prepareQuery("SELECT COUNT(*) AS nb FROM convocations WHERE statut = 'prevue'")->fetch()['nb'] ?? 0;

$parGravite = prepareQuery(
    'SELECT gravite, COUNT(*) AS nb FROM incidents_discipline GROUP BY gravite'
)->fetchAll();

$derniers = prepareQuery(
    "SELECT i.*, e.nom, e.prenom, e.matricule, c.nom_classe
     FROM incidents_discipline i
     JOIN eleves e ON e.id = i.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     ORDER BY i.date_incident DESC, i.id DESC LIMIT 8"
)->fetchAll();

$gravites = gravites_incident();
$statuts = statuts_incident();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-gavel me-2 text-primary"></i>Discipline</h4>
    <div class="d-flex gap-2">
        <a href="incidents.php" class="btn btn-outline-primary"><i class="fa-solid fa-triangle-exclamation me-1"></i>Incidents</a>
        <a href="sanctions.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Nouvelle sanction</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-red">
            <div><div class="stat-label">Incidents (total)</div><div class="stat-number"><?= (int)$nbIncidents ?></div></div>
            <i class="fa-solid fa-triangle-exclamation stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-orange">
            <div><div class="stat-label">Incidents ouverts</div><div class="stat-number"><?= (int)$nbOuverts ?></div></div>
            <i class="fa-solid fa-folder-open stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-purple">
            <div><div class="stat-label">Sanctions en cours</div><div class="stat-number"><?= (int)$nbSanctions ?></div></div>
            <i class="fa-solid fa-gavel stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-blue">
            <div><div class="stat-label">Convocations prévues</div><div class="stat-number"><?= (int)$nbConvocations ?></div></div>
            <i class="fa-solid fa-envelope stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-clock-rotate-left me-2"></i>Derniers incidents</span>
                <a href="incidents.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Élève</th><th>Type</th><th>Gravité</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php if (empty($derniers)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Aucun incident enregistré.</td></tr>
                        <?php else: foreach ($derniers as $i): ?>
                            <tr>
                                <td class="small"><?= date('d/m/Y', strtotime($i['date_incident'])) ?></td>
                                <td><?= e($i['prenom'] . ' ' . $i['nom']) ?><br><small class="text-muted"><?= e($i['nom_classe'] ?? '') ?></small></td>
                                <td class="small"><?= e($i['type_incident']) ?></td>
                                <td>
                                    <?php $gc = ['faible'=>'info','moyenne'=>'warning','grave'=>'danger'][$i['gravite']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $gc ?>"><?= e($gravites[$i['gravite']] ?? $i['gravite']) ?></span>
                                </td>
                                <td><span class="badge bg-<?= $i['statut']==='ouvert'?'danger':($i['statut']==='traite'?'info':'success') ?>"><?= e($statuts[$i['statut']] ?? $i['statut']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-pie me-2"></i>Répartition par gravité</div>
            <div class="card-body">
                <?php if (empty($parGravite)): ?>
                    <div class="text-center text-muted py-4">Aucune donnée.</div>
                <?php else: foreach ($parGravite as $g): $gc = ['faible'=>'info','moyenne'=>'warning','grave'=>'danger'][$g['gravite']] ?? 'secondary'; ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-<?= $gc ?>"><?= e($gravites[$g['gravite']] ?? $g['gravite']) ?></span>
                        <span class="fw-semibold"><?= (int)$g['nb'] ?></span>
                    </div>
                <?php endforeach; ?>
                    <hr>
                    <div class="small text-muted">Ce mois : <strong><?= (int)$nbMois ?></strong> incident(s)</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
