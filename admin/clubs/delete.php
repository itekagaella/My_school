<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('clubs.delete');

$id = (int)get('id', 0);
$club = prepareQuery('SELECT * FROM clubs WHERE id = :id', ['id' => $id])->fetch();

if (!$club) {
    set_flash('error', 'Club introuvable.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    prepareQuery('DELETE FROM clubs WHERE id = :id', ['id' => $id]);
    log_activity('clubs.delete', 'Suppression du club ' . $club['nom_club']);
    set_flash('success', 'Club supprimé.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size:48px;"></i>
        <h4 class="mt-3">Confirmer la suppression</h4>
        <p class="text-muted">Supprimer le club <strong><?= e($club['nom_club']) ?></strong> ? Cette action retirera également tous ses membres.</p>
        <form method="post" action="" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
            <a href="index.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
