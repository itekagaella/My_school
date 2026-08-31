<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$id = (int)get('id', 0);
$doc = prepareQuery('SELECT * FROM documents WHERE id = :id', ['id' => $id])->fetch();

if (!$doc) {
    set_flash('error', 'Document introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Supprimer un document';
$active_menu = 'documents';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
        header('Location: delete.php?id=' . $id);
        exit;
    }

    $fullPath = ROOT_PATH . $doc['fichier_path'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    $versions = prepareQuery('SELECT fichier_path FROM document_versions WHERE document_id = :did', ['did' => $id])->fetchAll();
    foreach ($versions as $v) {
        $vPath = ROOT_PATH . $v['fichier_path'];
        if (file_exists($vPath)) {
            unlink($vPath);
        }
    }

    prepareQuery('DELETE FROM document_versions WHERE document_id = :did', ['did' => $id]);
    prepareQuery('DELETE FROM document_partages WHERE document_id = :did', ['did' => $id]);
    prepareQuery('DELETE FROM documents WHERE id = :id', ['id' => $id]);

    log_activity('documents.delete', 'Suppression du document ' . $doc['nom_fichier'] . ' (ID ' . $id . ')');
    set_flash('success', 'Document supprimé avec succès.');
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
        <p class="text-muted">
            Voulez-vous vraiment supprimer le document
            <strong><?= e($doc['nom_fichier']) ?></strong>
            ? Cette action est irréversible et supprimera également toutes les versions et partages associés.
        </p>
        <form method="post" action="" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
            <a href="index.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
