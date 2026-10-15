<?php
/**
 * Discipline — Convocations des parents (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('discipline.view');

$page_title = 'Convocations';
$active_menu = 'discipline';

$canManage = has_permission('discipline.manage');
$statuts = ['prevue' => 'Prévue', 'effectuee' => 'Effectuée', 'manquee' => 'Manquée'];

$error = '';
$d = ['eleve_id'=>0,'incident_id'=>0,'date_convocation'=>date('Y-m-d'),'heure'=>'','motif'=>'','statut'=>'prevue'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            prepareQuery('DELETE FROM convocations WHERE id = :id', ['id' => $id]);
            set_flash('success', 'Convocation supprimée.');
            header('Location: convocations.php');
            exit;
        }
        if ($action === 'statut') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $_POST['statut'] ?? 'prevue';
            if (array_key_exists($st, $statuts)) {
                prepareQuery('UPDATE convocations SET statut = :s WHERE id = :id', ['s' => $st, 'id' => $id]);
                set_flash('success', 'Statut mis à jour.');
            }
            header('Location: convocations.php');
            exit;
        }

        $d['eleve_id'] = (int)($_POST['eleve_id'] ?? 0);
        $d['incident_id'] = (int)($_POST['incident_id'] ?? 0);
        $d['date_convocation'] = clean_input($_POST['date_convocation'] ?? date('Y-m-d'));
        $d['heure'] = clean_input($_POST['heure'] ?? '');
        $d['motif'] = clean_input($_POST['motif'] ?? '');
        $d['statut'] = $_POST['statut'] ?? 'prevue';

        if ($d['eleve_id'] <= 0) {
            $error = 'Sélectionnez un élève.';
        } elseif ($d['motif'] === '') {
            $error = 'Le motif est obligatoire.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date_convocation'])) {
            $error = 'Date invalide.';
        } else {
            prepareQuery(
                'INSERT INTO convocations (eleve_id, incident_id, date_convocation, heure, motif, statut, cree_par)
                 VALUES (:e, :inc, :dc, :h, :m, :st, :uid)',
                ['e'=>$d['eleve_id'],'inc'=>($d['incident_id'] ?: null),'dc'=>$d['date_convocation'],
                 'h'=>($d['heure'] ?: null),'m'=>$d['motif'],
                 'st'=>(array_key_exists($d['statut'], $statuts) ? $d['statut'] : 'prevue'),'uid'=>current_user()['id']]
            );
            log_activity('discipline.convocation_create', 'Convocation élève #' . $d['eleve_id']);
            set_flash('success', 'Convocation enregistrée.');
            header('Location: convocations.php');
            exit;
        }
    }
}

$search = clean_input(get('search', ''));
$statutF = get('statut', '');
$where = []; $params = [];
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s OR cv.motif ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}
if (array_key_exists($statutF, $statuts)) { $where[] = 'cv.statut = :st'; $params['st'] = $statutF; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$convocations = prepareQuery(
    "SELECT cv.*, e.nom, e.prenom, e.matricule, c.nom_classe
     FROM convocations cv
     JOIN eleves e ON e.id = cv.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     $whereSql
     ORDER BY cv.date_convocation DESC, cv.id DESC",
    $params
)->fetchAll();

$eleves = prepareQuery(
    "SELECT e.id, e.matricule, e.nom, e.prenom, c.nom_classe
     FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
     WHERE e.statut = 'actif' ORDER BY e.nom, e.prenom"
)->fetchAll();

$incidents = prepareQuery(
    "SELECT i.id, i.type_incident, i.date_incident, e.nom, e.prenom
     FROM incidents_discipline i JOIN eleves e ON e.id = i.eleve_id
     ORDER BY i.date_incident DESC LIMIT 200"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-envelope me-2 text-primary"></i>Convocations <span class="text-muted fs-6">(<?= count($convocations) ?>)</span></h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Tableau de bord</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($canManage): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Nouvelle convocation</div>
    <div class="card-body">
        <form method="post" action="" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label required">Élève</label>
                <select name="eleve_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($eleves as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= (int)$d['eleve_id'] === (int)$el['id'] ? 'selected' : '' ?>><?= e($el['matricule'] . ' — ' . $el['prenom'] . ' ' . $el['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Incident lié</label>
                <select name="incident_id" class="form-select">
                    <option value="0">— Aucun —</option>
                    <?php foreach ($incidents as $inc): ?>
                        <option value="<?= $inc['id'] ?>" <?= (int)$d['incident_id'] === (int)$inc['id'] ? 'selected' : '' ?>><?= e('#'.$inc['id'].' ' . $inc['type_incident'] . ' — ' . $inc['prenom'] . ' ' . $inc['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label required">Date</label>
                <input type="date" name="date_convocation" class="form-control" value="<?= e($d['date_convocation']) ?>" required>
            </div>
            <div class="col-md-1">
                <label class="form-label">Heure</label>
                <input type="time" name="heure" class="form-control" value="<?= e($d['heure']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label required">Motif</label>
                <input type="text" name="motif" class="form-control" value="<?= e($d['motif']) ?>" required>
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
            <div class="col-md-6"><input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Élève ou motif..."></div>
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
                <thead class="table-light"><tr><th>Date</th><th>Élève</th><th>Motif</th><th>Incident</th><th>Statut</th><?php if ($canManage): ?><th class="text-end"></th><?php endif; ?></tr></thead>
                <tbody>
                    <?php if (empty($convocations)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucune convocation.</td></tr>
                    <?php else: foreach ($convocations as $cv): ?>
                        <tr>
                            <td class="small text-nowrap"><?= date('d/m/Y', strtotime($cv['date_convocation'])) ?><?= $cv['heure'] ? '<br><small class="text-muted">' . e(substr($cv['heure'],0,5)) . '</small>' : '' ?></td>
                            <td><?= e($cv['prenom'] . ' ' . $cv['nom']) ?><br><small class="text-muted"><?= e($cv['nom_classe'] ?? '') ?></small></td>
                            <td class="small"><?= e($cv['motif']) ?></td>
                            <td class="small"><?= $cv['incident_id'] ? '#'.(int)$cv['incident_id'] : '-' ?></td>
                            <td><span class="badge bg-<?= $cv['statut']==='prevue'?'warning':($cv['statut']==='effectuee'?'success':'secondary') ?>"><?= e($statuts[$cv['statut']] ?? $cv['statut']) ?></span></td>
                            <?php if ($canManage): ?>
                            <td class="text-end text-nowrap">
                                <form method="post" action="" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="statut">
                                    <input type="hidden" name="id" value="<?= $cv['id'] ?>">
                                    <select name="statut" class="form-select form-select-sm d-inline-block" style="width:auto;" onchange="this.form.submit()">
                                        <?php foreach ($statuts as $sv => $sl): ?><option value="<?= $sv ?>" <?= $cv['statut']===$sv?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                                    </select>
                                </form>
                                <form method="post" action="" class="d-inline" onsubmit="return confirmDelete('Supprimer cette convocation ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $cv['id'] ?>">
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
