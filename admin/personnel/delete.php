<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('personnel.delete');

$id = (int)get('id', 0);
$p = prepareQuery('SELECT * FROM personnel WHERE id = :id', ['id' => $id])->fetch();

if (!$p) {
    set_flash('error', 'Membre du personnel introuvable.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        try {
            getDB()->beginTransaction();

            prepareQuery('DELETE FROM personnel WHERE id = :id', ['id' => $id]);

            if ($p['user_id']) {
                prepareQuery('UPDATE utilisateurs SET actif = FALSE WHERE id = :uid', ['uid' => $p['user_id']]);
            }

            getDB()->commit();

            log_activity('personnel.delete', 'Suppression du membre ' . $p['matricule'] . ' ' . $p['prenom'] . ' ' . $p['nom']);
            set_flash('success', 'Membre du personnel supprimé.');
        } catch (Exception $ex) {
            getDB()->rollBack();
            set_flash('error', 'Erreur lors de la suppression : ' . $ex->getMessage());
        }
        header('Location: index.php');
        exit;
    }
}

$page_title = 'Supprimer un membre du personnel';
$active_menu = 'personnel';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-triangle-exclamation me-2"></i>Confirmer la suppression</div>
    <div class="card-body">
        <p>Êtes-vous sûr de vouloir supprimer le membre du personnel suivant ?</p>
        <div class="alert alert-warning">
            <strong><?= e($p['prenom'] . ' ' . $p['nom']) ?></strong> — <?= e($p['matricule']) ?><br>
            Fonction : <?= e($p['fonction']) ?><br>
            Email : <?= e($p['email'] ?: '-') ?>
        </div>
        <p class="text-muted small">Cette action désactivera également le compte utilisateur associé.</p>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
