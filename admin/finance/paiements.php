<?php
/**
 * Gestion financière — Paiements & reçus (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('finance.view');

$page_title = 'Paiements';
$active_menu = 'finance';

$canManage = has_permission('finance.manage');
$modes = modes_paiement();

// ---------- Enregistrement d'un paiement ----------
$error = '';
$d = ['eleve_id' => 0, 'frais_id' => 0, 'montant' => '', 'periode' => '', 'date_paiement' => date('Y-m-d'),
      'mode_paiement' => 'especes', 'reference' => '', 'observation' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            prepareQuery('DELETE FROM paiements WHERE id = :id', ['id' => $id]);
            log_activity('finance.paiement_delete', 'Suppression du paiement #' . $id);
            set_flash('success', 'Paiement supprimé.');
            header('Location: paiements.php');
            exit;
        }

        // Création
        $d['eleve_id'] = (int)($_POST['eleve_id'] ?? 0);
        $d['frais_id'] = (int)($_POST['frais_id'] ?? 0);
        $d['montant'] = (string)($_POST['montant'] ?? '');
        $d['periode'] = clean_input($_POST['periode'] ?? '');
        $d['date_paiement'] = clean_input($_POST['date_paiement'] ?? date('Y-m-d'));
        $d['mode_paiement'] = $_POST['mode_paiement'] ?? 'especes';
        $d['reference'] = clean_input($_POST['reference'] ?? '');
        $d['observation'] = clean_input($_POST['observation'] ?? '');

        $montant = (float)str_replace(',', '.', $d['montant']);
        if ($d['eleve_id'] <= 0) {
            $error = 'Sélectionnez un élève.';
        } elseif ($montant <= 0) {
            $error = 'Le montant doit être supérieur à zéro.';
        } elseif (!array_key_exists($d['mode_paiement'], $modes)) {
            $error = 'Mode de paiement invalide.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date_paiement'])) {
            $error = 'Date de paiement invalide.';
        } else {
            try {
                $recu = generer_numero_recu();
                $paiement = prepareQuery(
                    'INSERT INTO paiements (eleve_id, frais_id, montant, periode, date_paiement, mode_paiement,
                                            reference, recu_numero, observation, enregistre_par)
                     VALUES (:e, :f, :m, :p, :dp, :mode, :ref, :recu, :obs, :uid)
                     RETURNING id',
                    ['e'=>$d['eleve_id'],'f'=>($d['frais_id'] ?: null),'m'=>$montant,'p'=>($d['periode'] ?: null),
                     'dp'=>$d['date_paiement'],'mode'=>$d['mode_paiement'],'ref'=>($d['reference'] ?: null),
                     'recu'=>$recu,'obs'=>($d['observation'] ?: null),'uid'=>current_user()['id']]
                )->fetch();

                if ($paiement) {
                    notifier_paiement(
                        (int)$paiement['id'],
                        (int)$d['eleve_id'],
                        $montant,
                        $recu,
                        $d['date_paiement'],
                        $d['mode_paiement'],
                        $d['frais_id'] ? (int)$d['frais_id'] : null
                    );
                }
                log_activity('finance.paiement_create', 'Paiement ' . $recu . ' (' . format_montant($montant) . ')');
                set_flash('success', 'Paiement enregistré. Reçu : ' . $recu);
                header('Location: paiements.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Erreur lors de l\'enregistrement : ' . $ex->getMessage();
            }
        }
    }
}

// ---------- Filtres ----------
$search = clean_input(get('search', ''));
$fraisId = (int)get('frais_id', 0);
$debut = clean_input(get('debut', ''));
$fin = clean_input(get('fin', ''));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s OR pa.recu_numero ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}
if ($fraisId > 0) { $where[] = 'pa.frais_id = :fid'; $params['fid'] = $fraisId; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $debut)) { $where[] = 'pa.date_paiement >= :deb'; $params['deb'] = $debut; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) { $where[] = 'pa.date_paiement <= :fin'; $params['fin'] = $fin; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$paiements = prepareQuery(
    "SELECT pa.*, e.nom, e.prenom, e.matricule, " . classe_label_sql('c') . " AS nom_classe, f.libelle AS frais_libelle,
            u.prenom AS agent_prenom, u.nom AS agent_nom
     FROM paiements pa
     JOIN eleves e ON e.id = pa.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN frais_scolaires f ON f.id = pa.frais_id
     LEFT JOIN utilisateurs u ON u.id = pa.enregistre_par
     $whereSql
     ORDER BY pa.date_paiement DESC, pa.id DESC",
    $params
)->fetchAll();
$totalFiltre = array_sum(array_column($paiements, 'montant'));

$eleves = prepareQuery(
    "SELECT e.id, e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe
     FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
     WHERE e.statut = 'actif' ORDER BY e.nom, e.prenom"
)->fetchAll();

$frais = prepareQuery(
    'SELECT id, libelle, montant FROM frais_scolaires WHERE actif = TRUE ORDER BY libelle'
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-coins me-2 text-primary"></i>Paiements <span class="text-muted fs-6">(<?= count($paiements) ?> — <?= e(format_montant($totalFiltre)) ?>)</span></h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($canManage): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Enregistrer un paiement</div>
    <div class="card-body">
        <form method="post" action="" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4 col-lg-3">
                <label class="form-label required" for="eleve_recherche">Élève</label>
                <input type="text" id="eleve_recherche" class="form-control mb-1"
                       placeholder="Rechercher : nom, prénom ou matricule…" autocomplete="off"
                       oninput="filterSelect(this)">
                <select name="eleve_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($eleves as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= (int)$d['eleve_id'] === (int)$el['id'] ? 'selected' : '' ?>>
                            <?= e($el['matricule'] . ' — ' . $el['prenom'] . ' ' . $el['nom'] . ($el['nom_classe'] ? ' (' . $el['nom_classe'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label">Frais</label>
                <select name="frais_id" class="form-select">
                    <option value="0">— Aucun —</option>
                    <?php foreach ($frais as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= (int)$d['frais_id'] === (int)$f['id'] ? 'selected' : '' ?>>
                            <?= e($f['libelle'] . ' (' . format_montant($f['montant']) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label required">Montant (<?= e(devise()) ?>)</label>
                <input type="number" step="0.01" min="0.01" name="montant" class="form-control" value="<?= e($d['montant']) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Période</label>
                <input type="text" name="periode" class="form-control" value="<?= e($d['periode']) ?>" placeholder="Ex: Octobre 2025">
            </div>
            <div class="col-md-2">
                <label class="form-label required">Date</label>
                <input type="date" name="date_paiement" class="form-control" value="<?= e($d['date_paiement']) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mode</label>
                <input type="hidden" name="mode_paiement" value="especes">
                <input type="text" class="form-control" value="Espèces" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Référence</label>
                <input type="text" name="reference" class="form-control" value="<?= e($d['reference']) ?>" placeholder="N° transaction...">
            </div>
            <div class="col-md-5">
                <label class="form-label">Observation</label>
                <input type="text" name="observation" class="form-control" value="<?= e($d['observation']) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Élève, matricule ou n° de reçu..." oninput="filterTable(this)" autocomplete="off">
            </div>
            <div class="col-md-3">
                <select name="frais_id" class="form-select">
                    <option value="0">Tous les frais</option>
                    <?php foreach ($frais as $f): ?><option value="<?= $f['id'] ?>" <?= $fraisId===$f['id']?'selected':'' ?>><?= e($f['libelle']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="debut" class="form-control" value="<?= e($debut) ?>" title="Du">
            </div>
            <div class="col-md-2">
                <input type="date" name="fin" class="form-control" value="<?= e($fin) ?>" title="Au">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Reçu</th><th>Élève</th><th>Classe</th><th>Frais</th><th>Période</th><th>Mode</th><th>Montant</th><th>Date</th><th>Agent</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                    <?php if (empty($paiements)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Aucun paiement trouvé.</td></tr>
                    <?php else: foreach ($paiements as $p): ?>
                        <tr>
                            <td class="small"><code><?= e($p['recu_numero']) ?></code></td>
                            <td><?= e($p['prenom'] . ' ' . $p['nom']) ?><br><small class="text-muted"><?= e($p['matricule']) ?></small></td>
                            <td class="small"><?= e($p['nom_classe'] ?? '-') ?></td>
                            <td class="small"><?= e($p['frais_libelle'] ?? '-') ?></td>
                            <td class="small"><?= e($p['periode'] ?: '-') ?></td>
                            <td class="small"><?= e($modes[$p['mode_paiement']] ?? $p['mode_paiement']) ?></td>
                            <td class="fw-semibold text-success"><?= e(format_montant($p['montant'])) ?></td>
                            <td class="small"><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                            <td class="small text-muted"><?= e(trim(($p['agent_prenom'] ?? '') . ' ' . ($p['agent_nom'] ?? ''))) ?: '-' ?></td>
                            <td class="text-end text-nowrap">
                                <a href="recu.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Reçu" target="_blank"><i class="fa-solid fa-print"></i></a>
                                <?php if ($canManage): ?>
                                <form method="post" action="" class="d-inline" onsubmit="return confirmDelete('Supprimer ce paiement ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
