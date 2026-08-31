<?php
/**
 * Archivage / Restauration des élèves (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('eleves.archive');

$id = (int)get('id', 0);
$action = get('action', 'archive'); // archive | restore | list

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'archive' || $action === 'restore')) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $el = prepareQuery('SELECT * FROM eleves WHERE id = :id', ['id' => $id])->fetch();
        if (!$el) {
            set_flash('error', 'Élève introuvable.');
        } elseif ($action === 'archive') {
            // Sauvegarder les données en JSON pour la restauration (procédure stockée)
            getDB()->prepare('SELECT sp_archiver_donnees(:t, :r, :d, :u)')
                ->execute([':t' => 'eleves', ':r' => $id, ':d' => json_encode($el), ':u' => $_SESSION['user_id']]);
            // Marquer l'élève comme archivé
            prepareQuery('UPDATE eleves SET statut = :s WHERE id = :id', ['s' => 'archive', 'id' => $id]);
            log_activity('eleves.archive', 'Archivage de l\'élève ' . $el['matricule']);
            set_flash('success', 'Élève archivé.');
        } else {
            prepareQuery('UPDATE eleves SET statut = :s WHERE id = :id', ['s' => 'actif', 'id' => $id]);
            log_activity('eleves.restore', 'Restauration de l\'élève ' . $el['matricule']);
            set_flash('success', 'Élève restauré.');
        }
        header('Location: archive.php');
        exit;
    }
}

$page_title = 'Archives des élèves';
$active_menu = 'archives';

// Liste des élèves archivés
$archives = prepareQuery(
    "SELECT e.*, c.nom_classe, a.id AS archive_id, a.date_archive, a.donnees_json
     FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN archives a ON a.table_source = 'eleves' AND a.record_id = e.id
     WHERE e.statut = 'archive'
     ORDER BY a.date_archive DESC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fa-solid fa-box-archive me-2"></i>Élèves archivés</span>
        <a href="index.php" class="btn btn-sm btn-outline-primary">Retour à la liste</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Date archivage</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($archives)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun élève archivé.</td></tr>
                    <?php else: foreach ($archives as $a): ?>
                        <tr>
                            <td class="small"><?= e($a['matricule']) ?></td>
                            <td><?= e($a['prenom'] . ' ' . $a['nom']) ?></td>
                            <td><?= e($a['nom_classe'] ?? '-') ?></td>
                            <td class="small text-muted"><?= $a['date_archive'] ? date('d/m/Y', strtotime($a['date_archive'])) : '-' ?></td>
                            <td class="text-end">
                                <form method="post" action="archive.php?id=<?= $a['id'] ?>&action=restore" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-success" title="Restaurer" onclick="return confirmDelete('Restaurer cet élève ?')">
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
