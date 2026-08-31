<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$id = (int)get('id', 0);
$commentaire = prepareQuery(
    'SELECT c.*, d.nom_fichier, u.prenom AS user_prenom, u.nom AS user_nom
     FROM commentaires c
     LEFT JOIN documents d ON d.id = c.document_id
     LEFT JOIN utilisateurs u ON u.id = c.user_id
     WHERE c.id = :id',
    ['id' => $id]
)->fetch();

if (!$commentaire) {
    set_flash('error', 'Commentaire introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Supprimer un commentaire';
$active_menu = 'commentaires';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
        header('Location: delete.php?id=' . $id);
        exit;
    }

    prepareQuery('DELETE FROM commentaires WHERE id = :id', ['id' => $id]);

    log_activity('commentaires.delete', 'Suppression du commentaire ID ' . $id . ' sur "' . ($commentaire['nom_fichier'] ?? '') . '"');
    set_flash('success', 'Commentaire supprimé avec succès.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="card">
    <div class="card-body text-center py-5">
        <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size:48px;"></i>
        <h4 class="mt-3">Confirmer la suppression</h4>
        <p class="text-muted">
            Voulez-vous vraiment supprimer ce commentaire de
            <strong><?= e(($commentaire['user_prenom'] ?? '') . ' ' . ($commentaire['user_nom'] ?? '')) ?></strong>
            sur le document <strong><?= e($commentaire['nom_fichier'] ?? '—') ?></strong> ?
            Cette action est irréversible.
        </p>
        <div class="border rounded p-3 mb-3 bg-light text-start mx-auto" style="max-width:500px;">
            <?= nl2br(e($commentaire['contenu'])) ?>
        </div>
        <form method="post" action="" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
            <a href="index.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
