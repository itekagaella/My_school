<?php
/**
 * Discipline — Incidents (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['discipline']);
require_permission('discipline.view');

$page_title = 'Incidents disciplinaires';
$active_menu = 'incidents';

$canManage = has_permission('discipline.manage');
$gravites = gravites_incident();
$statuts = statuts_incident();

$typesCourants = [
    'Absence injustifiée', 'Retard répété', 'Indiscipline en classe', 'Non-respect du règlement',
    'Bagarre', 'Insolence envers le personnel', 'Tricherie', 'Dégradation de matériel', 'Autre',
];

$error = '';
$d = ['eleve_id'=>0,'date_incident'=>date('Y-m-d'),'type_incident'=>'','gravite'=>'moyenne','description'=>'','lieu'=>'','statut'=>'ouvert'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            prepareQuery('DELETE FROM incidents_discipline WHERE id = :id', ['id' => $id]);
            log_activity('discipline.incident_delete', 'Suppression incident #' . $id);
            set_flash('success', 'Incident supprimé.');
            header('Location: incidents.php');
            exit;
        }
        if ($action === 'statut') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $_POST['statut'] ?? 'ouvert';
            if (array_key_exists($st, $statuts)) {
                prepareQuery('UPDATE incidents_discipline SET statut = :s, updated_at = NOW() WHERE id = :id', ['s' => $st, 'id' => $id]);
                log_activity('discipline.incident_statut', 'Incident #' . $id . ' → ' . $st);
                set_flash('success', 'Statut mis à jour.');
            }
            header('Location: incidents.php');
            exit;
        }

        // Création
        $d['eleve_id'] = (int)($_POST['eleve_id'] ?? 0);
        $d['date_incident'] = clean_input($_POST['date_incident'] ?? date('Y-m-d'));
        $d['type_incident'] = clean_input($_POST['type_incident'] ?? '');
        $d['gravite'] = $_POST['gravite'] ?? 'moyenne';
        $d['description'] = clean_input($_POST['description'] ?? '');
        $d['lieu'] = clean_input($_POST['lieu'] ?? '');
        $d['statut'] = $_POST['statut'] ?? 'ouvert';

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
                 VALUES (:e, :dt, :t, :g, :desc, :lieu, :uid, :st)',
                ['e'=>$d['eleve_id'],'dt'=>$d['date_incident'],'t'=>$d['type_incident'],'g'=>$d['gravite'],
                 'desc'=>($d['description'] ?: null),'lieu'=>($d['lieu'] ?: null),'uid'=>current_user()['id'],
                 'st'=>(array_key_exists($d['statut'], $statuts) ? $d['statut'] : 'ouvert')]
            );
            log_activity('discipline.incident_create', 'Incident ' . $d['type_incident'] . ' (élève #' . $d['eleve_id'] . ')');
            set_flash('success', 'Incident enregistré.');
            header('Location: incidents.php');
            exit;
        }
    }
}

// Filtres
$search = clean_input(get('search', ''));
$graviteF = get('gravite', '');
$statutF = get('statut', '');
$where = []; $params = [];
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s OR i.type_incident ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}
if (array_key_exists($graviteF, $gravites)) { $where[] = 'i.gravite = :g'; $params['g'] = $graviteF; }
if (array_key_exists($statutF, $statuts)) { $where[] = 'i.statut = :st'; $params['st'] = $statutF; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$incidents = prepareQuery(
    "SELECT i.*, e.nom, e.prenom, e.matricule, c.nom_classe,
            u.prenom AS sig_prenom, u.nom AS sig_nom
     FROM incidents_discipline i
     JOIN eleves e ON e.id = i.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN utilisateurs u ON u.id = i.signale_par
     $whereSql
     ORDER BY i.date_incident DESC, i.id DESC",
    $params
)->fetchAll();

$eleves = prepareQuery(
    "SELECT e.id, e.matricule, e.nom, e.prenom, c.nom_classe
     FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
     WHERE e.statut = 'actif' ORDER BY e.nom, e.prenom"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-2 text-primary"></i>Incidents <span class="text-muted fs-6">(<?= count($incidents) ?>)</span></h4>
    <div class="d-flex gap-2">
        <a href="export.php?type=incidents" class="btn btn-outline-success"><i class="fa-solid fa-file-csv me-1"></i>Exporter</a>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($canManage): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Signaler un incident</div>
    <div class="card-body">
        <form method="post" action="" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4 col-lg-3">
                <label class="form-label required">Élève</label>
                <select name="eleve_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($eleves as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= (int)$d['eleve_id'] === (int)$el['id'] ? 'selected' : '' ?>>
                            <?= e($el['matricule'] . ' — ' . $el['prenom'] . ' ' . $el['nom'] . ($el['nom_classe'] ? ' (' . $el['nom_classe'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label required">Type</label>
                <input list="types-incident" name="type_incident" class="form-control" value="<?= e($d['type_incident']) ?>" required>
                <datalist id="types-incident">
                    <?php foreach ($typesCourants as $t): ?><option value="<?= e($t) ?>"></option><?php endforeach; ?>
                </datalist>
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
            <div class="col-md-2">
                <label class="form-label">Lieu</label>
                <input type="text" name="lieu" class="form-control" value="<?= e($d['lieu']) ?>" placeholder="Ex: Cour, Salle...">
            </div>
            <div class="col-md-8">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="<?= e($d['description']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <?php foreach ($statuts as $sv => $sl): ?><option value="<?= $sv ?>" <?= $d['statut']===$sv?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-5"><input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Élève, matricule ou type..."></div>
            <div class="col-md-3">
                <select name="gravite" class="form-select">
                    <option value="">Toutes les gravités</option>
                    <?php foreach ($gravites as $gv => $gl): ?><option value="<?= $gv ?>" <?= $graviteF===$gv?'selected':'' ?>><?= e($gl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="statut" class="form-select">
                    <option value="">Tous statuts</option>
                    <?php foreach ($statuts as $sv => $sl): ?><option value="<?= $sv ?>" <?= $statutF===$sv?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Élève</th><th>Type</th><th>Gravité</th><th>Lieu</th><th>Signalé par</th><th>Statut</th><?php if ($canManage): ?><th class="text-end"></th><?php endif; ?></tr>
                </thead>
                <tbody>
                    <?php if (empty($incidents)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Aucun incident trouvé.</td></tr>
                    <?php else: foreach ($incidents as $i): ?>
                        <tr>
                            <td class="small text-nowrap"><?= date('d/m/Y', strtotime($i['date_incident'])) ?></td>
                            <td><?= e($i['prenom'] . ' ' . $i['nom']) ?><br><small class="text-muted"><?= e($i['matricule'] . ($i['nom_classe'] ? ' — ' . $i['nom_classe'] : '')) ?></small></td>
                            <td class="small"><?= e($i['type_incident']) ?><?= $i['description'] ? '<br><small class="text-muted">' . e(mb_substr($i['description'], 0, 60)) . '</small>' : '' ?></td>
                            <td><?php $gc = ['faible'=>'info','moyenne'=>'warning','grave'=>'danger'][$i['gravite']] ?? 'secondary'; ?><span class="badge bg-<?= $gc ?>"><?= e($gravites[$i['gravite']] ?? $i['gravite']) ?></span></td>
                            <td class="small"><?= e($i['lieu'] ?: '-') ?></td>
                            <td class="small text-muted"><?= e(trim(($i['sig_prenom'] ?? '') . ' ' . ($i['sig_nom'] ?? ''))) ?: '-' ?></td>
                            <td><span class="badge bg-<?= $i['statut']==='ouvert'?'danger':($i['statut']==='traite'?'info':'success') ?>"><?= e($statuts[$i['statut']] ?? $i['statut']) ?></span></td>
                            <?php if ($canManage): ?>
                            <td class="text-end text-nowrap">
                                <form method="post" action="" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="statut">
                                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                    <select name="statut" class="form-select form-select-sm d-inline-block" style="width:auto;" onchange="this.form.submit()">
                                        <?php foreach ($statuts as $sv => $sl): ?><option value="<?= $sv ?>" <?= $i['statut']===$sv?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                                    </select>
                                </form>
                                <form method="post" action="" class="d-inline" onsubmit="return confirmDelete('Supprimer cet incident ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
