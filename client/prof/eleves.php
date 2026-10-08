<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['prof']);
$user = current_user();
$page_title = 'Mes élèves';
$active_menu = 'eleves';

$prof = prepareQuery(
    'SELECT * FROM profs WHERE user_id = :uid',
    ['uid' => $user['id']]
)->fetch();

if (!$prof) {
    set_flash('error', 'Profil professeur introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$prof_id = $prof['id'];

$classes = prepareQuery(
    "SELECT DISTINCT c.id, c.nom_classe, c.niveau, " . classes_order_sql('c.nom_classe') . " AS ordre_classe
     FROM matieres m
     JOIN classes c ON c.id = m.classe_id
     WHERE m.prof_id = :pid
     ORDER BY ordre_classe",
    ['pid' => $prof_id]
)->fetchAll();

$search = clean_input(get('search', ''));
$classe_id = (int)get('classe_id', 0);

$where = ['e.classe_id IN (SELECT classe_id FROM matieres WHERE prof_id = :pid)'];
$params = ['pid' => $prof_id];
if ($classe_id > 0) {
    $where[] = 'e.classe_id = :cl';
    $params['cl'] = $classe_id;
}
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}

$eleves = prepareQuery(
    'SELECT e.*, c.nom_classe
     FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY ' . classes_order_sql('c.nom_classe') . ', e.nom, e.prenom',
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-users me-2 text-primary"></i>Mes élèves</h4>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-user-graduate me-2"></i>Élèves de mes classes (<?= count($eleves) ?>)</span>
        <span class="badge bg-primary"><?= count($classes) ?> classe(s)</span>
    </div>
    <div class="card-body">
        <form method="get" action="" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-muted">Rechercher</label>
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Nom, prénom ou matricule...">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Classe</label>
                <select name="classe_id" class="form-select">
                    <option value="0">Toutes mes classes</option>
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
                        <th>Classe</th>
                        <th>Moyenne générale</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eleves)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun élève trouvé.</td></tr>
                    <?php else: foreach ($eleves as $el):
                        $r = prepareQuery('SELECT sp_calculer_moyenne(:eid) AS moy', ['eid' => $el['id']])->fetch();
                        $moy = (float)($r['moy'] ?? 0);
                        $badge_note = $moy >= 14 ? 'success' : ($moy >= 10 ? 'warning' : 'danger');
                        $badge_statut = $el['statut'] === 'actif' ? 'success' : ($el['statut'] === 'archive' ? 'warning' : 'secondary');
                    ?>
                        <tr>
                            <td class="text-nowrap small"><?= e($el['matricule']) ?></td>
                            <td><?= e($el['nom']) ?></td>
                            <td><?= e($el['prenom']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= e($el['nom_classe'] ?? '-') ?></span></td>
                            <td><span class="badge bg-<?= $badge_note ?>"><?= number_format($moy, 2) ?>/20</span></td>
                            <td><span class="badge bg-<?= $badge_statut ?>"><?= e($el['statut']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
