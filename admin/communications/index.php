<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('communications.view');

$page_title = 'Gestion des communications';
$active_menu = 'communications';

$search = clean_input(get('search', ''));
$type_filter = get('type', '');
$statut_filter = get('statut', '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(c.titre) LIKE :s OR LOWER(c.contenu) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($type_filter !== '' && in_array($type_filter, ['annonce', 'alerte', 'info'])) {
    $where[] = 'c.type = :type';
    $params['type'] = $type_filter;
}
if ($statut_filter !== '' && in_array($statut_filter, ['brouillon', 'publie', 'archive'])) {
    $where[] = 'c.statut = :statut';
    $params['statut'] = $statut_filter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$communications = prepareQuery(
    "SELECT c.*, u.nom, u.prenom
     FROM communications c LEFT JOIN utilisateurs u ON u.id = c.auteur_id
     $whereSql ORDER BY c.date_publication DESC, c.id DESC",
    $params
)->fetchAll();

$canEdit = has_permission('communications.publish');
$canCreate = has_permission('communications.create');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><?= count($communications) ?> communication(s)</h4>
    <?php if ($canCreate): ?>
        <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Nouvelle communication</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher...">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">Tous les types</option>
                    <option value="annonce" <?= $type_filter==='annonce'?'selected':'' ?>>Annonce</option>
                    <option value="alerte" <?= $type_filter==='alerte'?'selected':'' ?>>Alerte</option>
                    <option value="info" <?= $type_filter==='info'?'selected':'' ?>>Info</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="brouillon" <?= $statut_filter==='brouillon'?'selected':'' ?>>Brouillon</option>
                    <option value="publie" <?= $statut_filter==='publie'?'selected':'' ?>>Publié</option>
                    <option value="archive" <?= $statut_filter==='archive'?'selected':'' ?>>Archivé</option>
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
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Destinataires</th>
                        <th>Auteur</th>
                        <th>Publication</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($communications)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune communication trouvée.</td></tr>
                    <?php else: foreach ($communications as $comm): ?>
                        <tr>
                            <td><strong><?= e($comm['titre']) ?></strong></td>
                            <td>
                                <?php
                                $typeBadges = ['annonce'=>'bg-primary','alerte'=>'bg-danger','info'=>'bg-info'];
                                $bc = $typeBadges[$comm['type']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $bc ?>"><?= e(ucfirst($comm['type'])) ?></span>
                            </td>
                            <td class="small"><?= e($comm['destinataires']) ?></td>
                            <td class="small"><?= e($comm['prenom'] . ' ' . $comm['nom']) ?></td>
                            <td class="small text-nowrap"><?= $comm['date_publication'] ? date('d/m/Y H:i', strtotime($comm['date_publication'])) : '-' ?></td>
                            <td>
                                <?php
                                $statutBadges = ['brouillon'=>'bg-secondary','publie'=>'bg-success','archive'=>'bg-warning'];
                                $sc = $statutBadges[$comm['statut']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $sc ?>"><?= e(ucfirst($comm['statut'])) ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="view.php?id=<?= $comm['id'] ?>" class="btn btn-sm btn-outline-info" title="Voir"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($canEdit): ?>
                                    <a href="edit.php?id=<?= $comm['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                    <?php if ($comm['statut'] === 'brouillon'): ?>
                                        <a href="publish.php?id=<?= $comm['id'] ?>" class="btn btn-sm btn-outline-success" title="Publier" onclick="return confirm('Publier cette communication ?')"><i class="fa-solid fa-paper-plane"></i></a>
                                    <?php endif; ?>
                                    <a href="delete.php?id=<?= $comm['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="return confirm('Supprimer cette communication ?')"><i class="fa-solid fa-trash"></i></a>
                                <?php endif; ?>
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
