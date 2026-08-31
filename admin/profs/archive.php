<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('profs.archive');

$id = (int)get('id', 0);
$action = get('action', 'archive');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'archive' || $action === 'restore')) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $prof = prepareQuery('SELECT * FROM profs WHERE id = :id', ['id' => $id])->fetch();
        if (!$prof) {
            set_flash('error', 'Professeur introuvable.');
        } elseif ($action === 'archive') {
            getDB()->prepare('SELECT sp_archiver_donnees(:t, :r, :d, :u)')
                ->execute([':t' => 'profs', ':r' => $id, ':d' => json_encode($prof), ':u' => $_SESSION['user_id']]);
            prepareQuery('UPDATE profs SET statut = :s WHERE id = :id', ['s' => 'archive', 'id' => $id]);
            log_activity('profs.archive', 'Archivage du professeur ' . $prof['matricule']);
            set_flash('success', 'Professeur archivé.');
        } else {
            prepareQuery('UPDATE profs SET statut = :s WHERE id = :id', ['s' => 'actif', 'id' => $id]);
            log_activity('profs.restore', 'Restauration du professeur ' . $prof['matricule']);
            set_flash('success', 'Professeur restauré.');
        }
        header('Location: archive.php');
        exit;
    }
}

$page_title = 'Archives des professeurs';
$active_menu = 'archives';

$archives = prepareQuery(
    "SELECT p.*, a.id AS archive_id, a.date_archive, a.donnees_json
     FROM profs p
     LEFT JOIN archives a ON a.table_source = 'profs' AND a.record_id = p.id
     WHERE p.statut = 'archive'
     ORDER BY a.date_archive DESC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fa-solid fa-box-archive me-2"></i>Professeurs archivés</span>
        <a href="index.php" class="btn btn-sm btn-outline-primary">Retour à la liste</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Professeur</th>
                        <th>Spécialité</th>
                        <th>Date archivage</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($archives)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun professeur archivé.</td></tr>
                    <?php else: foreach ($archives as $a): ?>
                        <tr>
                            <td class="small"><?= e($a['matricule']) ?></td>
                            <td><?= e($a['prenom'] . ' ' . $a['nom']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= e($a['specialite'] ?? '-') ?></span></td>
                            <td class="small text-muted"><?= $a['date_archive'] ? date('d/m/Y', strtotime($a['date_archive'])) : '-' ?></td>
                            <td class="text-end">
                                <form method="post" action="archive.php?id=<?= $a['id'] ?>&action=restore" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-success" title="Restaurer" onclick="return confirmDelete('Restaurer ce professeur ?')">
                                        <i class="fa-solid fa-rotate-left"></i> Restaurer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
