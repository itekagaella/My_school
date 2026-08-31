<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Communications';
$active_menu = 'communications';

$type_filter = clean_input(get('type', ''));
$types = ['annonce','alerte','info'];

$where = "c.statut = 'publie' AND (c.destinataires = 'tous' OR c.destinataires LIKE '%role:eleve%' OR c.destinataires LIKE '%classe%')";
$params = [];
if ($type_filter !== '' && in_array($type_filter, $types)) {
    $where .= ' AND c.type = :type';
    $params['type'] = $type_filter;
}

$communications = prepareQuery(
    "SELECT c.*, u.nom AS auteur_nom, u.prenom AS auteur_prenom
     FROM communications c
     LEFT JOIN utilisateurs u ON u.id = c.auteur_id
     WHERE $where
     ORDER BY c.date_publication DESC, c.id DESC",
    $params
)->fetchAll();

$type_icon = ['annonce'=>'fa-megaphone','alerte'=>'fa-triangle-exclamation','info'=>'fa-circle-info'];
$type_color = ['annonce'=>'primary','alerte'=>'danger','info'=>'info'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>Communications</h4>
    <form method="get" action="" class="d-flex gap-2 align-items-center">
        <select name="type" class="form-select" onchange="this.form.submit()" style="width:180px;">
            <option value="">Tous les types</option>
            <option value="annonce" <?= $type_filter==='annonce'?'selected':'' ?>>Annonce</option>
            <option value="alerte" <?= $type_filter==='alerte'?'selected':'' ?>>Alerte</option>
            <option value="info" <?= $type_filter==='info'?'selected':'' ?>>Info</option>
        </select>
    </form>
</div>

<?php if (empty($communications)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">Aucune communication publiée.</div>
    </div>
<?php else: foreach ($communications as $comm): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="flex-shrink-0 rounded-circle bg-<?= $type_color[$comm['type']] ?? 'info' ?> bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:44px;height:44px;">
                    <i class="fa-solid <?= $type_icon[$comm['type']] ?? 'fa-circle-info' ?> text-<?= $type_color[$comm['type']] ?? 'info' ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1">
                            <span class="badge bg-<?= $type_color[$comm['type']] ?? 'info' ?> me-2"><?= e(ucfirst($comm['type'])) ?></span>
                            <?= e($comm['titre']) ?>
                        </h6>
                        <small class="text-muted text-nowrap ms-2"><?= date('d/m/Y H:i', strtotime($comm['date_publication'])) ?></small>
                    </div>
                    <p class="mb-1 text-muted" style="white-space:pre-wrap;"><?= e($comm['contenu']) ?></p>
                    <small class="text-muted">
                        <i class="fa-solid fa-user me-1"></i>
                        <?= e(($comm['auteur_prenom'] ?? '') . ' ' . ($comm['auteur_nom'] ?? 'Administration')) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
