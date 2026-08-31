<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('personnel.view');

$page_title = 'Gestion du personnel';
$active_menu = 'personnel';

$search = clean_input(get('search', ''));
$fonction = clean_input(get('fonction', ''));
$statut = get('statut', '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(p.nom) LIKE :s OR LOWER(p.prenom) LIKE :s OR LOWER(p.matricule) LIKE :s OR LOWER(p.email) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($fonction !== '') {
    $where[] = 'LOWER(p.fonction) = :f';
    $params['f'] = strtolower($fonction);
}
if ($statut !== '') {
    $where[] = 'p.statut = :st';
    $params['st'] = $statut;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$personnel = prepareQuery(
    "SELECT p.* FROM personnel p $whereSql ORDER BY p.nom, p.prenom",
    $params
)->fetchAll();

$canEdit = has_permission('personnel.edit');

$fonctions = prepareQuery("SELECT DISTINCT fonction FROM personnel WHERE fonction != '' ORDER BY fonction")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><?= count($personnel) ?> membre(s)</h4>
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Nouveau membre</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher (nom, prénom, matricule, email)...">
            </div>
            <div class="col-md-3">
                <select name="fonction" class="form-select">
                    <option value="">Toutes les fonctions</option>
                    <?php foreach ($fonctions as $f): ?>
                        <option value="<?= e($f['fonction']) ?>" <?= $fonction === $f['fonction'] ? 'selected' : '' ?>><?= e($f['fonction']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                    <option value="archive" <?= $statut === 'archive' ? 'selected' : '' ?>>Archivé</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom & Prénom</th>
                        <th>Fonction</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($personnel)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun membre du personnel trouvé.</td></tr>
                    <?php else: foreach ($personnel as $p): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e($p['matricule']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width:36px;height:36px;font-size:13px;"><?= strtoupper(mb_substr($p['prenom'], 0, 1)) ?></div>
                                    <div>
                                        <strong><?= e($p['prenom'] . ' ' . $p['nom']) ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= e($p['fonction']) ?></span></td>
                            <td class="small"><?= e($p['tel']) ?></td>
                            <td class="small"><?= e($p['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $p['statut'] === 'actif' ? 'success' : ($p['statut'] === 'inactif' ? 'secondary' : 'warning') ?>"><?= e($p['statut']) ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Voir"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($canEdit): ?>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="return confirmDelete('Supprimer ce membre du personnel ?')"><i class="fa-solid fa-trash"></i></a>
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
