<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
$role = $user['role'];

$role_redirects = [
    'eleve'      => 'client/eleve/communications.php',
    'prof'       => 'client/prof/communications.php',
    'personnel'  => 'client/personnel/communications.php',
    'admin'      => 'admin/communications/index.php',
];
$fallback = $role_redirects[$role] ?? 'index.php';

$id = (int)get('id', 0);
$comm = prepareQuery(
    "SELECT c.*, u.nom, u.prenom
     FROM communications c
     LEFT JOIN utilisateurs u ON u.id = c.auteur_id
     WHERE c.id = :id",
    ['id' => $id]
)->fetch();

if (!$comm || $comm['statut'] !== 'publie') {
    set_flash('error', 'Communication introuvable ou non publiée.');
    header('Location: ' . BASE_URL . $fallback);
    exit;
}

// Visibilité : admin toujours, sinon destinataires 'tous' / role:{rôle} / classe:{ma classe}
$dests = array_map('trim', explode(',', (string)$comm['destinataires']));
$visible = false;
if ($role === 'admin') {
    $visible = true;
} elseif (in_array('tous', $dests, true) || in_array('role:' . $role, $dests, true)) {
    $visible = true;
} elseif ($role === 'eleve') {
    $eleve = prepareQuery('SELECT classe_id FROM eleves WHERE user_id = :u', ['u' => $user['id']])->fetch();
    if ($eleve && $eleve['classe_id'] && in_array('classe:' . (int)$eleve['classe_id'], $dests, true)) {
        $visible = true;
    }
}

if (!$visible) {
    set_flash('error', 'Cette communication ne vous est pas destinée.');
    header('Location: ' . BASE_URL . $fallback);
    exit;
}

// Marquer la notification correspondante comme lue
prepareQuery(
    'UPDATE notifications SET lu = TRUE
     WHERE user_id = :u AND lu = FALSE AND lien LIKE :l',
    ['u' => $user['id'], 'l' => '%communication.php?id=' . $id]
);

$page_title = $comm['titre'];
$active_menu = 'communications';

$type_badges = ['annonce' => 'bg-primary', 'alerte' => 'bg-danger', 'info' => 'bg-info'];
$type_icons  = ['annonce' => 'fa-megaphone', 'alerte' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];

require_once __DIR__ . '/../includes/header.php';
if ($role === 'admin') {
    require_once __DIR__ . '/../includes/sidebar_admin.php';
} else {
    require_once __DIR__ . '/../includes/sidebar_client.php';
}
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>Communication</h4>
    <a href="<?= BASE_URL . e($fallback) ?>" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Retour
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-newspaper me-2"></i>Contenu</span>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comm['date_publication'])) ?></small>
            </div>
            <div class="card-body">
                <h5 class="mb-2">
                    <span class="badge <?= $type_badges[$comm['type']] ?? 'bg-secondary' ?> me-2"><?= e(ucfirst($comm['type'])) ?></span>
                    <?= e($comm['titre']) ?>
                </h5>
                <div class="text-muted small mb-3">
                    <i class="fa-solid fa-user me-1"></i><?= e(trim(($comm['prenom'] ?? '') . ' ' . ($comm['nom'] ?? ''), ' ')) ?: 'Administration' ?>
                </div>
                <div style="white-space:pre-wrap;"><?= e($comm['contenu']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-circle-info me-2"></i>Détails</div>
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:45%;">Type</td>
                        <td><span class="badge <?= $type_badges[$comm['type']] ?? 'bg-secondary' ?>"><?= e(ucfirst($comm['type'])) ?></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Auteur</td>
                        <td><?= e(trim(($comm['prenom'] ?? '') . ' ' . ($comm['nom'] ?? ''), ' ')) ?: 'Administration' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Publication</td>
                        <td><?= date('d/m/Y H:i', strtotime($comm['date_publication'])) ?></td>
                    </tr>
                    <?php if ($role === 'admin'): ?>
                        <tr>
                            <td class="text-muted">Destinataires</td>
                            <td><?= e($comm['destinataires']) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
