<?php
/**
 * Liste des utilisateurs (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('users.view');

$page_title = 'Gestion des utilisateurs';
$active_menu = 'users_users';

// Filtres de recherche/tri/filtrage
$search = clean_input(get('search', ''));
$role = get('role', '');
$etat = get('etat', '');
$orderBy = get('order', 'created_at');
$orderDir = get('dir', 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$allowedOrder = ['id', 'nom', 'email', 'role', 'actif', 'created_at'];
if (!in_array($orderBy, $allowedOrder)) $orderBy = 'created_at';

// Construction de la requête avec tri/filtres/recherche
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(nom) LIKE :s OR LOWER(prenom) LIKE :s OR LOWER(email) LIKE :s)';
    $params['s'] = '%' . strtolower($search) . '%';
}
if ($role !== '') {
    $where[] = 'role = :role';
    $params['role'] = $role;
}
if ($etat !== '') {
    $where[] = 'actif = :etat';
    $params['etat'] = ($etat === 'actif');
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM utilisateurs $whereSql ORDER BY $orderBy $orderDir";
$users = prepareQuery($sql, $params)->fetchAll();
$total = count($users);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="fa-solid fa-users me-2 text-primary"></i>Utilisateurs <span class="text-muted fs-6">(<?= $total ?>)</span></h4>
    </div>
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Nouvel utilisateur</a>
</div>

<div class="card">
    <div class="card-body">
        <!-- Filtres -->
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher (nom, email)...">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">Tous les rôles</option>
                    <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
                    <option value="eleve" <?= $role==='eleve'?'selected':'' ?>>Élève</option>
                    <option value="prof" <?= $role==='prof'?'selected':'' ?>>Professeur</option>
                    <option value="personnel" <?= $role==='personnel'?'selected':'' ?>>Personnel</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="etat" class="form-select">
                    <option value="">Actif / Inactif</option>
                    <option value="actif" <?= $etat==='actif'?'selected':'' ?>>Actifs</option>
                    <option value="inactif" <?= $etat==='inactif'?'selected':'' ?>>Inactifs</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th><a href="?order=id&dir=<?= $orderDir==='ASC'?'DESC':'ASC' ?>&search=<?= e($search) ?>&role=<?= e($role) ?>&etat=<?= e($etat) ?>">ID</a></th>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>2FA</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucun utilisateur trouvé.</td></tr>
                    <?php else: foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width:36px;height:36px;font-size:13px;">
                                        <?= strtoupper(mb_substr($u['prenom'],0,1) . mb_substr($u['nom'],0,1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= e($u['prenom'] . ' ' . $u['nom']) ?></strong><br>
                                        <small class="text-muted"><?= $u['compte_verrouille'] ? '<span class="text-danger">Verrouillé</span>' : '' ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a></td>
                            <td>
                                <span class="badge badge-role <?= 'badge-role-' . $u['role'] ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['deux_facteurs']): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-shield-halved"></i> Activée</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Désactivée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['actif']): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fa-solid fa-pen"></i></a>
                                <a href="delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="return confirmDelete('Supprimer cet utilisateur ?')"><i class="fa-solid fa-trash"></i></a>
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
