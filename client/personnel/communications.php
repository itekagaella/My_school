<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['personnel']);
$user = current_user();
$page_title = 'Communications';
$active_menu = 'communications';

$pers = prepareQuery(
    'SELECT * FROM personnel WHERE user_id = :u',
    ['u' => $user['id']]
)->fetch();

if (!$pers) {
    set_flash('error', 'Profil personnel introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$communications = prepareQuery(
    "SELECT * FROM communications WHERE statut='publie' ORDER BY date_publication DESC"
)->fetchAll();

$type_icon = ['annonce'=>'fa-megaphone','alerte'=>'fa-triangle-exclamation','info'=>'fa-circle-info'];
$type_color = ['annonce'=>'primary','alerte'=>'danger','info'=>'info'];
$type_label = ['annonce'=>'Annonce','alerte'=>'Alerte','info'=>'Information'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<?php if (empty($communications)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-bullhorn fa-3x text-muted mb-3"></i>
            <div class="text-muted">Aucune communication disponible</div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($communications as $comm):
            $color = $type_color[$comm['type']] ?? 'info';
            $icon = $type_icon[$comm['type']] ?? 'fa-info-circle';
            $label = $type_label[$comm['type']] ?? 'Info';
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-2">
                            <span class="badge bg-<?= $color ?> me-2">
                                <i class="fa-solid <?= $icon ?> me-1"></i><?= $label ?>
                            </span>
                        </div>
                        <h6 class="card-title fw-bold"><?= e($comm['titre']) ?></h6>
                        <p class="card-text text-muted small mb-3"><?= e(mb_substr($comm['contenu'], 0, 200)) ?><?= mb_strlen($comm['contenu']) > 200 ? '...' : '' ?></p>
                        <div class="d-flex align-items-center justify-content-between">
                            <small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($comm['date_publication'])) ?></small>
                            <?php if (!empty($comm['fichier_joint'])): ?>
                                <small class="text-muted"><i class="fa-solid fa-paperclip me-1"></i>Pièce jointe</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
