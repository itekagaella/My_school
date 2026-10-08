<?php
/**
 * Liste des élèves (Admin) - avec recherche, filtres, tri
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('eleves.view');

$page_title = 'Gestion des élèves';
$active_menu = 'eleves';

// Filtres
$search = clean_input(get('search', ''));
$classe = (int)get('classe', 0);
$sexe = get('sexe', '');
$classe_id = (int)get('classe_id', 0);

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(e.nom) LIKE :s OR LOWER(e.prenom) LIKE :s OR LOWER(e.matricule) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($classe > 0) {
    $where[] = 'e.classe_id = :cl';
    $params['cl'] = $classe;
}
if ($sexe !== '') {
    $where[] = 'e.sexe = :sex';
    $params['sex'] = $sexe;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$eleves = prepareQuery(
    "SELECT e.*, c.nom_classe
     FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
     $whereSql ORDER BY e.nom, e.prenom"
, $params)->fetchAll();

// Vérifier si l'utilisateur courant a le droit de gérer
$canEdit = has_permission('eleves.edit');

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Élèves <span class="text-muted fs-6">(<?= count($eleves) ?>)</span></h4>
    <div class="d-flex gap-2">
        <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Inscrire un élève</a>
        <a href="import.php" class="btn btn-outline-secondary"><i class="fa-solid fa-file-import me-1"></i>Importer</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Filtres -->
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher (nom, prénom, matricule)...">
            </div>
            <div class="col-md-3">
                <select name="classe" class="form-select">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classe===$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="sexe" class="form-select">
                    <option value="">Sexe</option>
                    <option value="M" <?= $sexe==='M'?'selected':'' ?>>Masculin</option>
                    <option value="F" <?= $sexe==='F'?'selected':'' ?>>Féminin</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Sexe</th>
                        <th>Date naissance</th>
                        <th>Parent</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eleves)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun élève trouvé.</td></tr>
                    <?php else: foreach ($eleves as $el): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e($el['matricule']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($el['photo'] && file_exists(ROOT_PATH . $el['photo'])): ?>
                                        <img src="<?= BASE_URL . e($el['photo']) ?>" class="user-avatar" style="width:36px;height:36px;object-fit:cover;" alt="">
                                    <?php else: ?>
                                        <div class="user-avatar" style="width:36px;height:36px;font-size:13px;"><?= strtoupper(mb_substr($el['prenom'],0,1)) ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= e($el['prenom'] . ' ' . $el['nom']) ?></strong>
                                        <?php if ($el['statut'] !== 'actif'): ?>
                                            <br><span class="badge bg-secondary"><?= e($el['statut']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= e($el['nom_classe'] ?? '-') ?></span></td>
                            <td><?= $el['sexe'] === 'M' ? '<i class="fa-solid fa-mars text-primary"></i>' : '<i class="fa-solid fa-venus text-danger"></i>' ?></td>
                            <td class="small"><?= date('d/m/Y', strtotime($el['date_naissance'])) ?></td>
                            <td class="small"><?= e($el['parent_nom'] ?? '-') ?><br>
                                <small class="text-muted"><?= e($el['parent_tel'] ?? '') ?></small></td>
                            <td class="text-end text-nowrap">
                                <a href="view.php?id=<?= $el['id'] ?>" class="btn btn-sm btn-outline-info" title="Voir"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($canEdit): ?>
                                <a href="edit.php?id=<?= $el['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                <form method="post" action="archive.php?id=<?= $el['id'] ?>&action=archive" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-warning" title="Archiver" onclick="return confirmDelete('Archiver cet élève ?')"><i class="fa-solid fa-box-archive"></i></button>
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
