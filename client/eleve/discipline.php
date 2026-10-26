<?php
/**
 * Élève — Discipline (lecture seule)
 * Incidents, sanctions et convocations de l'élève connecté.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Discipline';
$active_menu = 'discipline';

$eleve = prepareQuery(
    'SELECT e.*, ' . classe_label_sql('c') . ' AS nom_classe, c.niveau FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     WHERE e.user_id = :uid',
    ['uid' => $user['id']]
)->fetch();

if (!$eleve) {
    set_flash('error', 'Profil élève introuvable.');
    header('Location: ' . BASE_URL . 'client/eleve/index.php');
    exit;
}

$eleve_id = $eleve['id'];

$gravites = gravites_incident();
$statuts_incident = statuts_incident();
$statuts_sanction = statuts_sanction();

$incidents = prepareQuery(
    "SELECT i.*, u.prenom AS sig_prenom, u.nom AS sig_nom
     FROM incidents_discipline i
     LEFT JOIN utilisateurs u ON u.id = i.signale_par
     WHERE i.eleve_id = :eid
     ORDER BY i.date_incident DESC, i.id DESC",
    ['eid' => $eleve_id]
)->fetchAll();

$sanctions = prepareQuery(
    "SELECT s.*, u.prenom AS pr_prenom, u.nom AS pr_nom
     FROM sanctions s
     LEFT JOIN utilisateurs u ON u.id = s.prononcee_par
     WHERE s.eleve_id = :eid
     ORDER BY s.date_debut DESC, s.id DESC",
    ['eid' => $eleve_id]
)->fetchAll();

$convocations = prepareQuery(
    "SELECT * FROM convocations
     WHERE eleve_id = :eid
     ORDER BY date_convocation DESC, id DESC",
    ['eid' => $eleve_id]
)->fetchAll();

$incidents_ouverts = 0;
foreach ($incidents as $i) {
    if ($i['statut'] === 'ouvert') $incidents_ouverts++;
}
$sanctions_encours = 0;
foreach ($sanctions as $s) {
    if ($s['statut'] === 'en_cours') $sanctions_encours++;
}
$convocations_a_venir = 0;
foreach ($convocations as $cv) {
    if ($cv['date_convocation'] >= date('Y-m-d')) $convocations_a_venir++;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Ma discipline</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
</div>

<!-- INDICATEURS -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card stat-red">
            <div>
                <div class="stat-label">Incidents ouverts</div>
                <div class="stat-number"><?= (int)$incidents_ouverts ?></div>
            </div>
            <i class="fa-solid fa-triangle-exclamation stat-icon"></i>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card stat-orange">
            <div>
                <div class="stat-label">Sanctions en cours</div>
                <div class="stat-number"><?= (int)$sanctions_encours ?></div>
            </div>
            <i class="fa-solid fa-gavel stat-icon"></i>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-label">Convocations à venir</div>
                <div class="stat-number"><?= (int)$convocations_a_venir ?></div>
            </div>
            <i class="fa-solid fa-envelope stat-icon"></i>
        </div>
    </div>
</div>

<!-- INCIDENTS -->
<div class="card mb-4">
    <div class="card-header">
        <span><i class="fa-solid fa-triangle-exclamation me-2"></i>Mes incidents <span class="text-muted fs-6">(<?= count($incidents) ?>)</span></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($incidents)): ?>
            <div class="text-center text-muted py-4">Aucun incident enregistré.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Gravité</th>
                            <th>Lieu</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $i): ?>
                            <tr>
                                <td class="small text-nowrap"><?= date('d/m/Y', strtotime($i['date_incident'])) ?></td>
                                <td class="small">
                                    <?= e($i['type_incident']) ?>
                                    <?php if (!empty($i['description'])): ?><br><small class="text-muted"><?= e(mb_substr($i['description'], 0, 80)) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <?php $gc = ['faible' => 'info', 'moyenne' => 'warning', 'grave' => 'danger'][$i['gravite']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $gc ?>"><?= e($gravites[$i['gravite']] ?? $i['gravite']) ?></span>
                                </td>
                                <td class="small"><?= e($i['lieu'] ?: '-') ?></td>
                                <td>
                                    <?php $sc = $i['statut'] === 'ouvert' ? 'danger' : ($i['statut'] === 'traite' ? 'info' : 'success'); ?>
                                    <span class="badge bg-<?= $sc ?>"><?= e($statuts_incident[$i['statut']] ?? $i['statut']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SANCTIONS -->
<div class="card mb-4">
    <div class="card-header">
        <span><i class="fa-solid fa-gavel me-2"></i>Mes sanctions <span class="text-muted fs-6">(<?= count($sanctions) ?>)</span></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($sanctions)): ?>
            <div class="text-center text-muted py-4">Aucune sanction enregistrée.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Sanction</th>
                            <th>Période</th>
                            <th>Durée</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sanctions as $s): ?>
                            <tr>
                                <td class="small">
                                    <?= e($s['type_sanction']) ?>
                                    <?php if (!empty($s['description'])): ?><br><small class="text-muted"><?= e(mb_substr($s['description'], 0, 80)) ?></small><?php endif; ?>
                                </td>
                                <td class="small text-nowrap">
                                    <?= date('d/m/Y', strtotime($s['date_debut'])) ?><?= $s['date_fin'] ? ' → ' . date('d/m/Y', strtotime($s['date_fin'])) : '' ?>
                                </td>
                                <td class="small"><?= $s['duree_jours'] !== null ? (int)$s['duree_jours'] . ' j' : '-' ?></td>
                                <td>
                                    <?php $sc = $s['statut'] === 'en_cours' ? 'warning' : ($s['statut'] === 'terminee' ? 'success' : 'secondary'); ?>
                                    <span class="badge bg-<?= $sc ?>"><?= e($statuts_sanction[$s['statut']] ?? $s['statut']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- CONVOCATIONS -->
<div class="card">
    <div class="card-header">
        <span><i class="fa-solid fa-envelope me-2"></i>Mes convocations <span class="text-muted fs-6">(<?= count($convocations) ?>)</span></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($convocations)): ?>
            <div class="text-center text-muted py-4">Aucune convocation.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Motif</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($convocations as $cv): ?>
                            <tr>
                                <td class="small text-nowrap"><?= date('d/m/Y', strtotime($cv['date_convocation'])) ?></td>
                                <td class="small"><?= e($cv['heure'] ? substr($cv['heure'], 0, 5) : '-') ?></td>
                                <td class="small"><?= e($cv['motif']) ?></td>
                                <td>
                                    <?php $sc = $cv['statut'] === 'prevue' ? 'warning' : ($cv['statut'] === 'effectuee' ? 'success' : 'secondary'); ?>
                                    <span class="badge bg-<?= $sc ?>"><?= e(ucfirst($cv['statut'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
