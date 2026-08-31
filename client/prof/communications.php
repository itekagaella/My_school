<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['prof']);
$user = current_user();
$page_title = 'Communications';
$active_menu = 'communications';

$communications = prepareQuery(
    "SELECT * FROM communications
    WHERE statut='publie' AND (destinataires='tous' OR destinataires LIKE '%role:prof%')
    ORDER BY date_publication DESC"
)->fetchAll();

$type_icon = ['annonce'=>'fa-megaphone','alerte'=>'fa-triangle-exclamation','info'=>'fa-circle-info'];
$type_color = ['annonce'=>'primary','alerte'=>'danger','info'=>'info'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fa-solid fa-bullhorn me-2"></i>Communications</h5>
</div>

<?php if (empty($communications)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-bullhorn-slash fs-1 mb-3 d-block"></i>
            Aucune communication disponible
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($communications as $comm): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 rounded-circle bg-<?= $type_color[$comm['type']] ?? 'info' ?> bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                                <i class="fa-solid <?= $type_icon[$comm['type']] ?? 'fa-info-circle' ?> text-<?= $type_color[$comm['type']] ?? 'info' ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-1 fw-semibold"><?= e($comm['titre']) ?></h6>
                                    <span class="badge bg-<?= $type_color[$comm['type']] ?? 'info' ?>"><?= e(ucfirst($comm['type'])) ?></span>
                                </div>
                                <p class="text-muted small mb-2"><?= nl2br(e(mb_substr($comm['contenu'], 0, 200))) ?><?= mb_strlen($comm['contenu']) > 200 ? '...' : '' ?></p>
                                <div class="text-muted">
                                    <small><i class="fa-solid fa-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($comm['date_publication'])) ?></small>
                                    <?php if (!empty($comm['auteur'])): ?>
                                        <small class="ms-2"><i class="fa-solid fa-user me-1"></i><?= e($comm['auteur']) ?></small>
                                    <?php endif; ?>
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
