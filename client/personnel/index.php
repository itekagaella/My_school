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
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-user-graduate text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Élèves</div>
                        <div class="fs-4 fw-bold"><?= $nb_eleves ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-chalkboard-user text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Professeurs</div>
                        <div class="fs-4 fw-bold"><?= $nb_profs ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-school text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Classes</div>
                        <div class="fs-4 fw-bold"><?= $nb_classes ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="fa-solid fa-clipboard-check text-warning"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Présences du jour</div>
                        <div class="fs-4 fw-bold"><?= $nb_presences ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fa-solid fa-folder-open me-2"></i>Documents disponibles</h6>
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
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fa-solid fa-bullhorn me-2"></i>Dernières communications</h6>
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
                                <small class="text-muted"><?= mb_substr($comm['contenu'],0,80) ?><?= mb_strlen($comm['contenu'])>80?'...':'' ?></small>
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
