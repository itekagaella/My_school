<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('clubs.view');

$id = (int)get('id', 0);
$club = prepareQuery(
    'SELECT c.*, p.nom AS prof_nom, p.prenom AS prof_prenom FROM clubs c
     LEFT JOIN profs p ON p.id = c.prof_responsable_id WHERE c.id = :id',
    ['id' => $id]
)->fetch();
if (!$club) {
    set_flash('error', 'Club introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Détail du club';
$active_menu = 'clubs';

$membres = prepareQuery(
    'SELECT cm.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.matricule, e.statut, cl.nom_classe
     FROM club_membres cm
     JOIN eleves e ON e.id = cm.eleve_id
     LEFT JOIN classes cl ON cl.id = e.classe_id
     WHERE cm.club_id = :id ORDER BY cm.date_inscription DESC',
    ['id' => $id]
)->fetchAll();

$canEdit = has_permission('clubs.edit');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-people-group me-2 text-primary"></i><?= e($club['nom_club']) ?></h4>
    <div class="d-flex gap-2">
        <?php if ($canEdit): ?>
            <a href="edit.php?id=<?= $club['id'] ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i>Modifier</a>
        <?php endif; ?>
        <a href="manage.php?id=<?= $club['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-gear me-1"></i>Gérer les membres</a>
        <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Informations</div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if ($club['logo'] && file_exists(ROOT_PATH . $club['logo'])): ?>
                        <img src="<?= BASE_URL . e($club['logo']) ?>" style="width:100px;height:100px;object-fit:cover;border-radius:50%;" alt="">
                    <?php else: ?>
                        <div class="user-avatar mx-auto" style="width:100px;height:100px;font-size:30px;"><i class="fa-solid fa-people-group"></i></div>
                    <?php endif; ?>
                </div>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><strong>Professeur responsable :</strong><br><?= e(($club['prof_prenom'] ?? '') . ' ' . ($club['prof_nom'] ?? '-')) ?></li>
                    <li class="mb-2"><strong>Date de création :</strong><br><?= date('d/m/Y', strtotime($club['date_creation'])) ?></li>
                    <li class="mb-2"><strong>Statut :</strong><br>
                        <?php if ($club['statut'] === 'actif'): ?>
                            <span class="badge bg-success">Actif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactif</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>Nombre de membres :</strong> <span class="badge bg-info"><?= count($membres) ?></span></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Description</div>
            <div class="card-body">
                <p class="mb-0"><?= e($club['description'] ?? 'Aucune description.') ?></p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-users me-2"></i>Membres (<?= count($membres) ?>)</span>
                <a href="manage.php?id=<?= $club['id'] ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Gérer</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Matricule</th>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Date d'inscription</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($membres)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Aucun membre dans ce club.</td></tr>
                            <?php else: foreach ($membres as $m): ?>
                                <tr>
                                    <td class="small"><?= e($m['matricule']) ?></td>
                                    <td>
                                        <strong><?= e($m['eleve_prenom'] . ' ' . $m['eleve_nom']) ?></strong>
                                        <?php if ($m['statut'] !== 'actif'): ?>
                                            <br><span class="badge bg-secondary"><?= e($m['statut']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= e($m['nom_classe'] ?? '-') ?></span></td>
                                    <td class="small"><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
