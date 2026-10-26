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

$role_label = role_labels_court();
$role_icon = role_icons();
$role_base = role_base_dir($role);
$home = role_home($role);

function client_menu_item(string $key, string $label, string $icon, string $href, string $active): string {
    $isActive = ($active === $key) ? 'active' : '';
    return '<li class="nav-item"><a class="nav-link ' . $isActive . '" href="' . BASE_URL . $href . '">'
        . '<i class="fa-solid ' . $icon . ' menu-icon"></i><span class="menu-label">' . $label . '</span></a></li>';
}
?>
<aside class="sidebar sidebar-client" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL . $home ?>" class="sidebar-brand">
            <span class="grad-cap"><i class="fa-solid fa-graduation-cap"></i></span>
            <span class="brand-text">My_School</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php $avatarUrl = photo_profil_url($user['avatar'] ?? null); ?>
            <?php if ($avatarUrl): ?>
                <img src="<?= e($avatarUrl) ?>" alt="avatar">
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
            <?= client_menu_item('dashboard', 'Tableau de bord', 'fa-gauge-high', $home, $active_menu) ?>
            <?= client_menu_item('profil', 'Mon profil', 'fa-user', 'client/profil/index.php', $active_menu) ?>

            <li class="nav-section">Consultation</li>
            <?= client_menu_item('horaires', 'Emploi du temps', 'fa-calendar-days', $role_base . '/horaires.php', $active_menu) ?>

            <?php if ($role === 'eleve'): ?>
                <?= client_menu_item('notes', 'Mes notes', 'fa-file-pen', 'client/eleve/notes.php', $active_menu) ?>
                <?= client_menu_item('presences', 'Mes présences', 'fa-clipboard-check', 'client/eleve/presences.php', $active_menu) ?>
                <?= client_menu_item('finance', 'Finance', 'fa-wallet', 'client/eleve/finance.php', $active_menu) ?>
                <?= client_menu_item('discipline', 'Discipline', 'fa-scale-balanced', 'client/eleve/discipline.php', $active_menu) ?>
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

            <?php if ($role === 'surveillant'): ?>
                <li class="nav-section">Surveillance</li>
                <?= client_menu_item('eleves', 'Élèves & classes', 'fa-user-graduate', 'client/personnel/eleves.php', $active_menu) ?>
                <?= client_menu_item('presences', 'Suivi des présences', 'fa-clipboard-check', 'client/surveillant/presences.php', $active_menu) ?>
                <?= client_menu_item('incidents', 'Incidents signalés', 'fa-triangle-exclamation', 'client/surveillant/incidents.php', $active_menu) ?>
            <?php endif; ?>

            <?php if ($role === 'finance'): ?>
                <li class="nav-section">Finances</li>
                <?= client_menu_item('eleves', 'Élèves & soldes', 'fa-user-graduate', 'client/finance/eleves.php', $active_menu) ?>
                <?= client_menu_item('frais', 'Frais scolaires', 'fa-file-invoice-dollar', 'client/finance/frais.php', $active_menu) ?>
                <?= client_menu_item('paiements', 'Paiements & reçus', 'fa-coins', 'client/finance/paiements.php', $active_menu) ?>
                <?= client_menu_item('rapport', 'Rapport financier', 'fa-chart-line', 'client/finance/rapport.php', $active_menu) ?>
            <?php endif; ?>

            <?php if ($role === 'discipline'): ?>
                <li class="nav-section">Discipline</li>
                <?= client_menu_item('eleves', 'Élèves', 'fa-user-graduate', 'client/discipline/eleves.php', $active_menu) ?>
                <?= client_menu_item('incidents', 'Incidents', 'fa-triangle-exclamation', 'client/discipline/incidents.php', $active_menu) ?>
                <?= client_menu_item('sanctions', 'Sanctions', 'fa-gavel', 'client/discipline/sanctions.php', $active_menu) ?>
                <?= client_menu_item('convocations', 'Convocations', 'fa-envelope', 'client/discipline/convocations.php', $active_menu) ?>
            <?php endif; ?>

            <li class="nav-section">Communication</li>
            <?= client_menu_item('communications', 'Communications', 'fa-bullhorn', $role_base . '/communications.php', $active_menu) ?>
            <?= client_menu_item('documents', 'Documents', 'fa-folder-open', $role_base . '/documents.php', $active_menu) ?>
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
