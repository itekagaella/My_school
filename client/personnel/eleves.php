<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['personnel']);
$user = current_user();
$page_title = 'Élèves & classes';
$active_menu = 'eleves';

$search = clean_input(get('search', ''));
$classe_id = (int)get('classe_id', 0);

$classes = prepareQuery(
    'SELECT c.*, COUNT(e.id) AS effectif
     FROM classes c
     LEFT JOIN eleves e ON e.classe_id = c.id AND e.statut = \'actif\'
     GROUP BY c.id
     ORDER BY ' . classes_order_sql('c.nom_classe')
)->fetchAll();

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}
if ($classe_id > 0) {
    $where[] = 'e.classe_id = :cl';
    $params['cl'] = $classe_id;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$eleves = prepareQuery(
    "SELECT e.*, c.nom_classe, c.niveau
     FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     $whereSql
     ORDER BY " . classes_order_sql('c.nom_classe') . ", e.nom, e.prenom",
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Élèves & classes</h4>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-users me-2"></i>Élèves (<?= count($eleves) ?>)</span>
    </div>
    <div class="card-body">
        <form method="get" action="" class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
                <label class="form-label small text-muted">Rechercher</label>
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Nom, prénom ou matricule...">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Classe</label>
                <select name="classe_id" class="form-select">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classe_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nom_classe']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Matricule</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Sexe</th>
                        <th>Classe</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eleves)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun élève trouvé.</td></tr>
                    <?php else: foreach ($eleves as $el):
                        $badge_statut = $el['statut'] === 'actif' ? 'success' : ($el['statut'] === 'archive' ? 'warning' : 'secondary');
                    ?>
                        <tr>
                            <td class="text-nowrap small"><?= e($el['matricule']) ?></td>
                            <td><?= e($el['nom']) ?></td>
                            <td><?= e($el['prenom']) ?></td>
                            <td><i class="fa-solid <?= $el['sexe'] === 'F' ? 'fa-venus text-danger' : 'fa-mars text-primary' ?>"></i></td>
                            <td><span class="badge bg-light text-dark"><?= e($el['nom_classe'] ?? '-') ?></span></td>
                            <td><span class="badge bg-<?= $badge_statut ?>"><?= e($el['statut']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="fa-solid fa-school me-2"></i>Classes (<?= count($classes) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Section</th>
                        <th>Année scolaire</th>
                        <th>Effectif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune classe enregistrée.</td></tr>
                    <?php else: foreach ($classes as $c): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($c['nom_classe']) ?></td>
                            <td><?= e($c['niveau']) ?></td>
                            <td><?= e($c['section'] ?? '-') ?></td>
                            <td class="small text-muted"><?= e($c['annee_scolaire']) ?></td>
                            <td><span class="badge bg-primary"><?= (int)$c['effectif'] ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
