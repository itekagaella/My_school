<?php
/**
 * Espace Surveillant — Incidents signalés
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['surveillant']);
$user = current_user();
$page_title = 'Incidents signalés';
$active_menu = 'incidents';

$pers = prepareQuery('SELECT * FROM personnel WHERE user_id = :u', ['u' => $user['id']])->fetch();
if (!$pers) {
    set_flash('error', 'Profil personnel introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$gravites = gravites_incident();
$statuts = statuts_incident();
$typesCourants = [
    'Absence injustifiée', 'Retard répété', 'Indiscipline en classe', 'Non-respect du règlement',
    'Bagarre', 'Insolence envers le personnel', 'Tricherie', 'Dégradation de matériel', 'Autre',
];

$error = '';
$d = ['eleve_id'=>0,'date_incident'=>date('Y-m-d'),'type_incident'=>'','gravite'=>'moyenne','description'=>'','lieu'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée.';
    } else {
        $d['eleve_id'] = (int)($_POST['eleve_id'] ?? 0);
        $d['date_incident'] = clean_input($_POST['date_incident'] ?? date('Y-m-d'));
        $d['type_incident'] = clean_input($_POST['type_incident'] ?? '');
        $d['gravite'] = $_POST['gravite'] ?? 'moyenne';
        $d['description'] = clean_input($_POST['description'] ?? '');
        $d['lieu'] = clean_input($_POST['lieu'] ?? '');

        if ($d['eleve_id'] <= 0) {
            $error = 'Sélectionnez un élève.';
        } elseif ($d['type_incident'] === '') {
            $error = 'Le type d\'incident est obligatoire.';
        } elseif (!array_key_exists($d['gravite'], $gravites)) {
            $error = 'Gravité invalide.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date_incident'])) {
            $error = 'Date invalide.';
        } else {
            prepareQuery(
                'INSERT INTO incidents_discipline (eleve_id, date_incident, type_incident, gravite, description, lieu, signale_par, statut)
                 VALUES (:e, :dt, :t, :g, :desc, :lieu, :uid, \'ouvert\')',
                ['e'=>$d['eleve_id'],'dt'=>$d['date_incident'],'t'=>$d['type_incident'],'g'=>$d['gravite'],
                 'desc'=>($d['description'] ?: null),'lieu'=>($d['lieu'] ?: null),'uid'=>$user['id']]
            );
            log_activity('surveillance.incident', 'Signalement: ' . $d['type_incident'] . ' (élève #' . $d['eleve_id'] . ')');
            set_flash('success', 'Incident signalé. La discipline en sera informée.');
            header('Location: incidents.php');
            exit;
        }
    }
}

$mess = prepareQuery(
    "SELECT i.*, e.nom, e.prenom, e.matricule, " . classe_label_sql('c') . " AS nom_classe
     FROM incidents_discipline i
     JOIN eleves e ON e.id = i.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     WHERE i.signale_par = :uid
     ORDER BY i.date_incident DESC, i.id DESC",
    ['uid' => $user['id']]
)->fetchAll();

$eleves = prepareQuery(
    "SELECT e.id, e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe
     FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
     WHERE e.statut = 'actif' ORDER BY e.nom, e.prenom"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-2 text-primary"></i>Signaler un incident</h4>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="post" action="" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label required">Élève</label>
                <select name="eleve_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($eleves as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= (int)$d['eleve_id'] === (int)$el['id'] ? 'selected' : '' ?>><?= e($el['matricule'] . ' — ' . $el['prenom'] . ' ' . $el['nom'] . ($el['nom_classe'] ? ' (' . $el['nom_classe'] . ')' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label required">Type</label>
                <input list="types-incident" name="type_incident" class="form-control" value="<?= e($d['type_incident']) ?>" required>
                <datalist id="types-incident"><?php foreach ($typesCourants as $t): ?><option value="<?= e($t) ?>"></option><?php endforeach; ?></datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label required">Date</label>
                <input type="date" name="date_incident" class="form-control" value="<?= e($d['date_incident']) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label required">Gravité</label>
                <select name="gravite" class="form-select" required>
                    <?php foreach ($gravites as $gv => $gl): ?><option value="<?= $gv ?>" <?= $d['gravite']===$gv?'selected':'' ?>><?= e($gl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-check"></i></button>
            </div>
            <div class="col-md-3"><input type="text" name="lieu" class="form-control" value="<?= e($d['lieu']) ?>" placeholder="Lieu (ex: Cour)"></div>
            <div class="col-md-9"><input type="text" name="description" class="form-control" value="<?= e($d['description']) ?>" placeholder="Description..."></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-list me-2"></i>Mes signalements (<?= count($mess) ?>)</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Élève</th><th>Type</th><th>Gravité</th><th>Statut</th></tr></thead>
            <tbody>
                <?php if (empty($mess)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Aucun signalement.</td></tr>
                <?php else: foreach ($mess as $m): ?>
                    <tr>
                        <td class="small"><?= date('d/m/Y', strtotime($m['date_incident'])) ?></td>
                        <td><?= e($m['prenom'] . ' ' . $m['nom']) ?><br><small class="text-muted"><?= e($m['nom_classe'] ?? '') ?></small></td>
                        <td class="small"><?= e($m['type_incident']) ?></td>
                        <td><?php $gc = ['faible'=>'info','moyenne'=>'warning','grave'=>'danger'][$m['gravite']] ?? 'secondary'; ?><span class="badge bg-<?= $gc ?>"><?= e($gravites[$m['gravite']] ?? $m['gravite']) ?></span></td>
                        <td><span class="badge bg-<?= $m['statut']==='ouvert'?'danger':($m['statut']==='traite'?'info':'success') ?>"><?= e($statuts[$m['statut']] ?? $m['statut']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
