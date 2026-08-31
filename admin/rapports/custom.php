<?php
/**
 * Rapport personnalisé (sélection de colonnes et filtres avancés)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('reports.view');

$page_title = 'Rapport personnalisé';
$active_menu = 'rapports';

$type = get('type', 'eleves');
$colonnes = get('colonnes', []);
$classe_id = (int)get('classe_id', 0);

$colonnes_dispo = [
    'eleves' => ['matricule'=>'Matricule','nom'=>'Nom','prenom'=>'Prénom','sexe'=>'Sexe','date_naissance'=>'Date naissance','adresse'=>'Adresse','tel'=>'Tél','email'=>'Email','classe'=>'Classe'],
    'profs' => ['matricule'=>'Matricule','nom'=>'Nom','prenom'=>'Prénom','specialite'=>'Spécialité','tel'=>'Tél','email'=>'Email','statut'=>'Statut'],
    'notes' => ['matricule'=>'Matricule','eleve'=>'Élève','matiere'=>'Matière','note'=>'Note','type'=>'Type','date'=>'Date','classe'=>'Classe'],
];

$classes = prepareQuery('SELECT * FROM classes ORDER BY nom_classe')->fetchAll();

$result = null;
$resultHeaders = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $type = clean_input($_POST['type'] ?? 'eleves');
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $selColonnes = $_POST['colonnes'] ?? [];

    if (!empty($selColonnes)) {
        $colMap = $colonnes_dispo[$type] ?? [];
        $resultHeaders = [];
        $selects = [];
        foreach ($selColonnes as $col) {
            if (!isset($colMap[$col])) continue;
            $resultHeaders[] = $colMap[$col];
            if ($type === 'eleves') {
                $selects[] = $col === 'classe' ? 'c.nom_classe' : 'e.' . $col;
            } elseif ($type === 'profs') {
                $selects[] = 'p.' . $col;
            } elseif ($type === 'notes') {
                $mapN = ['matricule'=>'e.matricule','eleve'=>"(e.prenom||' '||e.nom)",'matiere'=>'m.nom_matiere','note'=>'n.note','type'=>'n.type_evaluation','date'=>'n.date_evaluation','classe'=>'c.nom_classe'];
                if (isset($mapN[$col])) $selects[] = $mapN[$col];
            }
        }
        if ($selects) {
            if ($type === 'eleves') {
                $w = $classe_id>0 ? 'WHERE e.classe_id=:cl' : '';
                $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
                $result = prepareQuery('SELECT '.implode(',',$selects).' FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id '.$w.' ORDER BY e.nom', $p)->fetchAll();
            } elseif ($type === 'profs') {
                $result = prepareQuery('SELECT '.implode(',',$selects).' FROM profs p WHERE p.statut=\'actif\' ORDER BY p.nom')->fetchAll();
            } elseif ($type === 'notes') {
                $w = $classe_id>0 ? 'WHERE c.id=:cl' : '';
                $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
                $result = prepareQuery('SELECT '.implode(',',$selects).' FROM notes n JOIN eleves e ON e.id=n.eleve_id JOIN matieres m ON m.id=n.matiere_id JOIN classes c ON c.id=e.classe_id '.$w.' ORDER BY e.nom', $p)->fetchAll();
            }
            log_activity('reports.custom', 'Rapport personnalisé ' . $type);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-sliders me-2"></i>Paramètres du rapport</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Source</label>
                        <select name="type" id="type_source" class="form-select" onchange="this.form.submit()">
                            <option value="eleves" <?= $type==='eleves'?'selected':'' ?>>Élèves</option>
                            <option value="profs" <?= $type==='profs'?'selected':'' ?>>Professeurs</option>
                            <option value="notes" <?= $type==='notes'?'selected':'' ?>>Notes</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Classe</label>
                        <select name="classe_id" class="form-select">
                            <option value="0">Toutes</option>
                            <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classe_id==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Colonnes à inclure</label>
                        <?php foreach (($colonnes_dispo[$type] ?? []) as $key=>$label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="colonnes[]" value="<?= $key ?>" id="col_<?= $key ?>" <?= in_array($key, (array)($_POST['colonnes'] ?? [])) ? 'checked':'' ?>>
                                <label class="form-check-label" for="col_<?= $key ?>"><?= $label ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-play me-1"></i>Générer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-table me-2"></i>Aperçu du rapport</div>
            <div class="card-body">
                <?php if ($result !== null && !empty($resultHeaders)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead><tr><?php foreach ($resultHeaders as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <?php if (empty($result)): ?>
                                    <tr><td colspan="<?= count($resultHeaders) ?>" class="text-center text-muted">Aucun résultat.</td></tr>
                                <?php else: foreach ($result as $row): ?>
                                    <tr><?php foreach (array_values($row) as $v): ?><td><?= e(is_null($v)?'':$v) ?></td><?php endforeach; ?></tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        <a href="excel.php?type=custom" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel me-1"></i>Exporter</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-5">Sélectionnez les colonnes puis cliquez sur « Générer ».</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
