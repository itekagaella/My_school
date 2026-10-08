<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['eleve']);
$user = current_user();
$page_title = 'Clubs scolaires';
$active_menu = 'clubs';

$eleve = prepareQuery(
    'SELECT * FROM eleves WHERE user_id = :uid',
    ['uid'=>$user['id']]
)->fetch();

if (!$eleve) {
    set_flash('error', 'Profil élève introuvable.');
    header('Location: ' . BASE_URL . 'client/eleve/index.php');
    exit;
}

$eleve_id = $eleve['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Jeton de sécurité invalide.');
        header('Location: ' . BASE_URL . 'client/eleve/clubs.php');
        exit;
    }

    $club_id = (int)($_POST['club_id'] ?? 0);
    $action = clean_input($_POST['action'] ?? '');

    if ($club_id > 0) {
        $membre = prepareQuery(
            'SELECT * FROM club_membres WHERE club_id = :c AND eleve_id = :e',
            ['c'=>$club_id, 'e'=>$eleve_id]
        )->fetch();

        if ($action === 'rejoindre' && !$membre) {
            prepareQuery(
                'INSERT INTO club_membres (club_id, eleve_id, date_inscription) VALUES (:c, :e, NOW())',
                ['c'=>$club_id, 'e'=>$eleve_id]
            );
            log_activity('club.join', 'Inscription au club #' . $club_id);
            set_flash('success', 'Vous avez rejoint le club avec succès.');
        } elseif ($action === 'quitter' && $membre) {
            prepareQuery(
                'DELETE FROM club_membres WHERE club_id = :c AND eleve_id = :e',
                ['c'=>$club_id, 'e'=>$eleve_id]
            );
            log_activity('club.leave', 'Désinscription du club #' . $club_id);
            set_flash('info', 'Vous avez quitté le club.');
        }
    }

    header('Location: ' . BASE_URL . 'client/eleve/clubs.php');
    exit;
}

$search = clean_input(get('search', ''));

$where = "c.statut = 'actif'";
$params = [];
if ($search !== '') {
    $where .= ' AND LOWER(c.nom_club) LIKE :s';
    $params['s'] = '%' . strtolower($search) . '%';
}

$clubs = prepareQuery(
    "SELECT c.*, p.nom AS prof_nom, p.prenom AS prof_prenom,
            (SELECT COUNT(*) FROM club_membres cm WHERE cm.club_id = c.id) AS nb_membres
     FROM clubs c
     LEFT JOIN profs p ON p.id = c.prof_responsable_id
     WHERE $where
     ORDER BY c.nom_club",
    $params
)->fetchAll();

$membres_ids = [];
foreach (prepareQuery(
    'SELECT club_id FROM club_membres WHERE eleve_id = :e',
    ['e'=>$eleve_id]
)->fetchAll() as $m) {
    $membres_ids[(int)$m['club_id']] = true;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-people-group me-2 text-primary"></i>Clubs scolaires</h4>
    <form method="get" action="" class="filter-form">
        <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Rechercher un club...">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
</div>

<div class="row g-3">
    <?php if (empty($clubs)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center text-muted py-4">Aucun club trouvé.</div>
            </div>
        </div>
    <?php else: foreach ($clubs as $cl):
        $est_membre = isset($membres_ids[(int)$cl['id']]) ? true : false;
    ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 <?= $est_membre ? 'border-success' : '' ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <?php if ($cl['logo'] && file_exists(ROOT_PATH . $cl['logo'])): ?>
                            <img src="<?= BASE_URL . e($cl['logo']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:50%;" alt="">
                        <?php else: ?>
                            <div class="user-avatar" style="width:48px;height:48px;font-size:16px;"><i class="fa-solid fa-people-group"></i></div>
                        <?php endif; ?>
                        <div class="ms-3">
                            <div class="fw-semibold"><?= e($cl['nom_club']) ?></div>
                            <small class="text-muted">
                                <i class="fa-solid fa-user-tie me-1"></i><?= e(($cl['prof_prenom'] ?? '') . ' ' . ($cl['prof_nom'] ?? '-')) ?>
                            </small>
                        </div>
                    </div>
                    <p class="small text-muted mb-2"><?= e(mb_strimwidth($cl['description'] ?? '', 0, 120, '...')) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-info"><i class="fa-regular fa-user me-1"></i><?= (int)$cl['nb_membres'] ?> membre(s)</span>
                        <?php if ($est_membre): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Membre</span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <?php if ($est_membre): ?>
                            <form method="post" action="" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="quitter">
                                <input type="hidden" name="club_id" value="<?= $cl['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger w-100">Quitter le club</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="rejoindre">
                                <input type="hidden" name="club_id" value="<?= $cl['id'] ?>">
                                <button class="btn btn-sm btn-primary w-100">Rejoindre le club</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
