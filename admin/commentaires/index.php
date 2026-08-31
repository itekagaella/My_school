<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$page_title = 'Gestion des commentaires';
$active_menu = 'commentaires';

$search = clean_input(get('search', ''));
$doc_filter = (int)get('document_id', 0);

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(c.contenu) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($doc_filter > 0) {
    $where[] = 'c.document_id = :doc_id';
    $params['doc_id'] = $doc_filter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$commentaires = prepareQuery(
    "SELECT c.*, d.nom_fichier, u.prenom AS user_prenom, u.nom AS user_nom
     FROM commentaires c
     LEFT JOIN documents d ON d.id = c.document_id
     LEFT JOIN utilisateurs u ON u.id = c.user_id
     $whereSql ORDER BY c.created_at DESC",
    $params
)->fetchAll();

$documents = prepareQuery(
    'SELECT id, nom_fichier FROM documents WHERE statut = :s ORDER BY nom_fichier',
    ['s' => 'actif']
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><?= count($commentaires) ?> commentaire(s)</h4>
    <div class="d-flex gap-2">
        <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Ajouter</a>
        <a href="../documents/index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-folder-open me-1"></i>Documents</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher dans le contenu...">
            </div>
            <div class="col-md-5">
                <select name="document_id" class="form-select">
                    <option value="">Tous les documents</option>
                    <?php foreach ($documents as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= $doc_filter === (int)$doc['id'] ? 'selected' : '' ?>>
                            <?= e($doc['nom_fichier']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Utilisateur</th>
                        <th>Contenu</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($commentaires)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun commentaire trouvé.</td></tr>
                    <?php else: foreach ($commentaires as $c): ?>
                        <tr>
                            <td class="small">
                                <i class="fa-solid fa-file-lines text-primary me-1"></i>
                                <?= e($c['nom_fichier'] ?? '—') ?>
                            </td>
                            <td class="small"><?= e(($c['user_prenom'] ?? '') . ' ' . ($c['user_nom'] ?? '')) ?></td>
                            <td>
                                <?= e(mb_strimwidth($c['contenu'], 0, 80, '...')) ?>
                            </td>
                            <td class="small text-nowrap"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                            <td class="text-end">
                                <a href="delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer"
                                   onclick="return confirm('Supprimer ce commentaire ?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
