<?php
/**
 * Supervision des notes (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('notes.view');

$page_title = 'Supervision des notes';
$active_menu = 'notes';

$classe_id = (int)get('classe_id', 0);
$matiere_id = (int)get('matiere_id', 0);
$classe_id_filter = $classe_id;

$classes = prepareQuery('SELECT * FROM classes ORDER BY nom_classe')->fetchAll();
$matieres = prepareQuery('SELECT * FROM matieres ORDER BY nom_matiere')->fetchAll();

$where = [];
$params = [];
if ($classe_id > 0) {
    $where[] = 'e.classe_id = :cl';
    $params['cl'] = $classe_id;
}
if ($matiere_id > 0) {
    $where[] = 'n.matiere_id = :m';
    $params['m'] = $matiere_id;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$notes = prepareQuery(
    "SELECT n.*, e.nom, e.prenom, e.matricule, m.nom_matiere, c.nom_classe, p.nom AS prof_nom, p.prenom AS prof_prenom
     FROM notes n
     JOIN eleves e ON e.id = n.eleve_id
     JOIN matieres m ON m.id = n.matiere_id
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN profs p ON p.id = n.prof_id
     $whereSql
     ORDER BY n.date_evaluation DESC LIMIT 300",
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-file-pen me-2"></i>Supervision des notes</div>
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-4">
                <select name="classe_id" class="form-select">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classe_id==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select name="matiere_id" class="form-select">
                    <option value="0">Toutes les matières</option>
                    <?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>" <?= $matiere_id==$m['id']?'selected':'' ?>><?= e($m['nom_matiere']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Élève</th><th>Classe</th><th>Matière</th><th>Note</th><th>Type</th><th>Date</th><th>Prof</th></tr></thead>
                <tbody>
                    <?php if (empty($notes)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune note trouvée.</td></tr>
                    <?php else: foreach ($notes as $n): ?>
                        <tr>
                            <td><?= e($n['prenom'] . ' ' . $n['nom']) ?><br><small class="text-muted"><?= e($n['matricule']) ?></small></td>
                            <td class="small"><?= e($n['nom_classe'] ?? '-') ?></td>
                            <td class="small"><?= e($n['nom_matiere']) ?></td>
                            <td><span class="badge bg-<?= $n['note']>=10?'success':'danger' ?>"><?= number_format($n['note'],2) ?></span></td>
                            <td class="small"><?= e($n['type_evaluation']) ?></td>
                            <td class="small"><?= date('d/m/Y', strtotime($n['date_evaluation'])) ?></td>
                            <td class="small"><?= e($n['prof_prenom'] ?? '') ?> <?= e($n['prof_nom'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
