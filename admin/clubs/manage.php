<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('clubs.edit');

$id = (int)get('id', 0);
$club = prepareQuery('SELECT * FROM clubs WHERE id = :id', ['id' => $id])->fetch();
if (!$club) {
    set_flash('error', 'Club introuvable.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée, veuillez réessayer.');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $eleve_id = (int)($_POST['eleve_id'] ?? 0);
            if ($eleve_id <= 0) {
                set_flash('error', 'Veuillez sélectionner un élève.');
            } else {
                $exists = prepareQuery(
                    'SELECT id FROM club_membres WHERE club_id = :c AND eleve_id = :e',
                    ['c' => $id, 'e' => $eleve_id]
                )->fetch();
                if ($exists) {
                    set_flash('error', 'Cet élève est déjà membre du club.');
                } else {
                    prepareQuery(
                        'INSERT INTO club_membres (club_id, eleve_id, date_inscription) VALUES (:c, :e, CURRENT_DATE)',
                        ['c' => $id, 'e' => $eleve_id]
                    );
                    log_activity('clubs.add_member', 'Ajout d\'un membre au club ' . $club['nom_club'] . ' (élève ID ' . $eleve_id . ')');
                    set_flash('success', 'Élève ajouté au club.');
                }
            }
        } elseif ($action === 'remove') {
            $membre_id = (int)($_POST['membre_id'] ?? 0);
            if ($membre_id > 0) {
                prepareQuery('DELETE FROM club_membres WHERE id = :mid AND club_id = :c', ['mid' => $membre_id, 'c' => $id]);
                log_activity('clubs.remove_member', 'Retrait d\'un membre du club ' . $club['nom_club']);
                set_flash('success', 'Membre retiré du club.');
            }
        }
    }
    header('Location: manage.php?id=' . $id);
    exit;
}

$page_title = 'Gestion des membres';
$active_menu = 'clubs';

$membres = prepareQuery(
    'SELECT cm.id AS membre_id, cm.date_inscription, e.id AS eleve_id, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.matricule, cl.nom_classe
     FROM club_membres cm
     JOIN eleves e ON e.id = cm.eleve_id
     LEFT JOIN classes cl ON cl.id = e.classe_id
     WHERE cm.club_id = :id ORDER BY cm.date_inscription DESC',
    ['id' => $id]
)->fetchAll();

$membreIds = array_map(function ($m) { return (int)$m['eleve_id']; }, $membres);

$where = 'e.statut = :act';
$params = ['act' => 'actif'];
if (!empty($membreIds)) {
    $placeholders = [];
    foreach ($membreIds as $i => $mid) {
        $placeholders[] = ':mid' . $i;
        $params['mid' . $i] = $mid;
    }
    $where .= ' AND e.id NOT IN (' . implode(',', $placeholders) . ')';
}

$eleves_disponibles = prepareQuery(
    "SELECT e.id, e.nom, e.prenom, e.matricule, cl.nom_classe
     FROM eleves e LEFT JOIN classes cl ON cl.id = e.classe_id
     WHERE $where ORDER BY e.nom, e.prenom",
    $params
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-users me-2 text-primary"></i>Gestion des membres : <?= e($club['nom_club']) ?></h4>
    <div class="d-flex gap-2">
        <a href="view.php?id=<?= $club['id'] ?>" class="btn btn-outline-info"><i class="fa-solid fa-eye me-1"></i>Voir</a>
        <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Ajouter un élève</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Élève</label>
                        <select name="eleve_id" class="form-select" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($eleves_disponibles as $el): ?>
                                <option value="<?= $el['id'] ?>"><?= e($el['prenom'] . ' ' . $el['nom']) ?> (<?= e($el['matricule']) ?>) - <?= e($el['nom_classe'] ?? '-') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($eleves_disponibles)): ?>
                            <small class="text-muted">Tous les élèves actifs sont déjà membres de ce club.</small>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary" <?= empty($eleves_disponibles)?'disabled':'' ?>><i class="fa-solid fa-plus me-1"></i>Ajouter au club</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">Membres actuels (<?= count($membres) ?>)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Date inscription</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($membres)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Aucun membre dans ce club.</td></tr>
                            <?php else: foreach ($membres as $m): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($m['eleve_prenom'] . ' ' . $m['eleve_nom']) ?></strong>
                                        <br><small class="text-muted"><?= e($m['matricule']) ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= e($m['nom_classe'] ?? '-') ?></span></td>
                                    <td class="small"><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
                                    <td class="text-end">
                                        <form method="post" action="" class="d-inline" onsubmit="return confirmDelete('Retirer ce membre du club ?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="membre_id" value="<?= (int)$m['membre_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer"><i class="fa-solid fa-user-minus"></i></button>
                                        </form>
                                    </td>
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
