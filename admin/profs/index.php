<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('profs.view');

$page_title = 'Gestion des professeurs';
$active_menu = 'profs';

$search = clean_input(get('search', ''));
$specialite = clean_input(get('specialite', ''));
$statut = get('statut', '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(p.nom) LIKE :s OR LOWER(p.prenom) LIKE :s OR LOWER(p.matricule) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($specialite !== '') {
    $where[] = 'LOWER(p.specialite) LIKE :sp';
    $params['sp'] = '%' . strtolower($specialite) . '%';
}
if ($statut !== '') {
    $where[] = 'p.statut = :st';
    $params['st'] = $statut;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$profs = prepareQuery(
    "SELECT p.* FROM profs p $whereSql ORDER BY p.nom, p.prenom"
, $params)->fetchAll();

$canEdit = has_permission('profs.edit');

$specialites = prepareQuery('SELECT DISTINCT specialite FROM profs WHERE specialite IS NOT NULL AND specialite != \'\' ORDER BY specialite')->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><?= count($profs) ?> professeur(s)</h4>
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Nouveau professeur</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher (nom, prénom, matricule)...">
            </div>
            <div class="col-md-3">
                <input type="text" name="specialite" value="<?= e($specialite) ?>" class="form-control" placeholder="Spécialité...">
            </div>
            <div class="col-md-3">
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?= $statut==='actif'?'selected':'' ?>>Actif</option>
                    <option value="inactif" <?= $statut==='inactif'?'selected':'' ?>>Inactif</option>
                    <option value="archive" <?= $statut==='archive'?'selected':'' ?>>Archivé</option>
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
                        <th>Matricule</th>
                        <th>Professeur</th>
                        <th>Spécialité</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun professeur trouvé.</td></tr>
                    <?php else: foreach ($profs as $p): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e($p['matricule']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($p['photo'] && file_exists(ROOT_PATH . $p['photo'])): ?>
                                        <img src="<?= BASE_URL . e($p['photo']) ?>" class="user-avatar" style="width:36px;height:36px;object-fit:cover;" alt="">
                                    <?php else: ?>
                                        <div class="user-avatar" style="width:36px;height:36px;font-size:13px;"><?= strtoupper(mb_substr($p['prenom'],0,1)) ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= e($p['prenom'] . ' ' . $p['nom']) ?></strong>
                                        <?php if ($p['statut'] !== 'actif'): ?>
                                            <br><span class="badge bg-secondary"><?= e($p['statut']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= e($p['specialite'] ?? '-') ?></span></td>
                            <td class="small"><?= e($p['tel'] ?? '-') ?></td>
                            <td class="small"><?= e($p['email'] ?? '-') ?></td>
                            <td>
                                <?php if ($p['statut'] === 'actif'): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php elseif ($p['statut'] === 'archive'): ?>
                                    <span class="badge bg-warning">Archivé</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Voir"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($canEdit): ?>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                <form method="post" action="archive.php?id=<?= $p['id'] ?>&action=archive" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-warning" title="Archiver" onclick="return confirmDelete('Archiver ce professeur ?')"><i class="fa-solid fa-box-archive"></i></button>
                                </form>
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
