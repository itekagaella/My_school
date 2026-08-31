<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.upload');

$page_title = 'Téléverser un document';
$active_menu = 'documents';

$error = '';
$desc = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $desc = clean_input($_POST['description'] ?? '');

        if (empty($_FILES['fichier']['name'])) {
            $error = 'Veuillez sélectionner un fichier.';
        } else {
            $result = upload_file($_FILES['fichier'], 'documents');
            if (!$result[0]) {
                $error = $result[1];
            } else {
                $chemin = str_replace(ROOT_PATH, '', $result[2]);
                $nomFichier = $result[3];
                $typeFichier = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
                $taille = filesize($result[2]);

                prepareQuery(
                    "INSERT INTO documents (nom_fichier, fichier_path, type_fichier, taille, auteur_id, description, statut, created_at)
                     VALUES (:nf, :fp, :tf, :tl, :ai, :desc, 'actif', NOW())",
                    ['nf' => $nomFichier, 'fp' => $chemin, 'tf' => $typeFichier, 'tl' => $taille, 'ai' => $_SESSION['user_id'], 'desc' => $desc]
                );

                log_activity('documents.upload', 'Téléversement du document ' . $nomFichier);
                set_flash('success', 'Document téléversé avec succès.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Téléverser un document</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label required">Fichier</label>
                    <input type="file" name="fichier" class="form-control" required
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt">
                    <div class="form-text">Taille maximale : 10 Mo. Types autorisés : PDF, Word, Excel, PowerPoint, images, texte.</div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Description du document (optionnel)"><?= e($desc) ?></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>Téléverser</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
