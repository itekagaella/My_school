<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Documents';
$active_menu = 'documents';

$clause = "d.statut = 'actif' AND (d.id IN (SELECT document_id FROM document_partages WHERE user_id = :uid) OR d.id NOT IN (SELECT document_id FROM document_partages))";
$params = ['uid' => $user['id']];

$search = clean_input(get('search', ''));
if ($search !== '') {
    $clause .= ' AND LOWER(d.nom_fichier) LIKE :s';
    $params['s'] = '%' . strtolower($search) . '%';
}

$documents = prepareQuery(
    "SELECT d.*, u.nom AS auteur_nom, u.prenom AS auteur_prenom
     FROM documents d
     LEFT JOIN utilisateurs u ON u.id = d.auteur_id
     WHERE $clause
     ORDER BY d.created_at DESC",
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-folder-open me-2 text-primary"></i>Documents</h4>
    <form method="get" action="" class="d-flex gap-2">
        <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher un document...">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="fa-solid fa-file me-2"></i>Documents disponibles (<?= count($documents) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom du fichier</th>
                        <th>Type</th>
                        <th>Taille</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun document disponible.</td></tr>
                    <?php else: foreach ($documents as $doc):
                        $ext = strtolower(pathinfo($doc['nom_fichier'], PATHINFO_EXTENSION));
                        $icon = 'fa-file';
                        $icon_color = '';
                        if ($ext === 'pdf') { $icon = 'fa-file-pdf'; $icon_color = 'text-danger'; }
                        elseif (in_array($ext, ['doc','docx'])) { $icon = 'fa-file-word'; $icon_color = 'text-primary'; }
                        elseif (in_array($ext, ['xls','xlsx'])) { $icon = 'fa-file-excel'; $icon_color = 'text-success'; }
                        elseif (in_array($ext, ['ppt','pptx'])) { $icon = 'fa-file-powerpoint'; $icon_color = 'text-warning'; }
                        elseif (in_array($ext, ['jpg','jpeg','png','gif'])) { $icon = 'fa-file-image'; $icon_color = 'text-info'; }
                        $size = (int)$doc['taille'];
                        if ($size >= 1048576) $taille = round($size / 1048576, 1) . ' Mo';
                        elseif ($size >= 1024) $taille = round($size / 1024, 1) . ' Ko';
                        else $taille = $size . ' o';
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid <?= $icon ?> <?= $icon_color ?>" style="font-size:24px;"></i>
                                    <strong><?= e($doc['nom_fichier']) ?></strong>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= e(strtoupper($ext)) ?></span></td>
                            <td class="small text-nowrap"><?= $taille ?></td>
                            <td class="small text-muted"><?= e($doc['description'] ?? '-') ?></td>
                            <td class="small text-nowrap"><?= date('d/m/Y', strtotime($doc['created_at'])) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= BASE_URL ?>client/documents/download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="fa-solid fa-download"></i> Télécharger</a>
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
