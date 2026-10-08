<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['personnel']);
$user = current_user();
$page_title = 'Emploi du temps';
$active_menu = 'horaires';

$pers = prepareQuery(
    'SELECT * FROM personnel WHERE user_id = :u',
    ['u' => $user['id']]
)->fetch();

if (!$pers) {
    set_flash('error', 'Profil personnel introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$classes = prepareQuery('SELECT id, nom_classe FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
$filtre_classe = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : 0;

$params = [];
$where = "h.statut='publie'";
if ($filtre_classe > 0) {
    $where .= " AND h.classe_id=:cid";
    $params['cid'] = $filtre_classe;
}

$horaires = prepareQuery(
    "SELECT h.*, m.nom_matiere, m.code AS matiere_code,
            p.nom AS prof_nom, p.prenom AS prof_prenom,
            c.nom_classe
     FROM horaires h
     JOIN matieres m ON m.id = h.matiere_id
     LEFT JOIN profs p ON p.id = h.prof_id
     JOIN classes c ON c.id = h.classe_id
     WHERE $where
     ORDER BY CASE h.jour WHEN 'Lundi' THEN 1 WHEN 'Mardi' THEN 2 WHEN 'Mercredi' THEN 3 WHEN 'Jeudi' THEN 4 WHEN 'Vendredi' THEN 5 WHEN 'Samedi' THEN 6 ELSE 7 END, h.heure_debut ASC",
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Emploi du temps</h4>
    <form method="get" action="" class="filter-form">
        <label class="text-muted small mb-0 d-none d-sm-inline">Classe</label>
        <select name="classe_id" class="form-select flex-grow-1" onchange="this.form.submit()">
            <option value="0">Toutes les classes</option>
            <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $filtre_classe == $cl['id'] ? 'selected' : '' ?>><?= e($cl['nom_classe']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="fa-solid fa-table-list me-2"></i>Cours de la semaine</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($horaires)): ?>
            <div class="text-center text-muted py-4">Aucun horaire disponible</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jour</th>
                            <th>Heure</th>
                            <th>Matière</th>
                            <th>Professeur</th>
                            <th>Classe</th>
                            <th>Salle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horaires as $h): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= e($h['jour']) ?></span></td>
                                <td><i class="fa-solid fa-clock me-1 text-muted"></i><?= e(substr($h['heure_debut'],0,5).' - '.substr($h['heure_fin'],0,5)) ?></td>
                                <td>
                                    <span class="badge bg-primary me-1"><?= e($h['matiere_code']) ?></span>
                                    <?= e($h['nom_matiere']) ?>
                                </td>
                                <td><?= $h['prof_prenom'] ? e($h['prof_prenom'].' '.$h['prof_nom']) : '<span class="text-muted">-</span>' ?></td>
                                <td><?= e($h['nom_classe']) ?></td>
                                <td><i class="fa-solid fa-location-dot me-1 text-muted"></i><?= e($h['salle']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
