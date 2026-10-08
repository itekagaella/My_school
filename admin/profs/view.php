<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('profs.view');

$id = (int)get('id', 0);
$prof = prepareQuery(
    "SELECT p.*, u.email AS email_compte
     FROM profs p
     LEFT JOIN utilisateurs u ON u.id = p.user_id
     WHERE p.id = :id",
    ['id' => $id]
)->fetch();

if (!$prof) {
    set_flash('error', 'Professeur introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Détail professeur';
$active_menu = 'profs';

$matieres = prepareQuery(
    "SELECT m.*, c.nom_classe
     FROM matieres m
     LEFT JOIN classes c ON c.id = m.classe_id
     WHERE m.prof_id = :id",
    ['id' => $id]
)->fetchAll();

$horaires = prepareQuery(
    "SELECT h.*, m.nom_matiere, c.nom_classe
     FROM horaires h
     LEFT JOIN matieres m ON m.id = h.matiere_id
     LEFT JOIN classes c ON c.id = h.classe_id
     WHERE h.prof_id = :id
     ORDER BY CASE h.jour WHEN 'Lundi' THEN 1 WHEN 'Mardi' THEN 2 WHEN 'Mercredi' THEN 3 WHEN 'Jeudi' THEN 4 WHEN 'Vendredi' THEN 5 WHEN 'Samedi' THEN 6 ELSE 7 END, h.heure_debut",
    ['id' => $id]
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    <div>
        <a href="edit.php?id=<?= $prof['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Modifier</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <?php if ($prof['photo'] && file_exists(ROOT_PATH . $prof['photo'])): ?>
                    <img src="<?= BASE_URL . e($prof['photo']) ?>" class="avatar-preview" alt="">
                <?php else: ?>
                    <div class="avatar-preview d-inline-flex align-items-center justify-content-center" style="font-size:40px;background:#e2e8f0;">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                <?php endif; ?>
                <h5><?= e($prof['prenom'] . ' ' . $prof['nom']) ?></h5>
                <span class="badge bg-primary"><?= e($prof['matricule']) ?></span>
                <hr>
                <div class="text-start small">
                    <p><strong>Spécialité :</strong> <?= e($prof['specialite'] ?? '-') ?></p>
                    <p><strong>Téléphone :</strong> <?= e($prof['tel'] ?? '-') ?></p>
                    <p><strong>Email :</strong> <?= e($prof['email'] ?? '-') ?></p>
                    <p><strong>Adresse :</strong> <?= e($prof['adresse'] ?? '-') ?></p>
                    <p><strong>Compte :</strong> <?= e($prof['email_compte'] ?? 'Non lié') ?></p>
                    <p><strong>Statut :</strong>
                        <?php if ($prof['statut'] === 'actif'): ?>
                            <span class="badge bg-success">Actif</span>
                        <?php elseif ($prof['statut'] === 'archive'): ?>
                            <span class="badge bg-warning">Archivé</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= e($prof['statut']) ?></span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Inscrit le :</strong> <?= $prof['created_at'] ? date('d/m/Y', strtotime($prof['created_at'])) : '-' ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-book me-2"></i>Matières enseignées</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Matière</th><th>Classe</th><th>Coefficient</th></tr></thead>
                    <tbody>
                        <?php if (empty($matieres)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Aucune matière assignée.</td></tr>
                        <?php else: foreach ($matieres as $m): ?>
                            <tr>
                                <td class="small"><?= e($m['code'] ?? '-') ?></td>
                                <td><?= e($m['nom_matiere']) ?></td>
                                <td><span class="badge bg-light text-dark"><?= e($m['nom_classe'] ?? '-') ?></span></td>
                                <td><?= e($m['coefficient'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-calendar-days me-2"></i>Emploi du temps</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Jour</th><th>Matière</th><th>Classe</th><th>Horaire</th><th>Salle</th></tr></thead>
                    <tbody>
                        <?php if (empty($horaires)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Aucun horaire enregistré.</td></tr>
                        <?php else: foreach ($horaires as $h): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark"><?= e($h['jour']) ?></span></td>
                                <td><?= e($h['nom_matiere'] ?? '-') ?></td>
                                <td><?= e($h['nom_classe'] ?? '-') ?></td>
                                <td class="small"><?= e($h['heure_debut'] ?? '') ?> - <?= e($h['heure_fin'] ?? '') ?></td>
                                <td class="small"><?= e($h['salle'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
