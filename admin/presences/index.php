<?php
/**
 * Supervision des présences (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('presences.view');

$page_title = 'Supervision des présences';
$active_menu = 'presences';

$date = get('date', date('Y-m-d'));
$classe_id = (int)get('classe_id', 0);

$classes = prepareQuery('SELECT * FROM classes ORDER BY nom_classe')->fetchAll();

$where = [];
$params = ['date' => $date];
if ($classe_id > 0) {
    $where[] = 'e.classe_id = :cl';
    $params['cl'] = $classe_id;
}
$whereSql = $where ? 'AND ' . implode(' AND ', $where) : '';

// Résumé du jour
$resume = prepareQuery(
    "SELECT p.statut, COUNT(*) AS total
     FROM presences p JOIN eleves e ON e.id = p.eleve_id
     WHERE p.date_presence = :date $whereSql
     GROUP BY p.statut",
    $params
)->fetchAll();

$presences = prepareQuery(
    "SELECT p.*, e.nom, e.prenom, e.matricule, c.nom_classe, pv.nom AS prof_nom, pv.prenom AS prof_prenom
     FROM presences p
     JOIN eleves e ON e.id = p.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN profs pv ON pv.id = p.prof_id
     WHERE p.date_presence = :date $whereSql
     ORDER BY e.nom, e.prenom",
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<form method="get" action="" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Classe</label>
        <select name="classe_id" class="form-select">
            <option value="0">Toutes</option>
            <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classe_id==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Voir</button>
    </div>
</form>

<!-- Résumé statistique -->
<div class="row g-3 mb-3">
    <?php
    $counters = ['present'=>'Présents','absent'=>'Absents','retard'=>'Retards','excuse'=>'Excusés'];
    $colors = ['present'=>'success','absent'=>'danger','retard'=>'warning','excuse'=>'info'];
    foreach ($counters as $k=>$label):
        $total = 0;
        foreach ($resume as $r) if ($r['statut']===$k) $total = $r['total'];
    ?>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="h2 text-<?= $colors[$k] ?> mb-0"><?= $total ?></div>
                <div class="text-muted small"><?= $label ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-clipboard-check me-2"></i>Détail des présences</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Élève</th><th>Classe</th><th>Statut</th><th>Heure</th><th>Motif</th><th>Marqué par</th></tr></thead>
            <tbody>
                <?php if (empty($presences)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Aucune présence enregistrée pour cette date.</td></tr>
                <?php else: foreach ($presences as $p): ?>
                    <tr>
                        <td><?= e($p['prenom'] . ' ' . $p['nom']) ?><br><small class="text-muted"><?= e($p['matricule']) ?></small></td>
                        <td class="small"><?= e($p['nom_classe'] ?? '-') ?></td>
                        <td>
                            <?php
                            $labels = ['present'=>'Présent','absent'=>'Absent','retard'=>'Retard','excuse'=>'Excusé'];
                            $colors = ['present'=>'success','absent'=>'danger','retard'=>'warning','excuse'=>'info'];
                            echo '<span class="badge bg-' . $colors[$p['statut']] . '">' . $labels[$p['statut']] . '</span>';
                            ?>
                        </td>
                        <td class="small"><?= $p['heure_arrivee'] ?? '-' ?></td>
                        <td class="small text-muted"><?= e($p['motif'] ?? '-') ?></td>
                        <td class="small"><?= e($p['prof_prenom'] ?? '') ?> <?= e($p['prof_nom'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
