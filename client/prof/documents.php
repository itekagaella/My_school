<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['prof']);
$user = current_user();
$page_title = 'Documents';
$active_menu = 'documents';

$documents = prepareQuery(
    "SELECT * FROM documents WHERE statut='actif' ORDER BY created_at DESC"
)->fetchAll();

$type_icon = [
    'pdf'=>'fa-file-pdf','doc'=>'fa-file-word','docx'=>'fa-file-word',
    'xls'=>'fa-file-excel','xlsx'=>'fa-file-excel','ppt'=>'fa-file-powerpoint',
    'pptx'=>'fa-file-powerpoint','jpg'=>'fa-file-image','jpeg'=>'fa-file-image',
    'png'=>'fa-file-image','gif'=>'fa-file-image','txt'=>'fa-file-lines'
];
$type_color = [
    'pdf'=>'danger','doc'=>'primary','docx'=>'primary',
    'xls'=>'success','xlsx'=>'success','ppt'=>'warning',
    'pptx'=>'warning','jpg'=>'info','jpeg'=>'info','png'=>'info','gif'=>'info','txt'=>'secondary'
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fa-solid fa-folder-open me-2"></i>Documents</h5>
</div>

<?php if (empty($documents)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-folder-open fs-1 mb-3 d-block"></i>
            Aucun document disponible
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($documents as $doc):
            $ext = strtolower(pathinfo($doc['nom_fichier'] ?? $doc['titre'] ?? '', PATHINFO_EXTENSION));
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 rounded bg-<?= $type_color[$ext] ?? 'secondary' ?> bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                                <i class="fa-solid <?= $type_icon[$ext] ?? 'fa-file' ?> text-<?= $type_color[$ext] ?? 'secondary' ?> fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-semibold"><?= e($doc['titre']) ?></h6>
                                <?php if (!empty($doc['description'])): ?>
                                    <p class="text-muted small mb-2"><?= e(mb_substr($doc['description'], 0, 100)) ?><?= mb_strlen($doc['description'] ?? '') > 100 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted"><i class="fa-solid fa-calendar me-1"></i><?= date('d/m/Y', strtotime($doc['created_at'])) ?></small>
                                    <a href="<?= BASE_URL ?>admin/documents/download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary">
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
