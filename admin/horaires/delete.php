<?php
/**
 * Suppression d'un créneau horaire (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('horaires.delete');

$id = (int)get('id', 0);
$classe_id = (int)get('classe_id', 0);

$h = prepareQuery('SELECT * FROM horaires WHERE id = :id', ['id' => $id])->fetch();
if (!$h) {
    set_flash('error', 'Créneau introuvable.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    prepareQuery('DELETE FROM horaires WHERE id = :id', ['id' => $id]);
    log_activity('horaires.delete', 'Suppression du créneau #' . $id);
    set_flash('success', 'Créneau supprimé.');
    header('Location: index.php' . ($classe_id ? '?classe_id=' . $classe_id : ''));
    exit;
}
?>
<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php if (!empty($h)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size:48px;"></i>
        <h4 class="mt-3">Confirmer la suppression</h4>
        <p class="text-muted">
            Supprimer le créneau du <strong><?= e($h['jour']) ?></strong> (<?= $h['heure_debut'] ?> - <?= $h['heure_fin'] ?>) ?
        </p>
        <form method="post" action="" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
            <a href="index.php?classe_id=<?= $classe_id ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
