<?php
/**
 * Gestion des rôles et des permissions (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('users.permissions');

$page_title = 'Rôles et permissions';
$active_menu = 'users_roles';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_role') {
            $nom = strtolower(trim($_POST['nom_role'] ?? ''));
            $desc = clean_input($_POST['description'] ?? '');
            if (empty($nom)) set_flash('error', 'Nom du rôle requis.');
            else {
                prepareQuery('INSERT INTO roles (nom_role, description) VALUES (:n, :d) ON CONFLICT (nom_role) DO NOTHING', ['n'=>$nom,'d'=>$desc]);
                log_activity('roles.create', 'Création du rôle ' . $nom);
                set_flash('success', 'Rôle créé.');
            }
        } elseif ($action === 'delete_role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            // Pas de suppression des rôles système
            $r = prepareQuery('SELECT nom_role FROM roles WHERE id = :id', ['id'=>$roleId])->fetch();
            if ($r && in_array($r['nom_role'], ['admin','eleve','prof','personnel'])) {
                set_flash('error', 'Impossible de supprimer un rôle système.');
            } else {
                prepareQuery('DELETE FROM roles WHERE id = :id', ['id'=>$roleId]);
                log_activity('roles.delete', 'Suppression du rôle #' . $roleId);
                set_flash('success', 'Rôle supprimé.');
            }
        } elseif ($action === 'update_permissions') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $checked = $_POST['perms'] ?? [];
            // Réinitialiser puis réattribuer
            prepareQuery('DELETE FROM role_permissions WHERE role_id = :id', ['id'=>$roleId]);
            foreach ($checked as $permId) {
                prepareQuery(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:r, :p) ON CONFLICT DO NOTHING',
                    ['r'=>$roleId, 'p'=>(int)$permId]
                );
            }
            log_activity('roles.permissions', 'Mise à jour des permissions du rôle #' . $roleId);
            set_flash('success', 'Permissions mises à jour.');
        }
        header('Location: roles.php');
        exit;
    }
}

$roles = prepareQuery('SELECT * FROM roles ORDER BY id')->fetchAll();
$permissions = prepareQuery('SELECT * FROM permissions ORDER BY nom_permission')->fetchAll();

// Récupérer les permissions par rôle
$rolePerms = [];
$rows = prepareQuery('SELECT role_id, permission_id FROM role_permissions')->fetchAll();
foreach ($rows as $r) $rolePerms[$r['role_id']][] = $r['permission_id'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row g-3">
    <!-- Liste des rôles -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-user-tag me-2"></i>Rôles</div>
            <div class="card-body">
                <?php foreach ($roles as $role): ?>
                    <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                        <div>
                            <strong><?= ucfirst(e($role['nom_role'])) ?></strong>
                            <div><small class="text-muted"><?= e($role['description']) ?></small></div>
                        </div>
                        <?php if (!in_array($role['nom_role'], ['admin','eleve','prof','personnel'])): ?>
                        <form method="post" action="">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_role">
                            <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Supprimer ce rôle ?')"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <hr>
                <h6>Nouveau rôle</h6>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_role">
                    <div class="mb-2">
                        <input type="text" name="nom_role" class="form-control" placeholder="Nom du rôle" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="description" class="form-control" placeholder="Description">
                    </div>
                    <button class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-plus me-1"></i>Ajouter</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Permissions par rôle -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-shield-halved me-2"></i>Attribuer les permissions</div>
            <div class="card-body">
                <?php foreach ($roles as $role): ?>
                    <div class="mb-4 p-3 border rounded">
                        <h6 class="d-flex justify-content-between">
                            <span><i class="fa-solid fa-user-tag me-1"></i><?= ucfirst(e($role['nom_role'])) ?></span>
                            <span class="badge bg-primary"><?= count($rolePerms[$role['id']] ?? []) ?> permissions</span>
                        </h6>
                        <form method="post" action="">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_permissions">
                            <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                            <div class="row">
                                <?php foreach ($permissions as $perm): ?>
                                    <div class="col-md-6 col-lg-4 mb-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="perms[]"
                                                   value="<?= $perm['id'] ?>"
                                                   id="perm_<?= $role['id'] ?>_<?= $perm['id'] ?>"
                                                   <?= in_array($perm['id'], $rolePerms[$role['id']] ?? []) ? 'checked' : '' ?>
                                                   <?= $role['nom_role'] === 'admin' ? 'disabled' : '' ?>>
                                            <label class="form-check-label small" for="perm_<?= $role['id'] ?>_<?= $perm['id'] ?>">
                                                <?= e($perm['nom_permission']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($role['nom_role'] !== 'admin'): ?>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-primary"><i class="fa-solid fa-save me-1"></i>Enregistrer</button>
                            </div>
                            <?php else: ?>
                            <small class="text-muted">L'administrateur dispose de toutes les permissions.</small>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
