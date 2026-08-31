<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('communications.publish');

$id = (int)get('id', 0);
$comm = prepareQuery('SELECT * FROM communications WHERE id = :id', ['id' => $id])->fetch();

if (!$comm) {
    set_flash('error', 'Communication introuvable.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        prepareQuery('DELETE FROM communications WHERE id = :id', ['id' => $id]);
        log_activity('communications.delete', 'Suppression de la communication "' . $comm['titre'] . '" (ID ' . $id . ')');
        set_flash('success', 'Communication supprimée.');
    }
    header('Location: index.php');
    exit;
}

$page_title = 'Supprimer une communication';
$active_menu = 'communications';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-trash me-2"></i>Confirmer la suppression</div>
    <div class="card-body">
        <?php display_flash(); ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>Êtes-vous sûr de vouloir supprimer cette communication ?
        </div>
        <div class="mb-3">
            <strong><?= e($comm['titre']) ?></strong><br>
            <span class="text-muted small"><?= e(ucfirst($comm['type'])) ?> — <?= $comm['date_publication'] ? date('d/m/Y H:i', strtotime($comm['date_publication'])) : 'Non publié' ?></span>
        </div>
        <form method="post" action="">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
            <a href="view.php?id=<?= $comm['id'] ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
