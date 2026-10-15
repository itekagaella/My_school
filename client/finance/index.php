<?php
/**
 * Gestion financière — Tableau de bord (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['finance']);
require_permission('finance.view');

$page_title = 'Tableau de bord';
$active_menu = 'dashboard';

$annee = get_param('annee_scolaire', date('Y') . '-' . (date('Y') + 1));

// Totaux globaux
$totalFrais = prepareQuery(
    'SELECT COALESCE(SUM(montant),0) AS total FROM frais_scolaires WHERE actif = TRUE'
)->fetch()['total'] ?? 0;

$totalEncaisse = prepareQuery(
    'SELECT COALESCE(SUM(montant),0) AS total FROM paiements'
)->fetch()['total'] ?? 0;

$totalMois = prepareQuery(
    "SELECT COALESCE(SUM(montant),0) AS total FROM paiements
     WHERE date_trunc('month', date_paiement) = date_trunc('month', CURRENT_DATE)"
)->fetch()['total'] ?? 0;

$nbEleves = prepareQuery("SELECT COUNT(*) AS nb FROM eleves WHERE statut = 'actif'")->fetch()['nb'] ?? 0;
$nbPaiements = prepareQuery('SELECT COUNT(*) AS nb FROM paiements')->fetch()['nb'] ?? 0;

// Derniers paiements
$derniers = prepareQuery(
    "SELECT pa.*, e.nom, e.prenom, e.matricule, f.libelle AS frais_libelle
     FROM paiements pa
     JOIN eleves e ON e.id = pa.eleve_id
     LEFT JOIN frais_scolaires f ON f.id = pa.frais_id
     ORDER BY pa.date_paiement DESC, pa.id DESC LIMIT 8"
)->fetchAll();

// Recouvrement par mode de paiement
$parMode = prepareQuery(
    'SELECT mode_paiement, COUNT(*) AS nb, COALESCE(SUM(montant),0) AS total
     FROM paiements GROUP BY mode_paiement ORDER BY total DESC'
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-coins me-2 text-primary"></i>Gestion financière</h4>
    <div class="d-flex gap-2">
        <a href="frais.php" class="btn btn-outline-primary"><i class="fa-solid fa-file-invoice-dollar me-1"></i>Frais scolaires</a>
        <a href="paiements.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Nouveau paiement</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-green">
            <div>
                <div class="stat-label">Total encaissé</div>
                <div class="stat-number" style="font-size:20px;"><?= e(format_montant($totalEncaisse)) ?></div>
            </div>
            <i class="fa-solid fa-sack-dollar stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-label">Encaissé ce mois</div>
                <div class="stat-number" style="font-size:20px;"><?= e(format_montant($totalMois)) ?></div>
            </div>
            <i class="fa-solid fa-calendar-day stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-purple">
            <div>
                <div class="stat-label">Frais définis</div>
                <div class="stat-number" style="font-size:20px;"><?= e(format_montant($totalFrais)) ?></div>
            </div>
            <i class="fa-solid fa-file-invoice-dollar stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-orange">
            <div>
                <div class="stat-label">Paiements (<?= (int)$nbPaiements ?>)</div>
                <div class="stat-number"><?= (int)$nbEleves ?> <span class="fs-6 fw-normal">élèves</span></div>
            </div>
            <i class="fa-solid fa-receipt stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-clock-rotate-left me-2"></i>Derniers paiements</span>
                <a href="paiements.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Reçu</th><th>Élève</th><th>Frais</th><th>Montant</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($derniers)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Aucun paiement enregistré.</td></tr>
                        <?php else: foreach ($derniers as $p): ?>
                            <tr>
                                <td class="small"><code><?= e($p['recu_numero']) ?></code></td>
                                <td><?= e($p['prenom'] . ' ' . $p['nom']) ?><br><small class="text-muted"><?= e($p['matricule']) ?></small></td>
                                <td class="small"><?= e($p['frais_libelle'] ?? '-') ?></td>
                                <td class="fw-semibold text-success"><?= e(format_montant($p['montant'])) ?></td>
                                <td class="small"><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                                <td class="text-end"><a href="recu.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Reçu" target="_blank"><i class="fa-solid fa-print"></i></a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-pie me-2"></i>Par mode de paiement</div>
            <div class="card-body">
                <?php if (empty($parMode)): ?>
                    <div class="text-center text-muted py-4">Aucune donnée.</div>
                <?php else: foreach ($parMode as $m): $modes = modes_paiement(); ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fa-solid fa-circle-dot me-2 text-primary"></i><?= e($modes[$m['mode_paiement']] ?? $m['mode_paiement']) ?></span>
                        <span class="fw-semibold"><?= e(format_montant($m['total'])) ?> <small class="text-muted">(<?= (int)$m['nb'] ?>)</small></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
