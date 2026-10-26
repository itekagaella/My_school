<?php
/**
 * Espace Surveillant — Suivi et saisie des présences
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['surveillant']);
$user = current_user();
$page_title = 'Suivi des présences';
$active_menu = 'presences';

$pers = prepareQuery('SELECT * FROM personnel WHERE user_id = :u', ['u' => $user['id']])->fetch();
if (!$pers) {
    set_flash('error', 'Profil personnel introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$canManage = has_permission('surveillance.manage');
$statutsLabels = ['present' => 'Présent', 'absent' => 'Absent', 'retard' => 'Retard', 'excuse' => 'Excusé'];

$date = clean_input(get('date', date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$classeId = (int)get('classe_id', 0);

$classes = prepareQuery('SELECT id, nom_classe, section FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

// Enregistrement d'un appel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        $postDate = clean_input($_POST['date'] ?? $date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)) $postDate = $date;
        $statuts = $_POST['statut'] ?? [];
        $motifs = $_POST['motif'] ?? [];
        $saved = 0;
        foreach ($statuts as $eleveId => $st) {
            $eleveId = (int)$eleveId;
            if ($eleveId <= 0 || !array_key_exists($st, $statutsLabels)) continue;
            $motif = clean_input($motifs[$eleveId] ?? '');
            prepareQuery(
                "INSERT INTO presences (eleve_id, date_presence, statut, motif)
                 VALUES (:e, :d, :st, :m)
                 ON CONFLICT (eleve_id, date_presence)
                 DO UPDATE SET statut = EXCLUDED.statut, motif = EXCLUDED.motif",
                ['e' => $eleveId, 'd' => $postDate, 'st' => $st, 'm' => ($motif ?: null)]
            );
            $saved++;
        }
        log_activity('surveillance.presences', $saved . ' présence(s) enregistrée(s) pour ' . $postDate);
        set_flash('success', $saved . ' présence(s) enregistrée(s).');
        header('Location: presences.php?date=' . urlencode($postDate) . '&classe_id=' . (int)($_POST['classe_id'] ?? 0));
        exit;
    }
}

$eleves = [];
$presences = [];
if ($classeId > 0) {
    $eleves = prepareQuery(
        "SELECT e.id, e.matricule, e.nom, e.prenom FROM eleves e
         WHERE e.statut = 'actif' AND e.classe_id = :cl ORDER BY e.nom, e.prenom",
        ['cl' => $classeId]
    )->fetchAll();
    $rows = prepareQuery(
        "SELECT p.eleve_id, p.statut, p.motif FROM presences p
         JOIN eleves e ON e.id = p.eleve_id
         WHERE p.date_presence = :d AND e.classe_id = :cl",
        ['d' => $date, 'cl' => $classeId]
    )->fetchAll();
    foreach ($rows as $r) { $presences[(int)$r['eleve_id']] = $r; }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Suivi des présences</h4>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Classe</label>
                <select name="classe_id" class="form-select">
                    <option value="0">— Sélectionner une classe —</option>
                    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classeId==$c['id']?'selected':'' ?>><?= e(classe_label($c['nom_classe'], $c['section'] ?? null)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Charger</button></div>
        </form>
    </div>
</div>

<?php if ($classeId > 0): ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <input type="hidden" name="classe_id" value="<?= $classeId ?>">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-users me-2"></i><?= count($eleves) ?> élève(s) — <?= date('d/m/Y', strtotime($date)) ?></span>
                <?php if ($canManage): ?>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="setAll('present')">Tous présents</button>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Matricule</th><th>Élève</th><th>Statut</th><th>Motif</th></tr></thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Aucun élève dans cette classe.</td></tr>
                        <?php else: foreach ($eleves as $el):
                            $cur = $presences[$el['id']]['statut'] ?? 'present';
                            $motif = $presences[$el['id']]['motif'] ?? '';
                        ?>
                            <tr>
                                <td class="small text-nowrap"><?= e($el['matricule']) ?></td>
                                <td><?= e($el['prenom'] . ' ' . $el['nom']) ?></td>
                                <td>
                                    <select name="statut[<?= $el['id'] ?>]" class="form-select form-select-sm statut-select" style="width:auto;" <?= $canManage ? '' : 'disabled' ?>>
                                        <?php foreach ($statutsLabels as $sv => $sl): ?><option value="<?= $sv ?>" <?= $cur===$sv?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="motif[<?= $el['id'] ?>]" class="form-control form-control-sm" value="<?= e($motif) ?>" <?= $canManage ? '' : 'disabled' ?>></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <script>
    function setAll(val) {
        document.querySelectorAll('.statut-select').forEach(function (s) { s.value = val; });
    }
    </script>
<?php else: ?>
    <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="fa-solid fa-clipboard-check fa-3x mb-3 d-block"></i>
        Sélectionnez une classe pour saisir les présences.
    </div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
