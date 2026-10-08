<?php
/**
 * Statistiques et indicateurs (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('reports.view');

$page_title = 'Statistiques';
$active_menu = 'rapports';

// Effectifs par classe
$effectifs_classes = prepareQuery(
    "SELECT c.nom_classe, COUNT(e.id) AS nb
     FROM classes c LEFT JOIN eleves e ON e.classe_id=c.id
     GROUP BY c.id, c.nom_classe ORDER BY " . classes_order_sql('c.nom_classe'))->fetchAll();

// Répartition par sexe
$repartition_sexe = prepareQuery(
    "SELECT sexe, COUNT(*) AS nb FROM eleves GROUP BY sexe")->fetchAll();

// Répartition profs par spécialité
$repartition_profs = prepareQuery(
    "SELECT specialite, COUNT(*) AS nb FROM profs WHERE statut='actif' GROUP BY specialite ORDER BY nb DESC")->fetchAll();

// Moyenne générale par classe
$moyennes_classes = [];
$classes = prepareQuery('SELECT id, nom_classe FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
foreach ($classes as $cl) {
    $moy = prepareQuery(
        "SELECT COALESCE(AVG(n.note),0) AS moy
         FROM notes n JOIN eleves e ON e.id=n.eleve_id
         WHERE e.classe_id=:id", ['id'=>$cl['id']])->fetch()['moy'] ?? 0;
    $moyennes_classes[] = ['nom_classe'=>$cl['nom_classe'], 'moyenne'=>round($moy, 2)];
}

// Notes mensuelles / types d'évaluation
$types_eval = prepareQuery(
    "SELECT type_evaluation, COUNT(*) AS nb FROM notes GROUP BY type_evaluation ORDER BY nb DESC")->fetchAll();

// Présences globales
$presences_stat = prepareQuery(
    "SELECT statut, COUNT(*) AS nb FROM presences GROUP BY statut")->fetchAll();

// Communic/membres
try { $nb_clubs = prepareQuery('SELECT COUNT(*) AS nb FROM clubs')->fetch()['nb'] ?? 0; } catch (Exception $e) { $nb_clubs = 0; }
try { $nb_membres = prepareQuery('SELECT COUNT(*) AS nb FROM club_membres')->fetch()['nb'] ?? 0; } catch (Exception $e) { $nb_membres = 0; }

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="display-6 fw-bold"><?= count($classes) ?></div>
        <div class="text-muted">Classes</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="display-6 fw-bold"><?= array_sum(array_column($effectifs_classes,'nb') ?: [0]) ?></div>
        <div class="text-muted">Élèves</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="display-6 fw-bold"><?= array_sum(array_column($repartition_profs,'nb') ?: [0]) ?></div>
        <div class="text-muted">Professeurs actifs</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="display-6 fw-bold"><?= $nb_clubs ?></div>
        <div class="text-muted">Clubs</div>
    </div></div></div>
</div>

<div class="row g-4">
    <div class="col-md-6"><div class="card"><div class="card-header">Élèves par classe</div>
        <div class="card-body"><canvas id="chartClasses" height="130"></canvas></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">Moyenne générale par classe</div>
        <div class="card-body"><canvas id="chartMoyennes" height="130"></canvas></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">Répartition par sexe</div>
        <div class="card-body"><canvas id="chartSexe" height="130"></canvas></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">Professeurs par spécialité</div>
        <div class="card-body"><canvas id="chartProfs" height="130"></canvas></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">Types d'évaluation</div>
        <div class="card-body"><canvas id="chartTypes" height="130"></canvas></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">Statut des présences</div>
        <div class="card-body"><canvas id="chartPresences" height="130"></canvas></div></div></div>
</div>

<script src="<?= BASE_URL ?>assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const clsLabels = <?= json_encode(array_column($effectifs_classes,'nom_classe')) ?>;
    const clsData = <?= json_encode(array_column($effectifs_classes,'nb')) ?>;
    new Chart(document.getElementById('chartClasses'), {type:'bar', data:{labels:clsLabels,datasets:[{label:'Élèves',data:clsData,backgroundColor:'#3b82f6'}]}});

    const moyLabels = <?= json_encode(array_column($moyennes_classes,'nom_classe')) ?>;
    const moyData = <?= json_encode(array_column($moyennes_classes,'moyenne')) ?>;
    new Chart(document.getElementById('chartMoyennes'), {type:'line', data:{labels:moyLabels,datasets:[{label:'Moyenne /20',data:moyData,borderColor:'#22c55e',tension:.3}]}});

    const sexeLabels = <?= json_encode(array_column($repartition_sexe,'sexe')) ?>;
    const sexeData = <?= json_encode(array_column($repartition_sexe,'nb')) ?>;
    new Chart(document.getElementById('chartSexe'), {type:'pie', data:{labels:sexeLabels,datasets:[{data:sexeData,backgroundColor:['#3b82f6','#f472b6','#a3e635']}]}});

    const profLabels = <?= json_encode(array_slice(array_column($repartition_profs,'specialite'),0,8)) ?>;
    const profData = <?= json_encode(array_slice(array_column($repartition_profs,'nb'),0,8)) ?>;
    new Chart(document.getElementById('chartProfs'), {type:'doughnut', data:{labels:profLabels,datasets:[{data:profData,backgroundColor:['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16']}]}});

    const tLabels = <?= json_encode(array_column($types_eval,'type_evaluation')) ?>;
    const tData = <?= json_encode(array_column($types_eval,'nb')) ?>;
    new Chart(document.getElementById('chartTypes'), {type:'bar', data:{labels:tLabels,datasets:[{label:'Notes',data:tData,backgroundColor:'#f59e0b'}]}});

    const pLabels = <?= json_encode(array_column($presences_stat,'statut')) ?>;
    const pData = <?= json_encode(array_column($presences_stat,'nb')) ?>;
    new Chart(document.getElementById('chartPresences'), {type:'pie', data:{labels:pLabels,datasets:[{data:pData,backgroundColor:['#22c55e','#ef4444','#f59e0b']}]}});
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
