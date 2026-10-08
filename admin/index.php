<?php
/**
 * Tableau de bord administrateur
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'Tableau de bord';
$active_menu = 'dashboard';

// Sauvegarde automatique quotidienne (paramètre "sauvegarde_auto")
// Silencieuse : un échec ne doit jamais bloquer le tableau de bord.
if (function_exists('backup_auto')) {
    backup_auto();
}

// Statistiques via procédure stockée
$stats = prepareQuery('SELECT * FROM sp_dashboard_stats()')->fetchAll()[0] ?? [];
if (!$stats) $stats = ['total_eleves'=>0,'total_profs'=>0,'total_classes'=>0,'total_communications'=>0,'total_documents'=>0,'total_clubs'=>0,'absences_mois'=>0,'retards_mois'=>0];

// Dernières activités
$activites = prepareQuery(
    'SELECT j.action, j.details, j.date_action, u.prenom, u.nom, u.role
     FROM journal_activites j LEFT JOIN utilisateurs u ON u.id = j.user_id
     ORDER BY j.date_action DESC LIMIT 10'
)->fetchAll();

// Derniers élèves inscrits
$eleves_recents = prepareQuery(
    'SELECT e.matricule, e.nom, e.prenom, e.date_naissance, c.nom_classe, e.created_at
     FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
     ORDER BY e.created_at DESC LIMIT 5'
)->fetchAll();

// Répartition des élèves par classe
$eleves_par_classe = prepareQuery(
    'SELECT c.nom_classe, COUNT(e.id) AS total
     FROM classes c LEFT JOIN eleves e ON e.classe_id = c.id
     GROUP BY c.nom_classe ORDER BY ' . classes_order_sql('c.nom_classe')
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<!-- Cartes statistiques -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-label">Élèves actifs</div>
                <div class="stat-number"><?= number_format($stats['total_eleves']) ?></div>
            </div>
            <i class="fa-solid fa-user-graduate stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-green">
            <div>
                <div class="stat-label">Professeurs</div>
                <div class="stat-number"><?= number_format($stats['total_profs']) ?></div>
            </div>
            <i class="fa-solid fa-chalkboard-user stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-purple">
            <div>
                <div class="stat-label">Classes</div>
                <div class="stat-number"><?= number_format($stats['total_classes']) ?></div>
            </div>
            <i class="fa-solid fa-school stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-orange">
            <div>
                <div class="stat-label">Communications</div>
                <div class="stat-number"><?= number_format($stats['total_communications']) ?></div>
            </div>
            <i class="fa-solid fa-bullhorn stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-teal">
            <div>
                <div class="stat-label">Documents</div>
                <div class="stat-number"><?= number_format($stats['total_documents']) ?></div>
            </div>
            <i class="fa-solid fa-folder-open stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-red">
            <div>
                <div class="stat-label">Clubs</div>
                <div class="stat-number"><?= number_format($stats['total_clubs']) ?></div>
            </div>
            <i class="fa-solid fa-people-group stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-orange">
            <div>
                <div class="stat-label">Absences (mois)</div>
                <div class="stat-number"><?= number_format($stats['absences_mois']) ?></div>
            </div>
            <i class="fa-solid fa-user-xmark stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-label">Retards (mois)</div>
                <div class="stat-number"><?= number_format($stats['retards_mois']) ?></div>
            </div>
            <i class="fa-solid fa-clock stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Dernières activités -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-clock-rotate-left me-2"></i>Activités récentes</span>
                <a href="logs/activities.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activites)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">Aucune activité récente.</td></tr>
                            <?php else: foreach ($activites as $a): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <?= e(($a['prenom'] ?? 'Système') . ' ' . ($a['nom'] ?? '')) ?>
                                        <?php if ($a['role']): ?><br><small class="text-muted"><?= e($a['role']) ?></small><?php endif; ?>
                                    </td>
                                    <td title="<?= e($a['details']) ?>"><?= e($a['action']) ?></td>
                                    <td class="text-nowrap small text-muted"><?= date('d/m H:i', strtotime($a['date_action'])) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Nouveaux élèves -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-user-graduate me-2"></i>Derniers élèves inscrits</span>
                <a href="eleves/index.php" class="btn btn-sm btn-outline-primary">Détails</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Matricule</th><th>Nom</th><th>Classe</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($eleves_recents)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">Aucun élève inscrit.</td></tr>
                            <?php else: foreach ($eleves_recents as $el): ?>
                                <tr>
                                    <td class="text-nowrap small"><?= e($el['matricule']) ?></td>
                                    <td><?= e($el['prenom'] . ' ' . $el['nom']) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= e($el['nom_classe'] ?? '-') ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Répartition élèves par classe -->
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-chart-column me-2"></i>Effectifs par classe</div>
            <div class="card-body">
                <?php if (empty($eleves_par_classe)): ?>
                    <p class="text-muted mb-0">Aucune donnée disponible.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($eleves_par_classe as $c): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="p-3 border rounded text-center">
                                    <div class="text-muted small"><?= e($c['nom_classe']) ?></div>
                                    <div class="h3 mb-0 text-primary"><?= $c['total'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
