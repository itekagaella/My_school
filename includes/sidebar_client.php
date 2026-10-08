<?php
/**
 * Sidebar de l'interface client (Élève / Prof / Personnel)
 * Paramètre attendu : $active_menu (optionnel) - clé du menu actif
 */
$user = current_user();
$role = $user['role'] ?? '';
if (!isset($active_menu)) $active_menu = 'dashboard';

$nb_notif = 0;
try {
    $r = prepareQuery(
        'SELECT COUNT(*) AS nb FROM notifications WHERE user_id = :uid AND lu = FALSE',
        ['uid' => $user['id']]
    )->fetch();
    $nb_notif = $r['nb'] ?? 0;
} catch (Exception $e) { $nb_notif = 0; }

$role_label = ['eleve' => 'Élève', 'prof' => 'Professeur', 'personnel' => 'Personnel'];
$role_icon = ['eleve' => 'fa-user-graduate', 'prof' => 'fa-chalkboard-user', 'personnel' => 'fa-briefcase'];
$role_base = ['eleve' => 'client/eleve', 'prof' => 'client/prof', 'personnel' => 'client/personnel'];

function client_menu_item(string $key, string $label, string $icon, string $href, string $active): string {
    $isActive = ($active === $key) ? 'active' : '';
    return '<li class="nav-item"><a class="nav-link ' . $isActive . '" href="' . BASE_URL . $href . '">'
        . '<i class="fa-solid ' . $icon . ' menu-icon"></i><span class="menu-label">' . $label . '</span></a></li>';
}
?>
<aside class="sidebar sidebar-client" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL . $role_base[$role] ?>/index.php" class="sidebar-brand">
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
            <span class="user-role"><i class="fa-solid <?= $role_icon[$role] ?>"></i> <?= $role_label[$role] ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-section">Mon espace</li>
            <?= client_menu_item('dashboard', 'Tableau de bord', 'fa-gauge-high', $role_base[$role] . '/index.php', $active_menu) ?>
            <?= client_menu_item('profil', 'Mon profil', 'fa-user', 'client/profil/index.php', $active_menu) ?>

            <li class="nav-section">Consultation</li>
            <?= client_menu_item('horaires', 'Emploi du temps', 'fa-calendar-days', $role_base[$role] . '/horaires.php', $active_menu) ?>

            <?php if ($role === 'eleve'): ?>
                <?= client_menu_item('notes', 'Mes notes', 'fa-file-pen', 'client/eleve/notes.php', $active_menu) ?>
                <?= client_menu_item('presences', 'Mes présences', 'fa-clipboard-check', 'client/eleve/presences.php', $active_menu) ?>
                <?= client_menu_item('clubs', 'Clubs scolaires', 'fa-people-group', 'client/eleve/clubs.php', $active_menu) ?>
            <?php endif; ?>

            <?php if ($role === 'prof'): ?>
                <?= client_menu_item('eleves', 'Mes élèves', 'fa-users', 'client/prof/eleves.php', $active_menu) ?>
                <?= client_menu_item('notes', 'Gestion des notes', 'fa-file-pen', 'client/prof/notes.php', $active_menu) ?>
                <?= client_menu_item('presences', 'Gestion des présences', 'fa-clipboard-check', 'client/prof/presences.php', $active_menu) ?>
            <?php endif; ?>

            <?php if ($role === 'personnel'): ?>
                <?= client_menu_item('eleves', 'Élèves & classes', 'fa-user-graduate', 'client/personnel/eleves.php', $active_menu) ?>
            <?php endif; ?>

            <li class="nav-section">Communication</li>
            <?= client_menu_item('communications', 'Communications', 'fa-bullhorn', $role_base[$role] . '/communications.php', $active_menu) ?>
            <?= client_menu_item('documents', 'Documents', 'fa-folder-open', $role_base[$role] . '/documents.php', $active_menu) ?>
            <?= client_menu_item('chat', 'Chat', 'fa-comments', 'client/chat.php', $active_menu) ?>
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
            <a href="<?= BASE_URL ?>client/profil/index.php" class="btn">
                <i class="fa-solid fa-user"></i> <span class="d-none d-md-inline"><?= e($user['prenom']) ?></span>
            </a>
            <a href="<?= BASE_URL ?>logout.php" class="btn" title="Déconnexion">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </nav>
    <div class="content-area">
