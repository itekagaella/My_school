<?php
/**
 * Élève — Finance (lecture seule)
 * Solde, frais attendus et historique des paiements de l'élève connecté.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Finance';
$active_menu = 'finance';

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

$solde = eleve_solde($eleve_id);

// Frais applicables au niveau de l'élève (ou frais globaux)
$frais = prepareQuery(
    "SELECT * FROM frais_scolaires
     WHERE actif = TRUE AND (niveau IS NULL OR niveau = :niveau)
     ORDER BY obligatoire DESC, libelle ASC",
    ['niveau' => $eleve['niveau']]
)->fetchAll();

$paiements = prepareQuery(
    "SELECT pa.*, f.libelle
     FROM paiements pa
     LEFT JOIN frais_scolaires f ON f.id = pa.frais_id
     WHERE pa.eleve_id = :eid
     ORDER BY pa.date_paiement DESC, pa.id DESC",
    ['eid' => $eleve_id]
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-wallet me-2 text-primary"></i>Ma situation financière</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
</div>

<!-- SOLDES -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card stat-green">
            <div>
                <div class="stat-label">Total payé</div>
                <div class="stat-number"><?= format_montant($solde['paye']) ?></div>
            </div>
            <i class="fa-solid fa-money-bill-wave stat-icon"></i>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card <?= $solde['solde'] > 0 ? 'stat-orange' : 'stat-green' ?>">
            <div>
                <div class="stat-label">Solde / Reste à payer</div>
                <div class="stat-number"><?= format_montant($solde['solde']) ?></div>
            </div>
            <i class="fa-solid fa-scale-balanced stat-icon"></i>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card stat-purple">
            <div>
                <div class="stat-label">Montant attendu</div>
                <div class="stat-number"><?= format_montant($solde['attendu']) ?></div>
            </div>
            <i class="fa-solid fa-calculator stat-icon"></i>
        </div>
    </div>
</div>

<!-- FRAIS ATTENDUS -->
<div class="card mb-4">
    <div class="card-header">
        <span><i class="fa-solid fa-file-invoice-dollar me-2"></i>Frais scolaires applicables</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($frais)): ?>
            <div class="text-center text-muted py-4">Aucun frais défini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Frais</th>
                            <th>Période / niveau</th>
                            <th>Obligatoire</th>
                            <th class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($frais as $f): ?>
                            <tr>
                                <td>
                                    <?= e($f['libelle']) ?>
                                    <?php if (!empty($f['description'])): ?><br><small class="text-muted"><?= e($f['description']) ?></small><?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= e($f['niveau'] ?: 'Tous les niveaux') ?></td>
                                <td>
                                    <span class="badge bg-<?= $f['obligatoire'] ? 'primary' : 'secondary' ?>">
                                        <?= $f['obligatoire'] ? 'Oui' : 'Non' ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= format_montant($f['montant']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- PAIEMENTS -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-receipt me-2"></i>Historique des paiements <span class="text-muted fs-6">(<?= count($paiements) ?>)</span></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($paiements)): ?>
            <div class="text-center text-muted py-4">Aucun paiement enregistré.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Frais</th>
                            <th>Période</th>
                            <th>Mode</th>
                            <th class="text-end">Montant</th>
                            <th>N° reçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $p): ?>
                            <tr>
                                <td class="small text-nowrap"><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                                <td><?= e($p['libelle'] ?? '-') ?></td>
                                <td class="small text-muted"><?= e($p['periode'] ?: '-') ?></td>
                                <td class="small text-capitalize"><?= e($p['mode_paiement']) ?></td>
                                <td class="text-end"><?= format_montant($p['montant']) ?></td>
                                <td class="small"><?= e($p['recu_numero']) ?></td>
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
