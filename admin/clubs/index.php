<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('clubs.view');

$page_title = 'Clubs scolaires';
$active_menu = 'clubs';

$search = clean_input(get('search', ''));
$statut = get('statut', '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(c.nom_club) LIKE :s OR LOWER(c.description) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($statut !== '' && in_array($statut, ['actif', 'inactif'])) {
    $where[] = 'c.statut = :st';
    $params['st'] = $statut;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$clubs = prepareQuery(
    "SELECT c.*, p.nom AS prof_nom, p.prenom AS prof_prenom,
            (SELECT COUNT(*) FROM club_membres cm WHERE cm.club_id = c.id) AS nb_membres
     FROM clubs c LEFT JOIN profs p ON p.id = c.prof_responsable_id
     $whereSql ORDER BY c.nom_club"
, $params)->fetchAll();

$canEdit = has_permission('clubs.edit');
$canDelete = has_permission('clubs.delete');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-people-group me-2 text-primary"></i>Clubs <span class="text-muted fs-6">(<?= count($clubs) ?>)</span></h4>
    <?php if (has_permission('clubs.create')): ?>
        <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Nouveau club</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher un club...">
            </div>
            <div class="col-md-3">
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?= $statut==='actif'?'selected':'' ?>>Actif</option>
                    <option value="inactif" <?= $statut==='inactif'?'selected':'' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Club</th>
                        <th>Description</th>
                        <th>Prof responsable</th>
                        <th>Date création</th>
                        <th class="text-center">Membres</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clubs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun club trouvé.</td></tr>
                    <?php else: foreach ($clubs as $cl): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($cl['logo'] && file_exists(ROOT_PATH . $cl['logo'])): ?>
                                        <img src="<?= BASE_URL . e($cl['logo']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:50%;" alt="">
                                    <?php else: ?>
                                        <div class="user-avatar" style="width:36px;height:36px;font-size:13px;"><i class="fa-solid fa-people-group"></i></div>
                                    <?php endif; ?>
                                    <strong><?= e($cl['nom_club']) ?></strong>
                                </div>
                            </td>
                            <td class="small text-muted" style="max-width:250px;"><?= e(mb_strimwidth($cl['description'] ?? '', 0, 80, '...')) ?></td>
                            <td><?= e(($cl['prof_prenom'] ?? '') . ' ' . ($cl['prof_nom'] ?? '-')) ?></td>
                            <td class="small"><?= date('d/m/Y', strtotime($cl['date_creation'])) ?></td>
                            <td class="text-center"><span class="badge bg-info"><?= (int)$cl['nb_membres'] ?></span></td>
                            <td>
                                <?php if ($cl['statut'] === 'actif'): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="view.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-info" title="Voir"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($canEdit): ?>
                                    <a href="edit.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <a href="delete.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="return confirmDelete('Supprimer ce club ?')"><i class="fa-solid fa-trash"></i></a>
                                <?php endif; ?>
                                <a href="manage.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-success" title="Activités / Membres"><i class="fa-solid fa-gear"></i></a>
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
