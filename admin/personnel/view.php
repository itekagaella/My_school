<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('personnel.view');

$id = (int)get('id', 0);
$p = prepareQuery(
    "SELECT p.*, u.email AS email_compte
     FROM personnel p
     LEFT JOIN utilisateurs u ON u.id = p.user_id
     WHERE p.id = :id",
    ['id' => $id]
)->fetch();

if (!$p) {
    set_flash('error', 'Membre du personnel introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Détail membre du personnel';
$active_menu = 'personnel';

$canEdit = has_permission('personnel.edit');
$canDelete = has_permission('personnel.delete');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    <div class="d-flex gap-2">
        <?php if ($canEdit): ?>
        <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Modifier</a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
        <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger" onclick="return confirmDelete('Supprimer ce membre du personnel ?')"><i class="fa-solid fa-trash me-1"></i>Supprimer</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body text-center">
                <div class="avatar-preview d-inline-flex align-items-center justify-content-center" style="font-size:40px;background:#e2e8f0;">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <h5 class="mt-3"><?= e($p['prenom'] . ' ' . $p['nom']) ?></h5>
                <span class="badge bg-primary"><?= e($p['matricule']) ?></span>
                <hr>
                <div class="text-start small">
                    <p><strong>Fonction :</strong> <?= e($p['fonction']) ?></p>
                    <p><strong>Téléphone :</strong> <?= e($p['tel'] ?: '-') ?></p>
                    <p><strong>Email :</strong> <?= e($p['email'] ?: '-') ?></p>
                    <p><strong>Compte :</strong> <?= e($p['email_compte'] ?? 'Non lié') ?></p>
                    <p><strong>Statut :</strong>
                        <span class="badge bg-<?= $p['statut'] === 'actif' ? 'success' : ($p['statut'] === 'inactif' ? 'secondary' : 'warning') ?>"><?= e($p['statut']) ?></span>
                    </p>
                    <p><strong>Ajouté le :</strong> <?= date('d/m/Y', strtotime($p['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-circle-info me-2"></i>Informations complètes</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <th style="width:200px;">ID</th>
                            <td><?= $p['id'] ?></td>
                        </tr>
                        <tr>
                            <th>Matricule</th>
                            <td><?= e($p['matricule']) ?></td>
                        </tr>
                        <tr>
                            <th>Nom complet</th>
                            <td><?= e($p['prenom'] . ' ' . $p['nom']) ?></td>
                        </tr>
                        <tr>
                            <th>Fonction</th>
                            <td><?= e($p['fonction']) ?></td>
                        </tr>
                        <tr>
                            <th>Téléphone</th>
                            <td><?= e($p['tel'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <th>Email personnel</th>
                            <td><?= e($p['email'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <th>Email du compte</th>
                            <td><?= e($p['email_compte'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td><span class="badge bg-<?= $p['statut'] === 'actif' ? 'success' : ($p['statut'] === 'inactif' ? 'secondary' : 'warning') ?>"><?= e($p['statut']) ?></span></td>
                        </tr>
                        <tr>
                            <th>Date de création</th>
                            <td><?= date('d/m/Y à H:i', strtotime($p['created_at'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
