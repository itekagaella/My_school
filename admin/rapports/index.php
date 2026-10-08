<?php
/**
 * Rapports et exports (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('reports.view');

require_once __DIR__ . '/data.php';

$page_title = 'Rapports';
$active_menu = 'rapports';

$type = rapport_type_valide((string)get('type', 'eleves'));
$classe_id = (int)get('classe_id', 0);
$periode_debut = (string)get('debut', date('Y-m-01'));
$periode_fin = (string)get('fin', date('Y-m-d'));

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
$profs = prepareQuery('SELECT * FROM profs WHERE statut = :s ORDER BY nom', ['s'=>'actif'])->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-file-lines me-2"></i>Génération de rapports</div>
    <div class="card-body">
        <form method="get" action="" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label">Type de rapport</label>
                <select name="type" class="form-select">
                    <option value="eleves" <?= $type==='eleves'?'selected':'' ?>>Liste des élèves</option>
                    <option value="profs" <?= $type==='profs'?'selected':'' ?>>Liste des professeurs</option>
                    <option value="notes" <?= $type==='notes'?'selected':'' ?>>Rapport de notes</option>
                    <option value="presences" <?= $type==='presences'?'selected':'' ?>>Rapport de présences</option>
                    <option value="moyennes" <?= $type==='moyennes'?'selected':'' ?>>Moyennes par élève</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Classe</label>
                <select name="classe_id" class="form-select">
                    <option value="0">Toutes</option>
                    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classe_id==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Début</label>
                <input type="date" name="debut" class="form-control" value="<?= e($periode_debut) ?>"></div>
            <div class="col-md-2"><label class="form-label">Fin</label>
                <input type="date" name="fin" class="form-control" value="<?= e($periode_fin) ?>"></div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-eye me-1"></i>Aperçu</button>
            </div>
        </form>

        <div class="d-flex gap-2 mb-3">
            <?php if (has_permission('reports.pdf')): ?>
            <a href="pdf.php?type=<?= e($type) ?>&amp;classe_id=<?= $classe_id ?>&amp;debut=<?= e($periode_debut) ?>&amp;fin=<?= e($periode_fin) ?>" class="btn btn-danger">
                <i class="fa-solid fa-file-pdf me-1"></i>Exporter en PDF
            </a>
            <?php endif; ?>
            <?php if (has_permission('reports.excel')): ?>
            <a href="excel.php?type=<?= e($type) ?>&amp;classe_id=<?= $classe_id ?>&amp;debut=<?= e($periode_debut) ?>&amp;fin=<?= e($periode_fin) ?>" class="btn btn-success">
                <i class="fa-solid fa-file-excel me-1"></i>Exporter en Excel
            </a>
            <?php endif; ?>
            <a href="custom.php" class="btn btn-outline-primary"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Rapport personnalisé</a>
        </div>

        <?php
        // Aperçu : même jeu de données que les exports (50 premières lignes)
        $ds = rapport_dataset($type, $classe_id, $periode_debut, $periode_fin);
        $total = count($ds['rows']);
        $apercu = array_slice($ds['rows'], 0, 50);
        ?>

        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($ds['headers'] as $h): ?>
                            <th><?= e($h) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($apercu)): ?>
                        <tr><td colspan="<?= max(1, count($ds['headers'])) ?>" class="text-center text-muted py-4">Aucune donnée pour ce rapport.</td></tr>
                    <?php else: foreach ($apercu as $rw): ?>
                        <tr>
                            <?php foreach ($rw as $i => $v): ?>
                                <td><?= e(rapport_format_value($ds['types'][$i] ?? 'text', $v)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <?php if ($total > 50): ?><p class="text-muted small">Aperçu limité à 50 lignes sur <?= $total ?>. Exportez pour le rapport complet.</p><?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
