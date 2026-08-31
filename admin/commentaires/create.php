<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$page_title = 'Nouveau commentaire';
$active_menu = 'commentaires';

$error = '';
$document_id = (int)get('document_id', 0);
$user_id = (int)get('user_id', 0);
$contenu = '';

$documents = prepareQuery(
    'SELECT id, nom_fichier FROM documents WHERE statut = :s ORDER BY nom_fichier',
    ['s' => 'actif']
)->fetchAll();

$utilisateurs = prepareQuery(
    'SELECT id, nom, prenom FROM utilisateurs WHERE actif = TRUE ORDER BY nom, prenom'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $document_id = (int)($_POST['document_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $contenu = trim($_POST['contenu'] ?? '');

        if ($document_id <= 0) {
            $error = 'Veuillez sélectionner un document.';
        } elseif ($user_id <= 0) {
            $error = 'Veuillez sélectionner un utilisateur.';
        } elseif ($contenu === '') {
            $error = 'Le contenu du commentaire est obligatoire.';
        } else {
            prepareQuery(
                'INSERT INTO commentaires (document_id, user_id, contenu, created_at) VALUES (:doc_id, :user_id, :contenu, NOW())',
                ['doc_id' => $document_id, 'user_id' => $user_id, 'contenu' => $contenu]
            );

            $newId = getDB()->lastInsertId();
            log_activity('commentaires.create', 'Création du commentaire ID ' . $newId . ' sur le document ID ' . $document_id);
            set_flash('success', 'Commentaire ajouté avec succès.');
            header('Location: index.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-comment-dots me-2"></i>Nouveau commentaire</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Document</label>
                    <select name="document_id" class="form-select" required>
                        <option value="">— Sélectionner un document —</option>
                        <?php foreach ($documents as $doc): ?>
                            <option value="<?= $doc['id'] ?>" <?= $document_id === (int)$doc['id'] ? 'selected' : '' ?>>
                                <?= e($doc['nom_fichier']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Utilisateur</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">— Sélectionner un utilisateur —</option>
                        <?php foreach ($utilisateurs as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_id === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label required">Contenu</label>
                    <textarea name="contenu" class="form-control" rows="5" required><?= e($contenu) ?></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Ajouter</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
