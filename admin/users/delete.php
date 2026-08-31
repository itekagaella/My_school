<?php
/**
 * Suppression d'un utilisateur (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('users.delete');

$id = (int)get('id', 0);
$user = prepareQuery('SELECT * FROM utilisateurs WHERE id = :id', ['id' => $id])->fetch();

if (!$user) {
    set_flash('error', 'Utilisateur introuvable.');
} elseif ($id === (int)($_SESSION['user_id'] ?? 0)) {
    set_flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Session expirée.');
        } else {
            // Suppression sécurisée (requête préparée)
            prepareQuery('DELETE FROM utilisateurs WHERE id = :id', ['id' => $id]);
            log_activity('users.delete', 'Suppression de l\'utilisateur ' . $user['email']);
            set_flash('success', 'Utilisateur supprimé.');
            header('Location: index.php');
            exit;
        }
    }
}
?>
<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size:48px;"></i>
        <h4 class="mt-3">Confirmer la suppression</h4>
        <p class="text-muted">
            Voulez-vous vraiment supprimer l'utilisateur
            <strong><?= e($user['prenom'] . ' ' . $user['nom']) ?></strong>
            (<?= e($user['email']) ?>) ?
        </p>
        <?php if ($user['id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
            <p class="alert alert-warning d-inline-block">Vous ne pouvez pas supprimer votre propre compte.</p>
            <div><a href="index.php" class="btn btn-primary">Retour</a></div>
        <?php else: ?>
        <form method="post" action="" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
            <a href="index.php" class="btn btn-secondary">Annuler</a>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
