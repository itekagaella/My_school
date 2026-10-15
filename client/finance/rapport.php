<?php
/**
 * Espace Finance — Rapport financier
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['finance']);
$user = current_user();
require_permission('finance.reports');
$page_title = 'Rapport financier';
$active_menu = 'rapport';

$pers = prepareQuery('SELECT * FROM personnel WHERE user_id = :u', ['u' => $user['id']])->fetch();
if (!$pers) {
    set_flash('error', 'Profil personnel introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$debut = clean_input(get('debut', date('Y-m-01')));
$fin = clean_input(get('fin', date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $debut)) $debut = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) $fin = date('Y-m-d');

$where = 'WHERE pa.date_paiement BETWEEN :deb AND :fin';
$params = ['deb' => $debut, 'fin' => $fin];

$total = prepareQuery("SELECT COALESCE(SUM(montant),0) AS total, COUNT(*) AS nb FROM paiements pa $where", $params)->fetch();
$totalEncaisse = (float)($total['total'] ?? 0);
$nbPaiements = (int)($total['nb'] ?? 0);

$parFrais = prepareQuery(
    "SELECT COALESCE(f.libelle, 'Non affecté') AS libelle, COUNT(*) AS nb, COALESCE(SUM(pa.montant),0) AS total
     FROM paiements pa LEFT JOIN frais_scolaires f ON f.id = pa.frais_id
     $where GROUP BY f.libelle ORDER BY total DESC",
    $params
)->fetchAll();

$parClasse = prepareQuery(
    "SELECT COALESCE(c.nom_classe, 'Sans classe') AS nom_classe, COUNT(*) AS nb, COALESCE(SUM(pa.montant),0) AS total
     FROM paiements pa
     JOIN eleves e ON e.id = pa.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     $where GROUP BY c.nom_classe ORDER BY total DESC",
    $params
)->fetchAll();

$parMode = prepareQuery(
    "SELECT mode_paiement, COUNT(*) AS nb, COALESCE(SUM(montant),0) AS total
     FROM paiements pa $where GROUP BY mode_paiement ORDER BY total DESC",
    $params
)->fetchAll();

$modes = modes_paiement();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Rapport financier</h4>
    <a href="export.php?type=paiements&debut=<?= urlencode($debut) ?>&fin=<?= urlencode($fin) ?>" class="btn btn-outline-success">
        <i class="fa-solid fa-file-csv me-1"></i>Exporter CSV
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Du</label>
                <input type="date" name="debut" class="form-control" value="<?= e($debut) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Au</label>
                <input type="date" name="fin" class="form-control" value="<?= e($fin) ?>">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Générer</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card stat-green">
            <div><div class="stat-label">Total encaissé</div><div class="stat-number" style="font-size:22px;"><?= e(format_montant($totalEncaisse)) ?></div></div>
            <i class="fa-solid fa-sack-dollar stat-icon"></i>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card stat-blue">
            <div><div class="stat-label">Nombre de paiements</div><div class="stat-number"><?= $nbPaiements ?></div></div>
            <i class="fa-solid fa-receipt stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Par type de frais</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Frais</th><th class="text-end">Nb</th><th class="text-end">Montant</th></tr></thead>
                    <tbody>
                        <?php if (empty($parFrais)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée.</td></tr>
                        <?php else: foreach ($parFrais as $r): ?>
                            <tr><td><?= e($r['libelle']) ?></td><td class="text-end"><?= (int)$r['nb'] ?></td><td class="text-end fw-semibold"><?= e(format_montant($r['total'])) ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-school me-2"></i>Par classe</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Classe</th><th class="text-end">Nb</th><th class="text-end">Montant</th></tr></thead>
                    <tbody>
                        <?php if (empty($parClasse)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée.</td></tr>
                        <?php else: foreach ($parClasse as $r): ?>
                            <tr><td><?= e($r['nom_classe']) ?></td><td class="text-end"><?= (int)$r['nb'] ?></td><td class="text-end fw-semibold"><?= e(format_montant($r['total'])) ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-wallet me-2"></i>Par mode de paiement</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Mode</th><th class="text-end">Nb</th><th class="text-end">Montant</th></tr></thead>
                    <tbody>
                        <?php if (empty($parMode)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée.</td></tr>
                        <?php else: foreach ($parMode as $r): ?>
                            <tr><td><?= e($modes[$r['mode_paiement']] ?? $r['mode_paiement']) ?></td><td class="text-end"><?= (int)$r['nb'] ?></td><td class="text-end fw-semibold"><?= e(format_montant($r['total'])) ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
