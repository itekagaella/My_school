<?php
/**
 * Discipline — Sanctions (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('discipline.view');

$page_title = 'Sanctions';
$active_menu = 'discipline';

$canManage = has_permission('discipline.sanctions');
$statuts = statuts_sanction();

$typesCourants = [
    'Avertissement verbal', 'Avertissement écrit', 'Travaux d\'intérêt général',
    'Exclusion temporaire (1 jour)', 'Exclusion temporaire (3 jours)', 'Exclusion temporaire (1 semaine)',
    'Convocation des parents', 'Exclusion définitive', 'Autre',
];

$error = '';
$d = ['eleve_id'=>0,'incident_id'=>0,'type_sanction'=>'','description'=>'','date_debut'=>date('Y-m-d'),'date_fin'=>'','duree_jours'=>'','statut'=>'en_cours'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            prepareQuery('DELETE FROM sanctions WHERE id = :id', ['id' => $id]);
            log_activity('discipline.sanction_delete', 'Suppression sanction #' . $id);
            set_flash('success', 'Sanction supprimée.');
            header('Location: sanctions.php');
            exit;
        }
        if ($action === 'statut') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $_POST['statut'] ?? 'en_cours';
            if (array_key_exists($st, $statuts)) {
                prepareQuery('UPDATE sanctions SET statut = :s WHERE id = :id', ['s' => $st, 'id' => $id]);
                log_activity('discipline.sanction_statut', 'Sanction #' . $id . ' → ' . $st);
                set_flash('success', 'Statut mis à jour.');
            }
            header('Location: sanctions.php');
            exit;
        }

        $d['eleve_id'] = (int)($_POST['eleve_id'] ?? 0);
        $d['incident_id'] = (int)($_POST['incident_id'] ?? 0);
        $d['type_sanction'] = clean_input($_POST['type_sanction'] ?? '');
        $d['description'] = clean_input($_POST['description'] ?? '');
        $d['date_debut'] = clean_input($_POST['date_debut'] ?? date('Y-m-d'));
        $d['date_fin'] = clean_input($_POST['date_fin'] ?? '');
        $d['duree_jours'] = clean_input($_POST['duree_jours'] ?? '');
        $d['statut'] = $_POST['statut'] ?? 'en_cours';

        if ($d['eleve_id'] <= 0) {
            $error = 'Sélectionnez un élève.';
        } elseif ($d['type_sanction'] === '') {
            $error = 'Le type de sanction est obligatoire.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date_debut'])) {
            $error = 'Date de début invalide.';
        } else {
            $dateFin = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date_fin']) ? $d['date_fin'] : null;
            $duree = $d['duree_jours'] !== '' ? (int)$d['duree_jours'] : null;
            if ($duree === null && $dateFin) {
                $duree = max(0, (int)((strtotime($dateFin) - strtotime($d['date_debut'])) / 86400));
            }
            $sanction = prepareQuery(
                'INSERT INTO sanctions (eleve_id, incident_id, type_sanction, description, date_debut, date_fin, duree_jours, prononcee_par, statut)
                 VALUES (:e, :inc, :t, :desc, :dd, :df, :dur, :uid, :st)
                 RETURNING id',
                ['e'=>$d['eleve_id'],'inc'=>($d['incident_id'] ?: null),'t'=>$d['type_sanction'],
                 'desc'=>($d['description'] ?: null),'dd'=>$d['date_debut'],'df'=>$dateFin,'dur'=>$duree,
                 'uid'=>current_user()['id'],'st'=>(array_key_exists($d['statut'], $statuts) ? $d['statut'] : 'en_cours')]
            )->fetch();

            if ($sanction) {
                notifier_sanction(
                    (int)$sanction['id'],
                    (int)$d['eleve_id'],
                    $d['type_sanction'],
                    $d['description'] ?: null,
                    $d['date_debut'],
                    $dateFin,
                    $duree
                );
            }
            log_activity('discipline.sanction_create', 'Sanction ' . $d['type_sanction'] . ' (élève #' . $d['eleve_id'] . ')');
            set_flash('success', 'Sanction enregistrée.');
            header('Location: sanctions.php');
            exit;
        }
    }
}

$search = clean_input(get('search', ''));
$statutF = get('statut', '');
$where = []; $params = [];
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s OR s.type_sanction ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}
if (array_key_exists($statutF, $statuts)) { $where[] = 's.statut = :st'; $params['st'] = $statutF; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sanctions = prepareQuery(
    "SELECT s.*, e.nom, e.prenom, e.matricule, " . classe_label_sql('c') . " AS nom_classe,
            u.prenom AS pr_prenom, u.nom AS pr_nom
     FROM sanctions s
     JOIN eleves e ON e.id = s.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN utilisateurs u ON u.id = s.prononcee_par
     $whereSql
     ORDER BY s.date_debut DESC, s.id DESC",
    $params
)->fetchAll();

$eleves = prepareQuery(
    "SELECT e.id, e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe
     FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
     WHERE e.statut = 'actif' ORDER BY e.nom, e.prenom"
)->fetchAll();

// Incidents ouverts pour rattachement
$incidents = prepareQuery(
    "SELECT i.id, i.type_incident, i.date_incident, e.nom, e.prenom
     FROM incidents_discipline i JOIN eleves e ON e.id = i.eleve_id
     WHERE i.statut <> 'clos' ORDER BY i.date_incident DESC LIMIT 200"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-gavel me-2 text-primary"></i>Sanctions <span class="text-muted fs-6">(<?= count($sanctions) ?>)</span></h4>
    <div class="d-flex gap-2">
        <a href="export.php?type=sanctions" class="btn btn-outline-success"><i class="fa-solid fa-file-csv me-1"></i>Exporter</a>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($canManage): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Prononcer une sanction</div>
    <div class="card-body">
        <form method="post" action="" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4 col-lg-3">
                <label class="form-label required" for="eleve_recherche">Élève</label>
                <input type="text" id="eleve_recherche" class="form-control mb-1"
                       placeholder="Rechercher : nom, prénom ou matricule…" autocomplete="off"
                       oninput="filterSelect(this)">
                <select name="eleve_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($eleves as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= (int)$d['eleve_id'] === (int)$el['id'] ? 'selected' : '' ?>>
                            <?= e($el['matricule'] . ' — ' . $el['prenom'] . ' ' . $el['nom'] . ($el['nom_classe'] ? ' (' . $el['nom_classe'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label required">Type de sanction</label>
                <input list="types-sanction" name="type_sanction" class="form-control" value="<?= e($d['type_sanction']) ?>" required>
                <datalist id="types-sanction">
                    <?php foreach ($typesCourants as $t): ?><option value="<?= e($t) ?>"></option><?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-3">
                <label class="form-label">Incident lié</label>
                <select name="incident_id" class="form-select">
                    <option value="0">— Aucun —</option>
                    <?php foreach ($incidents as $inc): ?>
                        <option value="<?= $inc['id'] ?>" <?= (int)$d['incident_id'] === (int)$inc['id'] ? 'selected' : '' ?>>
                            <?= e('#'.$inc['id'].' ' . $inc['type_incident'] . ' — ' . $inc['prenom'] . ' ' . $inc['nom'] . ' (' . date('d/m/Y', strtotime($inc['date_incident'])) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label required">Début</label>
                <input type="date" name="date_debut" class="form-control" value="<?= e($d['date_debut']) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Fin</label>
                <input type="date" name="date_fin" class="form-control" value="<?= e($d['date_fin']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Durée (jours)</label>
                <input type="number" min="0" name="duree_jours" class="form-control" value="<?= e($d['duree_jours']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <?php foreach ($statuts as $sv => $sl): ?><option value="<?= $sv ?>" <?= $d['statut']===$sv?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="<?= e($d['description']) ?>">
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
            <div class="col-md-6"><input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Élève, matricule ou type..." oninput="filterTable(this)" autocomplete="off"></div>
            <div class="col-md-4">
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
                    <tr><th>Élève</th><th>Sanction</th><th>Période</th><th>Durée</th><th>Prononcée par</th><th>Statut</th><?php if ($canManage): ?><th class="text-end"></th><?php endif; ?></tr>
                </thead>
                <tbody>
                    <?php if (empty($sanctions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune sanction enregistrée.</td></tr>
                    <?php else: foreach ($sanctions as $s): ?>
                        <tr>
                            <td><?= e($s['prenom'] . ' ' . $s['nom']) ?><br><small class="text-muted"><?= e($s['matricule'] . ($s['nom_classe'] ? ' — ' . $s['nom_classe'] : '')) ?></small></td>
                            <td class="small"><?= e($s['type_sanction']) ?><?= $s['incident_id'] ? ' <span class="badge bg-light text-dark">Incident #' . (int)$s['incident_id'] . '</span>' : '' ?><?= $s['description'] ? '<br><small class="text-muted">' . e(mb_substr($s['description'], 0, 60)) . '</small>' : '' ?></td>
                            <td class="small text-nowrap"><?= date('d/m/Y', strtotime($s['date_debut'])) ?><?= $s['date_fin'] ? ' → ' . date('d/m/Y', strtotime($s['date_fin'])) : '' ?></td>
                            <td class="small"><?= $s['duree_jours'] !== null ? (int)$s['duree_jours'] . ' j' : '-' ?></td>
                            <td class="small text-muted"><?= e(trim(($s['pr_prenom'] ?? '') . ' ' . ($s['pr_nom'] ?? ''))) ?: '-' ?></td>
                            <td><span class="badge bg-<?= $s['statut']==='en_cours'?'warning':($s['statut']==='terminee'?'success':'secondary') ?>"><?= e($statuts[$s['statut']] ?? $s['statut']) ?></span></td>
                            <?php if ($canManage): ?>
                            <td class="text-end text-nowrap">
                                <form method="post" action="" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="statut">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <select name="statut" class="form-select form-select-sm d-inline-block" style="width:auto;" onchange="this.form.submit()">
                                        <?php foreach ($statuts as $sv => $sl): ?><option value="<?= $sv ?>" <?= $s['statut']===$sv?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                                    </select>
                                </form>
                                <form method="post" action="" class="d-inline" onsubmit="return confirmDelete('Supprimer cette sanction ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
