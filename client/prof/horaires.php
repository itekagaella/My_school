<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['prof']);
$user = current_user();
$page_title = 'Emploi du temps';
$active_menu = 'horaires';

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
$show_brouillon = isset($_GET['brouillon']);

if ($show_brouillon) {
    $horaires = prepareQuery(
        "SELECT h.*, m.nom_matiere, m.code AS matiere_code, c.nom_classe
        FROM horaires h
        JOIN matieres m ON m.id = h.matiere_id
        LEFT JOIN classes c ON c.id = h.classe_id
        WHERE h.prof_id=:pid
        ORDER BY CASE h.jour WHEN 'Lundi' THEN 1 WHEN 'Mardi' THEN 2 WHEN 'Mercredi' THEN 3 WHEN 'Jeudi' THEN 4 WHEN 'Vendredi' THEN 5 WHEN 'Samedi' THEN 6 ELSE 7 END, h.heure_debut",
        ['pid'=>$prof_id]
    )->fetchAll();
} else {
    $horaires = prepareQuery(
        "SELECT h.*, m.nom_matiere, m.code AS matiere_code, c.nom_classe
        FROM horaires h
        JOIN matieres m ON m.id = h.matiere_id
        LEFT JOIN classes c ON c.id = h.classe_id
        WHERE h.prof_id=:pid AND h.statut='publie'
        ORDER BY CASE h.jour WHEN 'Lundi' THEN 1 WHEN 'Mardi' THEN 2 WHEN 'Mercredi' THEN 3 WHEN 'Jeudi' THEN 4 WHEN 'Vendredi' THEN 5 WHEN 'Samedi' THEN 6 ELSE 7 END, h.heure_debut",
        ['pid'=>$prof_id]
    )->fetchAll();
}

$jours_ordre = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$horaires_par_jour = [];
foreach ($jours_ordre as $j) $horaires_par_jour[$j] = [];
foreach ($horaires as $h) {
    $horaires_par_jour[$h['jour']][] = $h;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Emploi du temps</h5>
        <small class="text-muted"><?= e($prof['nom'] . ' ' . $prof['prenom']) ?></small>
    </div>
    <div>
        <?php if ($show_brouillon): ?>
            <a href="<?= BASE_URL ?>client/prof/horaires.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-eye me-1"></i>Publiés uniquement
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>client/prof/horaires.php?brouillon=1" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-eye-slash me-1"></i>Inclure brouillons
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($horaires)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-calendar-xmark fs-1 mb-3 d-block"></i>
            Aucun horaire trouvé
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($jours_ordre as $jour):
            $cours_jour = $horaires_par_jour[$jour];
            if (empty($cours_jour)) continue;
        ?>
            <div class="col-lg-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-primary bg-opacity-10 border-bottom">
                        <h6 class="mb-0 text-primary"><i class="fa-solid fa-calendar-day me-2"></i><?= e($jour) ?></h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($cours_jour as $c): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">
                                            <span class="badge bg-secondary me-1"><?= e($c['matiere_code']) ?></span>
                                            <?= e($c['nom_matiere']) ?>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <i class="fa-solid fa-users me-1"></i><?= e($c['nom_classe'] ?? '-') ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold text-primary">
                                            <?= e(substr($c['heure_debut'],0,5).' - '.substr($c['heure_fin'],0,5)) ?>
                                        </div>
                                        <?php if (!empty($c['salle'])): ?>
                                            <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i><?= e($c['salle']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($c['statut'] !== 'publie'): ?>
                                            <span class="badge bg-warning text-dark mt-1">brouillon</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
