<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('communications.view');

$id = (int)get('id', 0);
$comm = prepareQuery(
    "SELECT c.*, u.nom, u.prenom
     FROM communications c LEFT JOIN utilisateurs u ON u.id = c.auteur_id
     WHERE c.id = :id",
    ['id' => $id]
)->fetch();

if (!$comm) {
    set_flash('error', 'Communication introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = e($comm['titre']);
$active_menu = 'communications';

$canEdit = has_permission('communications.publish');
$canDelete = has_permission('communications.publish');

$typeBadges = ['annonce'=>'bg-primary','alerte'=>'bg-danger','info'=>'bg-info'];
$statutBadges = ['brouillon'=>'bg-secondary','publie'=>'bg-success','archive'=>'bg-warning'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><?= e($comm['titre']) ?></h4>
    <div class="d-flex gap-2">
        <?php if ($canEdit): ?>
            <a href="edit.php?id=<?= $comm['id'] ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i>Modifier</a>
            <?php if ($comm['statut'] === 'brouillon'): ?>
                <a href="publish.php?id=<?= $comm['id'] ?>" class="btn btn-outline-success" onclick="return confirm('Publier cette communication ?')"><i class="fa-solid fa-paper-plane me-1"></i>Publier</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <a href="delete.php?id=<?= $comm['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Supprimer cette communication ?')"><i class="fa-solid fa-trash me-1"></i>Supprimer</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-newspaper me-2"></i>Contenu</div>
            <div class="card-body">
                <div style="white-space:pre-wrap;"><?= e($comm['contenu']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-circle-info me-2"></i>Détails</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:40%">Type</td>
                        <td><span class="badge <?= $typeBadges[$comm['type']] ?? 'bg-secondary' ?>"><?= e(ucfirst($comm['type'])) ?></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Statut</td>
                        <td><span class="badge <?= $statutBadges[$comm['statut']] ?? 'bg-secondary' ?>"><?= e(ucfirst($comm['statut'])) ?></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Destinataires</td>
                        <td><?= e($comm['destinataires']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Auteur</td>
                        <td><?= e($comm['prenom'] . ' ' . $comm['nom']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Publication</td>
                        <td><?= $comm['date_publication'] ? date('d/m/Y H:i', strtotime($comm['date_publication'])) : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Créé le</td>
                        <td><?= date('d/m/Y H:i', strtotime($comm['updated_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
