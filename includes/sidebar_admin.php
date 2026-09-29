<?php
/**
 * Sidebar de l'interface administrateur
 * Paramètre attendu : $active_menu (optionnel) - clé du menu actif
 */
$user = current_user();
if (!isset($active_menu)) $active_menu = 'dashboard';

// Nombre de notifications non lues
$nb_notif = 0;
try {
    $r = prepareQuery(
        'SELECT COUNT(*) AS nb FROM notifications WHERE user_id = :uid AND lu = FALSE',
        ['uid' => $user['id']]
    )->fetch();
    $nb_notif = $r['nb'] ?? 0;
} catch (Exception $e) { $nb_notif = 0; }

function admin_menu_item(string $key, string $label, string $icon, string $href, string $active): string {
    $isActive = ($active === $key) ? 'active' : '';
    return '<li class="nav-item"><a class="nav-link ' . $isActive . '" href="' . BASE_URL . $href . '">'
        . '<i class="fa-solid ' . $icon . ' menu-icon"></i><span class="menu-label">' . $label . '</span></a></li>';
}
?>
<aside class="sidebar sidebar-admin" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>admin/index.php" class="sidebar-brand">
            <span class="grad-cap"><i class="fa-solid fa-graduation-cap"></i></span>
            <span class="brand-text">My_School</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if (!empty($user['avatar']) && file_exists(ROOT_PATH . $user['avatar'])): ?>
                <img src="<?= BASE_URL . e($user['avatar']) ?>" alt="avatar">
            <?php else: ?>
                <span><?= strtoupper(mb_substr($user['prenom'] ?? 'A', 0, 1) . mb_substr($user['nom'] ?? 'D', 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?= e($user['prenom'] . ' ' . $user['nom']) ?></span>
            <span class="user-role"><i class="fa-solid fa-shield-halved"></i> Administrateur</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-section">Principal</li>
            <?= admin_menu_item('dashboard', 'Tableau de bord', 'fa-gauge-high', 'admin/index.php', $active_menu) ?>

            <li class="nav-section">Gestion utilisateurs</li>
            <?= admin_menu_item('users_users', 'Utilisateurs', 'fa-users', 'admin/users/index.php', $active_menu) ?>
            <?= admin_menu_item('users_roles', 'Rôles & Permissions', 'fa-user-shield', 'admin/users/roles.php', $active_menu) ?>
            <?= admin_menu_item('logs', 'Journal & Connexions', 'fa-clock-rotate-left', 'admin/logs/activities.php', $active_menu) ?>
            <?= admin_menu_item('logs_cleanup', 'Nettoyage', 'fa-broom', 'admin/logs/cleanup.php', $active_menu) ?>

            <li class="nav-section">Gestion des données</li>
            <?= admin_menu_item('classes', 'Classes', 'fa-school', 'admin/eleves/classes.php', $active_menu) ?>
            <?= admin_menu_item('matieres', 'Matières', 'fa-book', 'admin/eleves/matieres.php', $active_menu) ?>
            <?= admin_menu_item('eleves', 'Élèves', 'fa-user-graduate', 'admin/eleves/index.php', $active_menu) ?>
            <?= admin_menu_item('profs', 'Professeurs', 'fa-chalkboard-user', 'admin/profs/index.php', $active_menu) ?>
            <?= admin_menu_item('personnel', 'Personnel', 'fa-briefcase', 'admin/personnel/index.php', $active_menu) ?>
            <?= admin_menu_item('horaires', 'Horaires', 'fa-calendar-days', 'admin/horaires/index.php', $active_menu) ?>
            <?= admin_menu_item('notes', 'Notes', 'fa-file-pen', 'admin/notes/index.php', $active_menu) ?>
            <?= admin_menu_item('presences', 'Présences', 'fa-clipboard-check', 'admin/presences/index.php', $active_menu) ?>
            <?= admin_menu_item('archives', 'Archives', 'fa-box-archive', 'admin/eleves/archive.php', $active_menu) ?>

            <li class="nav-section">Clubs & Communications</li>
            <?= admin_menu_item('clubs', 'Clubs scolaires', 'fa-people-group', 'admin/clubs/index.php', $active_menu) ?>
            <?= admin_menu_item('communications', 'Communications', 'fa-bullhorn', 'admin/communications/index.php', $active_menu) ?>

            <li class="nav-section">Gestion documentaire</li>
            <?= admin_menu_item('documents', 'Documents', 'fa-folder-open', 'admin/documents/index.php', $active_menu) ?>
            <?= admin_menu_item('commentaires', 'Commentaires', 'fa-comment-dots', 'admin/commentaires/index.php', $active_menu) ?>

            <li class="nav-section">Rapports & Statistiques</li>
            <?= admin_menu_item('rapports', 'Rapports', 'fa-file-lines', 'admin/rapports/index.php', $active_menu) ?>
            <?= admin_menu_item('stats', 'Statistiques', 'fa-chart-column', 'admin/stats/index.php', $active_menu) ?>

            <li class="nav-section">Administration</li>
            <?= admin_menu_item('parametres', 'Paramètres', 'fa-gears', 'admin/parametres/index.php', $active_menu) ?>
            <?= admin_menu_item('themes', 'Thèmes', 'fa-palette', 'admin/parametres/theme.php', $active_menu) ?>
            <?= admin_menu_item('backup', 'Sauvegarde', 'fa-database', 'admin/parametres/backup.php', $active_menu) ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>api/notifications.php">
                    <i class="fa-solid fa-bell menu-icon"></i><span class="menu-label">Notifications</span>
                    <?php if ($nb_notif > 0): ?><span class="badge bg-danger"><?= $nb_notif ?></span><?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>logout.php">
                    <i class="fa-solid fa-right-from-bracket menu-icon"></i><span class="menu-label">Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="main-content">
    <nav class="topbar">
        <button class="btn btn-link topbar-toggle d-lg-none" id="topbarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title"><?= e($page_title ?? '') ?></div>
        <div class="topbar-actions ms-auto d-flex align-items-center">
            <a href="<?= BASE_URL ?>api/notifications.php" class="btn position-relative me-3">
                <i class="fa-solid fa-bell"></i>
                <?php if ($nb_notif > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $nb_notif ?></span><?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>admin/profil.php" class="btn">
                <i class="fa-solid fa-user"></i> <span class="d-none d-md-inline"><?= e($user['prenom']) ?></span>
            </a>
            <a href="<?= BASE_URL ?>logout.php" class="btn" title="Déconnexion">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </nav>
    <div class="content-area">
