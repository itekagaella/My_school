<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$id = (int)get('id', 0);
$doc = prepareQuery('SELECT * FROM documents WHERE id = :id', ['id' => $id])->fetch();

if (!$doc) {
    set_flash('error', 'Document introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Partage - ' . $doc['nom_fichier'];
$active_menu = 'documents';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
        header('Location: partage.php?id=' . $id);
        exit;
    }

    $action = post('action', '');

    if ($action === 'add') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            set_flash('error', 'Veuillez sélectionner un utilisateur.');
        } else {
            $exists = prepareQuery(
                'SELECT id FROM document_partages WHERE document_id = :did AND user_id = :uid',
                ['did' => $id, 'uid' => $userId]
            )->fetch();
            if ($exists) {
                set_flash('error', 'Ce document est déjà partagé avec cet utilisateur.');
            } else {
                prepareQuery(
                    'INSERT INTO document_partages (document_id, user_id, date_partage) VALUES (:did, :uid, NOW())',
                    ['did' => $id, 'uid' => $userId]
                );
                log_activity('documents.partage', 'Partage du document ' . $doc['nom_fichier'] . ' avec l\'utilisateur ID ' . $userId);
                set_flash('success', 'Document partagé avec succès.');
            }
        }
    } elseif ($action === 'remove') {
        $partageId = (int)($_POST['partage_id'] ?? 0);
        if ($partageId > 0) {
            prepareQuery('DELETE FROM document_partages WHERE id = :id', ['id' => $partageId]);
            log_activity('documents.partage', 'Retrait du partage ID ' . $partageId . ' pour le document ' . $doc['nom_fichier']);
            set_flash('success', 'Partage retiré.');
        }
    }

    header('Location: partage.php?id=' . $id);
    exit;
}

$partages = prepareQuery(
    "SELECT dp.*, u.prenom, u.nom, u.email, u.role
     FROM document_partages dp
     LEFT JOIN utilisateurs u ON u.id = dp.user_id
     WHERE dp.document_id = :did
     ORDER BY dp.date_partage DESC",
    ['did' => $id]
)->fetchAll();

$allUsers = prepareQuery(
    'SELECT id, prenom, nom, email, role FROM utilisateurs WHERE actif = TRUE ORDER BY nom, prenom'
)->fetchAll();

$sharedUserIds = array_column($partages, 'user_id');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fa-solid fa-share-nodes me-2 text-primary"></i>Partage de « <?= e($doc['nom_fichier']) ?> »</h4>
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Utilisateurs avec accès</div>
            <div class="card-body">
                <?php if (empty($partages)): ?>
                    <p class="text-muted text-center py-3">Ce document n'est partagé avec aucun utilisateur.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Date de partage</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partages as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar" style="width:32px;height:32px;font-size:12px;"><?= strtoupper(mb_substr($p['prenom'] ?? 'U', 0, 1) . mb_substr($p['nom'] ?? '', 0, 1)) ?></div>
                                                <strong><?= e($p['prenom'] . ' ' . $p['nom']) ?></strong>
                                            </div>
                                        </td>
                                        <td class="small"><?= e($p['email']) ?></td>
                                        <td><span class="badge bg-light text-dark"><?= e(ucfirst($p['role'])) ?></span></td>
                                        <td class="small text-nowrap"><?= date('d/m/Y H:i', strtotime($p['date_partage'])) ?></td>
                                        <td class="text-end">
                                            <form method="post" action="" class="d-inline" onsubmit="return confirm('Retirer l\\'accès de cet utilisateur ?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="partage_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer"><i class="fa-solid fa-xmark"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Ajouter un partage</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label required">Utilisateur</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Choisir un utilisateur --</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= in_array($u['id'], $sharedUserIds) ? 'disabled' : '' ?>>
                                    <?= e($u['prenom'] . ' ' . $u['nom']) ?> (<?= e($u['email']) ?>)<?= in_array($u['id'], $sharedUserIds) ? ' — déjà partagé' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-share me-1"></i>Partager</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
