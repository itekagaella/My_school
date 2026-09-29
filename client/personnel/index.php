<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['personnel']);
$user = current_user();
$page_title = 'Tableau de bord';
$active_menu = 'dashboard';

$pers = prepareQuery(
    'SELECT * FROM personnel WHERE user_id = :u',
    ['u' => $user['id']]
)->fetch();

if (!$pers) {
    set_flash('error', 'Profil personnel introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$nb_eleves = prepareQuery("SELECT COUNT(*) AS nb FROM eleves WHERE statut='actif'")->fetch()['nb'] ?? 0;
$nb_profs = prepareQuery("SELECT COUNT(*) AS nb FROM profs WHERE statut='actif'")->fetch()['nb'] ?? 0;
$nb_classes = prepareQuery("SELECT COUNT(*) AS nb FROM classes")->fetch()['nb'] ?? 0;
$nb_presences = prepareQuery(
    "SELECT COUNT(*) AS nb FROM presences WHERE created_at::date = CURRENT_DATE AND statut IN ('absent','retard')"
)->fetch()['nb'] ?? 0;
$nb_docs = prepareQuery("SELECT COUNT(*) AS nb FROM documents WHERE statut='actif'")->fetch()['nb'] ?? 0;

$dernieres_comms = prepareQuery(
    "SELECT * FROM communications WHERE statut='publie' ORDER BY date_publication DESC LIMIT 5"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-label">Élèves</div>
                <div class="stat-number"><?= $nb_eleves ?></div>
            </div>
            <i class="fa-solid fa-user-graduate stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-green">
            <div>
                <div class="stat-label">Professeurs</div>
                <div class="stat-number"><?= $nb_profs ?></div>
            </div>
            <i class="fa-solid fa-chalkboard-user stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-purple">
            <div>
                <div class="stat-label">Classes</div>
                <div class="stat-number"><?= $nb_classes ?></div>
            </div>
            <i class="fa-solid fa-school stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-red">
            <div>
                <div class="stat-label">Présences du jour</div>
                <div class="stat-number"><?= $nb_presences ?></div>
            </div>
            <i class="fa-solid fa-clipboard-check stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <span><i class="fa-solid fa-folder-open me-2"></i>Documents disponibles</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-file-lines text-secondary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Documents actifs</div>
                        <div class="fs-4 fw-bold"><?= $nb_docs ?></div>
                    </div>
                    <a href="<?= BASE_URL ?>client/personnel/documents.php" class="btn btn-sm btn-outline-primary ms-3">Voir</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <span><i class="fa-solid fa-bullhorn me-2"></i>Dernières communications</span>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($dernieres_comms)): ?>
                    <div class="text-center text-muted py-4">Aucune communication</div>
                <?php else: foreach ($dernieres_comms as $comm):
                    $type_icon = ['annonce'=>'fa-megaphone','alerte'=>'fa-triangle-exclamation','info'=>'fa-circle-info'];
                    $type_color = ['annonce'=>'primary','alerte'=>'danger','info'=>'info'];
                ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-start">
                            <i class="fa-solid <?= $type_icon[$comm['type']] ?? 'fa-info-circle' ?> text-<?= $type_color[$comm['type']] ?? 'info' ?> me-2 mt-1"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($comm['titre']) ?></div>
                                <small class="text-muted"><?= e(mb_substr($comm['contenu'],0,80)) ?><?= mb_strlen($comm['contenu'])>80?'...':'' ?></small>
                                <div><small class="text-muted"><?= date('d/m/Y H:i', strtotime($comm['date_publication'])) ?></small></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
