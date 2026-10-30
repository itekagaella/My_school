<?php
/**
 * Recherche documentaire
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.view');

$page_title = 'Recherche documentaire';
$active_menu = 'recherche_documentaire';

$q = clean_input($_GET['q'] ?? '');
$source = clean_input($_GET['source'] ?? 'all');

$results = [];
if ($q !== '') {
    if ($source === 'all' || $source === 'documents') {
        $sql = "SELECT d.id, d.nom_fichier, d.type_fichier, d.description, d.created_at, 'documents' as source, d.fichier_path as chemin
                FROM documents d
                WHERE d.statut = 'actif'
                AND (LOWER(d.nom_fichier) LIKE LOWER(:q) OR LOWER(COALESCE(d.description,'')) LIKE LOWER(:q))";
        $docs = prepareQuery($sql, ['q' => '%' . $q . '%'])->fetchAll();
        foreach ($docs as $doc) { $results[] = $doc; }
    }
    if ($source === 'all' || $source === 'archives') {
        $sql = "SELECT ae.id, ae.titre as nom_fichier, ae.fichier_type as type_fichier, ae.description, ae.date_versement as created_at, 'archives' as source, ae.fichier_chemin as chemin, ae.reference
                FROM archives_electroniques ae
                WHERE ae.statut = 'actif'
                AND (LOWER(ae.titre) LIKE LOWER(:q) OR LOWER(ae.reference) LIKE LOWER(:q) OR LOWER(COALESCE(ae.mots_cles,'')) LIKE LOWER(:q) OR LOWER(COALESCE(ae.description,'')) LIKE LOWER(:q))";
        $archives = prepareQuery($sql, ['q' => '%' . $q . '%'])->fetchAll();
        foreach ($archives as $arch) { $results[] = $arch; }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card mb-3">
    <div class="card-header">
        <span><i class="fa-solid fa-search me-2"></i>Recherche documentaire</span>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Rechercher par nom, description, mots-clés, référence..." value="<?= e($q) ?>" required>
            </div>
            <div class="col-md-3">
                <select name="source" class="form-select">
                    <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>Toutes les sources</option>
                    <option value="documents" <?= $source === 'documents' ? 'selected' : '' ?>>Documents</option>
                    <option value="archives" <?= $source === 'archives' ? 'selected' : '' ?>>Archivage électronique</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-search me-1"></i>Rechercher</button>
            </div>
        </form>
    </div>
</div>

<?php if ($q !== ''): ?>
<div class="card">
    <div class="card-header"><span>Résultats (<?= count($results) ?>)</span></div>
    <div class="card-body p-0">
        <?php if (empty($results)): ?>
            <div class="p-4 text-center text-muted">Aucun résultat trouvé pour "<strong><?= e($q) ?></strong>".</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Document</th><th>Source</th><th>Type</th><th>Date</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><strong><?= e($r['nom_fichier']) ?></strong><?php if (!empty($r['reference'])): ?><br><small class="text-muted">Réf: <?= e($r['reference']) ?></small><?php endif; ?><?php if ($r['description']): ?><br><small class="text-muted"><?= e(mb_substr($r['description'], 0, 80)) ?><?= mb_strlen($r['description']) > 80 ? '...' : '' ?></small><?php endif; ?></td>
                                <td><?php if ($r['source'] === 'documents'): ?><span class="badge bg-primary">Documents</span><?php else: ?><span class="badge bg-success">Archivage électronique</span><?php endif; ?></td>
                                <td><span class="badge bg-secondary"><?= strtoupper($r['type_fichier'] ?? '') ?></span></td>
                                <td class="small text-muted"><?= $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-' ?></td>
                                <td class="text-end">
                                    <?php if ($r['source'] === 'documents'): ?>
                                        <a href="../documents/download.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download"></i></a>
                                    <?php else: ?>
                                        <a href="../archivage-electronique/voir.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i></a>
                                        <a href="../archivage-electronique/telecharger.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-download"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer_content.php'; require_once __DIR__ . '/../../includes/footer.php'; ?>
