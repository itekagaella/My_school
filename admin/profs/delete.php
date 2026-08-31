<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('profs.delete');

$id = (int)get('id', 0);
$prof = prepareQuery('SELECT * FROM profs WHERE id = :id', ['id' => $id])->fetch();
if (!$prof) {
    set_flash('error', 'Professeur introuvable.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        try {
            if ($prof['user_id']) {
                prepareQuery('UPDATE utilisateurs SET actif = FALSE WHERE id = :uid', ['uid' => $prof['user_id']]);
            }
            prepareQuery('DELETE FROM profs WHERE id = :id', ['id' => $id]);
            log_activity('profs.delete', 'Suppression du professeur ' . $prof['matricule'] . ' - ' . $prof['prenom'] . ' ' . $prof['nom']);
            set_flash('success', 'Professeur supprimé avec succès.');
        } catch (Exception $ex) {
            set_flash('error', 'Erreur lors de la suppression : le professeur est peut-être lié à des données existantes.');
        }
        header('Location: index.php');
        exit;
    }
}

$page_title = 'Supprimer un professeur';
$active_menu = 'profs';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-trash me-2"></i>Confirmer la suppression</div>
    <div class="card-body">
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Êtes-vous sûr de vouloir supprimer définitivement ce professeur ? Cette action est irréversible.
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-1"><strong>Nom :</strong> <?= e($prof['prenom'] . ' ' . $prof['nom']) ?></p>
                <p class="mb-1"><strong>Matricule :</strong> <?= e($prof['matricule']) ?></p>
                <p class="mb-0"><strong>Spécialité :</strong> <?= e($prof['specialite'] ?? '-') ?></p>
            </div>
        </div>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer définitivement</button>
                <a href="view.php?id=<?= $prof['id'] ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
