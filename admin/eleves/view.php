<?php
/**
 * Détails d'un élève (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('eleves.view');

$id = (int)get('id', 0);
$el = prepareQuery(
    "SELECT e.*, c.nom_classe, u.email AS email_compte
     FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN utilisateurs u ON u.id = e.user_id
     WHERE e.id = :id",
    ['id' => $id]
)->fetch();

if (!$el) {
    set_flash('error', 'Élève introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Détail élève';
$active_menu = 'eleves';

// Notes de l'élève
$notes = prepareQuery(
    "SELECT n.*, m.nom_matiere, m.coefficient
     FROM notes n JOIN matieres m ON m.id = n.matiere_id
     WHERE n.eleve_id = :id ORDER BY n.date_evaluation DESC",
    ['id' => $id]
)->fetchAll();

// Moyenne globale via procédure stockée
$moyenne = 0;
$row = prepareQuery('SELECT sp_calculer_moyenne(:eleve) AS moy', ['eleve' => $id])->fetch();
$moyenne = $row['moy'] ?? 0;

// Présences
$presences = prepareQuery(
    "SELECT p.* FROM presences p WHERE p.eleve_id = :id ORDER BY p.date_presence DESC LIMIT 10",
    ['id' => $id]
)->fetchAll();

// Clubs
$clubs = prepareQuery(
    "SELECT c.nom_club, cm.date_inscription FROM club_membres cm
     JOIN clubs c ON c.id = cm.club_id WHERE cm.eleve_id = :id",
    ['id' => $id]
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    <div>
        <a href="edit.php?id=<?= $el['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Modifier</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <?php if ($el['photo'] && file_exists(ROOT_PATH . $el['photo'])): ?>
                    <img src="<?= BASE_URL . e($el['photo']) ?>" class="avatar-preview" alt="">
                <?php else: ?>
                    <div class="avatar-preview d-inline-flex align-items-center justify-content-center" style="font-size:40px;background:#e2e8f0;">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                <?php endif; ?>
                <h5><?= e($el['prenom'] . ' ' . $el['nom']) ?></h5>
                <span class="badge bg-primary"><?= e($el['matricule']) ?></span>
                <hr>
                <div class="text-start small">
                    <p><strong>Classe :</strong> <?= e($el['nom_classe'] ?? '-') ?></p>
                    <p><strong>Sexe :</strong> <?= $el['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?></p>
                    <p><strong>Naissance :</strong> <?= date('d/m/Y', strtotime($el['date_naissance'])) ?></p>
                    <p><strong>Compte :</strong> <?= e($el['email_compte'] ?? 'Non lié') ?></p>
                    <p><strong>Statut :</strong>
                        <span class="badge bg-<?= $el['statut']==='actif'?'success':'secondary' ?>"><?= e($el['statut']) ?></span>
                    </p>
                </div>
                <h6 class="text-center">Moyenne générale</h6>
                <div class="h3 text-primary"><?= number_format($moyenne, 2) ?>/20</div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Notes -->
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-file-pen me-2"></i>Notes récentes</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Matière</th><th>Note</th><th>Type</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if (empty($notes)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Aucune note enregistrée.</td></tr>
                        <?php else: foreach ($notes as $n): ?>
                            <tr>
                                <td><?= e($n['nom_matiere']) ?></td>
                                <td><span class="badge bg-<?= $n['note'] >= 10 ? 'success' : 'danger' ?>"><?= number_format($n['note'], 2) ?></span></td>
                                <td class="small"><?= e($n['type_evaluation']) ?></td>
                                <td class="small text-muted"><?= date('d/m/Y', strtotime($n['date_evaluation'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Clubs -->
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-people-group me-2"></i>Clubs</div>
            <div class="card-body">
                <?php if (empty($clubs)): ?>
                    <p class="text-muted mb-0">Non inscrit dans un club.</p>
                <?php else: foreach ($clubs as $c): ?>
                    <span class="badge bg-secondary me-1"><i class="fa-solid fa-people-group me-1"></i><?= e($c['nom_club']) ?></span>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
