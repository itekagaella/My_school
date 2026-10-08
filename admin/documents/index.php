<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.download');

$page_title = 'Gestion documentaire';
$active_menu = 'documents';

$search = clean_input(get('search', ''));
$type_filter = clean_input(get('type', ''));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(d.nom_fichier) LIKE :s OR LOWER(d.description) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($type_filter !== '') {
    $where[] = 'd.type_fichier = :type';
    $params['type'] = $type_filter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$documents = prepareQuery(
    "SELECT d.*, u.prenom AS auteur_prenom, u.nom AS auteur_nom
     FROM documents d
     LEFT JOIN utilisateurs u ON u.id = d.auteur_id
     $whereSql ORDER BY d.created_at DESC",
    $params
)->fetchAll();

$canManage = has_permission('documents.manage');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-folder-open me-2 text-primary"></i>Documents <span class="text-muted fs-6">(<?= count($documents) ?>)</span></h4>
    <?php if (has_permission('documents.upload')): ?>
        <a href="upload.php" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Téléverser un document</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher un document...">
            </div>
            <div class="col-md-4">
                <select name="type" class="form-select">
                    <option value="">Tous les types</option>
                    <option value="pdf" <?= $type_filter==='pdf'?'selected':'' ?>>PDF</option>
                    <option value="docx" <?= $type_filter==='docx'?'selected':'' ?>>Word</option>
                    <option value="xlsx" <?= $type_filter==='xlsx'?'selected':'' ?>>Excel</option>
                    <option value="pptx" <?= $type_filter==='pptx'?'selected':'' ?>>PowerPoint</option>
                    <option value="jpg" <?= $type_filter==='jpg'?'selected':'' ?>>Image</option>
                    <option value="png" <?= $type_filter==='png'?'selected':'' ?>>Image PNG</option>
                    <option value="txt" <?= $type_filter==='txt'?'selected':'' ?>>Texte</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nom du fichier</th>
                        <th>Type</th>
                        <th>Taille</th>
                        <th>Auteur</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun document trouvé.</td></tr>
                    <?php else: foreach ($documents as $doc): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php
                                    $icon = 'fa-file';
                                    $ext = strtolower(pathinfo($doc['nom_fichier'], PATHINFO_EXTENSION));
                                    if ($ext === 'pdf') $icon = 'fa-file-pdf text-danger';
                                    elseif (in_array($ext, ['doc','docx'])) $icon = 'fa-file-word text-primary';
                                    elseif (in_array($ext, ['xls','xlsx'])) $icon = 'fa-file-excel text-success';
                                    elseif (in_array($ext, ['ppt','pptx'])) $icon = 'fa-file-powerpoint text-warning';
                                    elseif (in_array($ext, ['jpg','jpeg','png','gif'])) $icon = 'fa-file-image text-info';
                                    ?>
                                    <i class="fa-solid <?= $icon ?>" style="font-size:24px;"></i>
                                    <div>
                                        <strong><?= e($doc['nom_fichier']) ?></strong>
                                        <?php if ($doc['description']): ?>
                                            <br><small class="text-muted"><?= e(mb_strimwidth($doc['description'], 0, 60, '...')) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= e(strtoupper($ext)) ?></span></td>
                            <td class="text-nowrap">
                                <?php
                                $size = (int)$doc['taille'];
                                if ($size >= 1048576) echo round($size / 1048576, 1) . ' Mo';
                                elseif ($size >= 1024) echo round($size / 1024, 1) . ' Ko';
                                else echo $size . ' o';
                                ?>
                            </td>
                            <td class="small"><?= e(($doc['auteur_prenom'] ?? '') . ' ' . ($doc['auteur_nom'] ?? '')) ?></td>
                            <td class="small text-nowrap"><?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></td>
                            <td>
                                <?php if ($doc['statut'] === 'actif'): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Archivé</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="fa-solid fa-download"></i></a>
                                <a href="versions.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-info" title="Versions"><i class="fa-solid fa-code-branch"></i></a>
                                <a href="partage.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Partager"><i class="fa-solid fa-share-nodes"></i></a>
                                <?php if ($canManage): ?>
                                    <a href="delete.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fa-solid fa-trash"></i></a>
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
