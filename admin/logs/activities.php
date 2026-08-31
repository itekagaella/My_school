<?php
/**
 * Journal des activités et historique des connexions (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('logs.view');

$page_title = 'Journal & Connexions';
$active_menu = 'logs';

$tab = get('tab', 'activities');

// Filtres
$search = clean_input(get('search', ''));
$user_id = (int)get('user_id', 0);
$orderBy = get('order', 'date_action');
$orderDir = get('dir', 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// ---- Journal des activités ----
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(LOWER(j.action) LIKE :s OR LOWER(j.details) LIKE :s OR LOWER(COALESCE(u.nom,\'\')) LIKE :s)';
    $where['s'] = '%' . strtolower($search) . '%';
}
if ($user_id > 0) {
    $where[] = 'j.user_id = :uid';
    $params['uid'] = $user_id;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
// Corriger : les params utilisent :s avec WHERE multiple - utiliser replacage

$activities = prepareQuery(
    "SELECT j.*, u.nom, u.prenom, u.email, u.role
     FROM journal_activites j LEFT JOIN utilisateurs u ON u.id = j.user_id
     ORDER BY j.date_action $orderDir LIMIT 200"
)->fetchAll();

// ---- Historique des connexions ----
$connections = prepareQuery(
    "SELECT h.*, u.nom, u.prenom, u.email
     FROM historique_connexions h LEFT JOIN utilisateurs u ON u.id = h.user_id
     ORDER BY h.date_connexion DESC LIMIT 100"
)->fetchAll();

$users = prepareQuery('SELECT id, nom, prenom, email FROM utilisateurs ORDER BY nom')->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab==='activities'?'active':'' ?>" href="?tab=activities">Journal des activités</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='connections'?'active':'' ?>" href="?tab=connections">Historique des connexions</a>
    </li>
</ul>

<?php if ($tab === 'activities'): ?>
<div class="card">
    <div class="card-header">Journal des activités</div>
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <input type="hidden" name="tab" value="activities">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher action, détail, utilisateur...">
            </div>
            <div class="col-md-3">
                <select name="user_id" class="form-select">
                    <option value="0">Tous les utilisateurs</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $user_id===$u['id']?'selected':'' ?>><?= e($u['prenom'] . ' ' . $u['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <a href="cleanup.php?tab=activities" class="btn btn-outline-danger w-100"><i class="fa-solid fa-trash me-1"></i>Nettoyer</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Détails</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune activité enregistrée.</td></tr>
                    <?php else: foreach ($activities as $a): ?>
                        <tr>
                            <td class="text-nowrap small"><?= date('d/m/Y H:i', strtotime($a['date_action'])) ?></td>
                            <td><?= e(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? 'Système')) ?></td>
                            <td><span class="badge bg-light text-dark"><?= e($a['action']) ?></span></td>
                            <td class="small"><?= e($a['details'] ?? '-') ?></td>
                            <td class="small text-muted"><?= e($a['ip_address'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header">Historique des connexions</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur</th>
                        <th>IP</th>
                        <th>Navigateur</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($connections)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune connexion enregistrée.</td></tr>
                    <?php else: foreach ($connections as $c): ?>
                        <tr>
                            <td class="text-nowrap small"><?= date('d/m/Y H:i', strtotime($c['date_connexion'])) ?></td>
                            <td><?= e(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? 'Inconnu')) ?><br>
                                <small class="text-muted"><?= e($c['email'] ?? '') ?></small></td>
                            <td class="small"><?= e($c['ip_address']) ?></td>
                            <td class="small text-muted" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($c['user_agent']) ?>"><?= e(mb_substr($c['user_agent'] ?? '', 0, 40)) ?></td>
                            <td>
                                <?php if ($c['succes']): ?>
                                    <span class="badge bg-success">Succès</span>
                                <?php else: ?>
                                    <span class="badge bg-danger" title="<?= e($c['raison']) ?>">Échec</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
