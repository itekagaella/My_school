<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['personnel']);
$user = current_user();
$page_title = 'Documents';
$active_menu = 'documents';

$pers = prepareQuery(
    'SELECT * FROM personnel WHERE user_id = :u',
    ['u' => $user['id']]
)->fetch();

if (!$pers) {
    set_flash('error', 'Profil personnel introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$clause = "d.statut = 'actif' AND (d.id IN (SELECT document_id FROM document_partages WHERE user_id = :uid) OR d.id NOT IN (SELECT document_id FROM document_partages))";
$params = ['uid' => $user['id']];

$search = clean_input(get('search', ''));
if ($search !== '') {
    $clause .= ' AND LOWER(d.nom_fichier) LIKE :s';
    $params['s'] = '%' . strtolower($search) . '%';
}

$documents = prepareQuery(
    "SELECT d.* FROM documents d
     WHERE $clause
     ORDER BY d.created_at DESC",
    $params
)->fetchAll();

$file_icons = [
    'pdf' => ['icon'=>'fa-file-pdf','color'=>'danger'],
    'doc' => ['icon'=>'fa-file-word','color'=>'primary'],
    'docx' => ['icon'=>'fa-file-word','color'=>'primary'],
    'xls' => ['icon'=>'fa-file-excel','color'=>'success'],
    'xlsx' => ['icon'=>'fa-file-excel','color'=>'success'],
    'ppt' => ['icon'=>'fa-file-powerpoint','color'=>'warning'],
    'pptx' => ['icon'=>'fa-file-powerpoint','color'=>'warning'],
    'jpg' => ['icon'=>'fa-file-image','color'=>'info'],
    'jpeg' => ['icon'=>'fa-file-image','color'=>'info'],
    'png' => ['icon'=>'fa-file-image','color'=>'info'],
    'zip' => ['icon'=>'fa-file-zipper','color'=>'secondary'],
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h5 class="mb-0"><i class="fa-solid fa-folder-open me-2 text-primary"></i>Documents</h5>
    <form method="get" action="" class="d-flex gap-2">
        <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher un document...">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
</div>

<?php if (empty($documents)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-folder-open fa-3x text-muted mb-3"></i>
            <div class="text-muted">Aucun document disponible</div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($documents as $doc):
            $ext = strtolower(pathinfo($doc['nom_fichier'] ?? '', PATHINFO_EXTENSION));
            $fi = $file_icons[$ext] ?? ['icon'=>'fa-file','color'=>'secondary'];
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 rounded bg-<?= $fi['color'] ?> bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                                <i class="fa-solid <?= $fi['icon'] ?> text-<?= $fi['color'] ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="card-title fw-bold mb-1"><?= e($doc['nom_fichier']) ?></h6>
                                <small class="text-muted"><?= e($ext ? strtoupper($ext) : 'Fichier') ?></small>
                                <?php if (!empty($doc['description'])): ?>
                                    <p class="text-muted small mt-1 mb-2"><?= e(mb_substr($doc['description'], 0, 100)) ?></p>
                                <?php endif; ?>
                                <div class="d-flex align-items-center justify-content-between mt-2">
                                    <small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y', strtotime($doc['created_at'])) ?></small>
                                    <a href="<?= BASE_URL ?>client/documents/download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-<?= $fi['color'] ?>">
                                        <i class="fa-solid fa-download me-1"></i>Télécharger
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
