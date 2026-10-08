<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['prof']);
$user = current_user();
$page_title = 'Gestion des notes';
$active_menu = 'notes';

$prof = prepareQuery(
    'SELECT * FROM profs WHERE user_id = :uid',
    ['uid'=>$user['id']]
)->fetch();

if (!$prof) {
    set_flash('error', 'Profil professeur introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$prof_id = $prof['id'];

$matieres = prepareQuery(
    "SELECT m.*, c.nom_classe FROM matieres m
    LEFT JOIN classes c ON c.id = m.classe_id
    WHERE m.prof_id=:pid
    ORDER BY m.nom_matiere",
    ['pid'=>$prof_id]
)->fetchAll();

$selected_matiere = post('matiere_id') ?: get('matiere_id', '');
$selected_type = post('type_evaluation') ?: get('type_evaluation', 'devoir');
$selected_date = post('date_evaluation') ?: get('date_evaluation', date('Y-m-d'));
$notes_existantes = [];
$eleves = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_notes') {
    if (!csrf_verify(post('csrf_token'))) {
        set_flash('error', 'Token CSRF invalide.');
        header('Location: ' . BASE_URL . 'client/prof/notes.php');
        exit;
    }

    $m_id = (int)post('matiere_id');
    $type_ev = clean_input(post('type_evaluation'));
    $date_ev = clean_input(post('date_evaluation'));
    $notes_data = $_POST['notes'] ?? [];

    // Vérifier que la matière appartient bien à ce professeur
    $matiere_ok = prepareQuery(
        'SELECT COUNT(*) AS nb FROM matieres WHERE id = :mid AND prof_id = :pid',
        ['mid' => $m_id, 'pid' => $prof_id]
    )->fetch();
    if ((int)($matiere_ok['nb'] ?? 0) === 0) {
        set_flash('error', 'Matière invalide ou non attribuée à ce professeur.');
        header('Location: ' . BASE_URL . 'client/prof/notes.php');
        exit;
    }

    // Vérifier que le type d'évaluation et la date sont valides
    if (!in_array($type_ev, ['devoir','composition','interrogation','trimestriel'])) {
        set_flash('error', 'Type d\'évaluation invalide.');
        header('Location: ' . BASE_URL . 'client/prof/notes.php');
        exit;
    }

    foreach ($notes_data as $eid => $data) {
        $note_val = isset($data['note']) && $data['note'] !== '' ? (float)$data['note'] : null;
        $appreciation = clean_input($data['appreciation'] ?? '');
        if ($note_val === null || $note_val < 0 || $note_val > 20) continue;

        // Vérifier que l'élève appartient à la même classe que la matière
        $eleve_ok = prepareQuery(
            'SELECT COUNT(*) AS nb FROM eleves e
             JOIN matieres m ON m.classe_id = e.classe_id
             WHERE e.id = :eid AND m.id = :mid',
            ['eid' => $eid, 'mid' => $m_id]
        )->fetch();
        if ((int)($eleve_ok['nb'] ?? 0) === 0) continue;

        prepareQuery(
            "INSERT INTO notes (eleve_id, matiere_id, prof_id, note, type_evaluation, date_evaluation, appreciation)
            VALUES (:eid, :mid, :pid, :note, :type, :date, :apprec)
            ON CONFLICT (eleve_id, matiere_id, type_evaluation, date_evaluation)
            DO UPDATE SET note = EXCLUDED.note, appreciation = EXCLUDED.appreciation",
            [
                'eid'=>$eid, 'mid'=>$m_id, 'pid'=>$prof_id,
                'note'=>$note_val, 'type'=>$type_ev, 'date'=>$date_ev, 'apprec'=>$appreciation
            ]
        );
    }

    log_activity('notes.saisie', "Notes saisies pour matière #{$m_id}, type={$type_ev}, date={$date_ev}");
    set_flash('success', 'Notes enregistrées avec succès.');
    header('Location: ' . BASE_URL . 'client/prof/notes.php?matiere_id=' . $m_id . '&type_evaluation=' . urlencode($type_ev) . '&date_evaluation=' . urlencode($date_ev));
    exit;
}

if ($selected_matiere) {
    $matiere_info = prepareQuery(
        "SELECT m.*, c.nom_classe FROM matieres m
        LEFT JOIN classes c ON c.id = m.classe_id
        WHERE m.id=:mid AND m.prof_id=:pid",
        ['mid'=>$selected_matiere, 'pid'=>$prof_id]
    )->fetch();

    if ($matiere_info && $matiere_info['classe_id']) {
        $eleves = prepareQuery(
            "SELECT * FROM eleves WHERE classe_id=:cid ORDER BY nom, prenom",
            ['cid'=>$matiere_info['classe_id']]
        )->fetchAll();

        $existing = prepareQuery(
            "SELECT * FROM notes WHERE matiere_id=:mid AND prof_id=:pid AND type_evaluation=:type AND date_evaluation=:date",
            ['mid'=>$selected_matiere, 'pid'=>$prof_id, 'type'=>$selected_type, 'date'=>$selected_date]
        )->fetchAll();
        foreach ($existing as $ex) {
            $notes_existantes[$ex['eleve_id']] = $ex;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-file-pen me-2 text-primary"></i>Gestion des notes</h4>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>client/prof/notes.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Matière</label>
                <select name="matiere_id" class="form-select" required>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($matieres as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $selected_matiere == $m['id'] ? 'selected' : '' ?>>
                            <?= e($m['nom_matiere']) ?> (<?= e($m['code']) ?>) - <?= e($m['nom_classe'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Type d'évaluation</label>
                <select name="type_evaluation" class="form-select" required>
                    <?php
                    $types = ['devoir'=>'Devoir','composition'=>'Composition','interrogation'=>'Interrogation','trimestriel'=>'Trimestriel'];
                    foreach ($types as $val => $lbl):
                    ?>
                        <option value="<?= $val ?>" <?= $selected_type === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Date</label>
                <input type="date" name="date_evaluation" class="form-control" value="<?= e($selected_date) ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Charger</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_matiere && !empty($eleves)): ?>
    <form method="POST" action="<?= BASE_URL ?>client/prof/notes.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_notes">
        <input type="hidden" name="matiere_id" value="<?= e($selected_matiere) ?>">
        <input type="hidden" name="type_evaluation" value="<?= e($selected_type) ?>">
        <input type="hidden" name="date_evaluation" value="<?= e($selected_date) ?>">

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="fa-solid fa-list me-2"></i>
                    Élèves - <?= e($matiere_info['nom_matiere'] ?? '') ?> (<?= e($matiere_info['nom_classe'] ?? '') ?>)
                </span>
                <span class="badge bg-info"><?= count($eleves) ?> élève(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Matricule</th>
                                <th>Nom complet</th>
                                <th style="width:120px">Note /20</th>
                                <th>Appréciation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eleves as $i => $el):
                                $existing_note = $notes_existantes[$el['id']] ?? null;
                            ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td><span class="badge bg-secondary"><?= e($el['matricule']) ?></span></td>
                                    <td><?= e($el['nom'] . ' ' . $el['prenom']) ?></td>
                                    <td>
                                        <input type="number" name="notes[<?= $el['id'] ?>][note]"
                                            class="form-control form-control-sm"
                                            min="0" max="20" step="0.25"
                                            value="<?= $existing_note ? e($existing_note['note']) : '' ?>"
                                            placeholder="0-20">
                                    </td>
                                    <td>
                                        <input type="text" name="notes[<?= $el['id'] ?>][appreciation]"
                                            class="form-control form-control-sm"
                                            value="<?= $existing_note ? e($existing_note['appreciation']) : '' ?>"
                                            placeholder="Appréciation...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer border-top text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer les notes
                </button>
            </div>
        </div>
    </form>
<?php elseif ($selected_matiere): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-users-slash fs-1 mb-3 d-block"></i>
            Aucun élève trouvé pour cette matière
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
