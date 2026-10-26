<?php
/**
 * Gestion financière — Soldes des élèves (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('finance.view');

$page_title = 'Soldes des élèves';
$active_menu = 'finance';

$search = clean_input(get('search', ''));
$classeId = (int)get('classe_id', 0);

$where = ["e.statut = 'actif'"];
$params = [];
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}
if ($classeId > 0) { $where[] = 'e.classe_id = :cl'; $params['cl'] = $classeId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$eleves = prepareQuery(
    "SELECT e.id, e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe, c.niveau,
            COALESCE((SELECT SUM(f.montant) FROM frais_scolaires f
                      WHERE f.actif = TRUE AND f.obligatoire = TRUE
                        AND (f.niveau IS NULL OR f.niveau = c.niveau)), 0) AS attendu,
            COALESCE((SELECT SUM(pa.montant) FROM paiements pa WHERE pa.eleve_id = e.id), 0) AS paye
     FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     $whereSql
     ORDER BY " . classes_order_sql('c.nom_classe') . ", e.nom, e.prenom",
    $params
)->fetchAll();

$classes = prepareQuery('SELECT id, nom_classe, section FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

$totalAttendu = 0.0; $totalPaye = 0.0;
foreach ($eleves as $el) { $totalAttendu += (float)$el['attendu']; $totalPaye += (float)$el['paye']; }

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Soldes des élèves</h4>
    <div class="d-flex gap-2">
        <a href="export.php?type=eleves<?= $classeId ? '&classe_id=' . $classeId : '' ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-csv me-1"></i>Exporter CSV</a>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Total attendu</div><div class="fs-5 fw-bold"><?= e(format_montant($totalAttendu)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Total payé</div><div class="fs-5 fw-bold text-success"><?= e(format_montant($totalPaye)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Reste à recouvrer</div><div class="fs-5 fw-bold text-danger"><?= e(format_montant($totalAttendu - $totalPaye)) ?></div></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-5"><input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Nom, prénom ou matricule..."></div>
            <div class="col-md-4">
                <select name="classe_id" class="form-select">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classeId===$c['id']?'selected':'' ?>><?= e(classe_label($c['nom_classe'], $c['section'] ?? null)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Matricule</th><th>Élève</th><th>Classe</th><th class="text-end">Attendu</th><th class="text-end">Payé</th><th class="text-end">Solde</th><th></th></tr>
                </thead>
                <tbody>
                    <?php if (empty($eleves)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun élève trouvé.</td></tr>
                    <?php else: foreach ($eleves as $el): $solde = (float)$el['attendu'] - (float)$el['paye']; ?>
                        <tr>
                            <td class="small text-nowrap"><?= e($el['matricule']) ?></td>
                            <td><?= e($el['prenom'] . ' ' . $el['nom']) ?></td>
                            <td class="small"><?= e($el['nom_classe'] ?? '-') ?></td>
                            <td class="text-end small"><?= e(format_montant($el['attendu'])) ?></td>
                            <td class="text-end small text-success"><?= e(format_montant($el['paye'])) ?></td>
                            <td class="text-end fw-semibold <?= $solde > 0 ? 'text-danger' : 'text-success' ?>"><?= e(format_montant($solde)) ?></td>
                            <td class="text-end"><a href="paiements.php?search=<?= urlencode($el['matricule']) ?>" class="btn btn-sm btn-outline-primary" title="Paiements"><i class="fa-solid fa-coins"></i></a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
