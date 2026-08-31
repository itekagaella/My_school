<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('logs.delete');

$page_title = 'Nettoyage des journaux';
$active_menu = 'logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $type = $_POST['type'] ?? '';
    $anciennete = max(1, (int)($_POST['anciennete'] ?? 30));

    if ($type === 'activites') {
        prepareQuery('DELETE FROM journal_activites WHERE date_action < (NOW() - :j * INTERVAL \'1 day\')', ['j'=>$anciennete]);
        set_flash('success', 'Journal des activités nettoyé (antérieur à ' . $anciennete . ' jours).');
    } elseif ($type === 'connexions') {
        prepareQuery('DELETE FROM historique_connexions WHERE date_connexion < (NOW() - :j * INTERVAL \'1 day\')', ['j'=>$anciennete]);
        set_flash('success', 'Historique des connexions nettoyé.');
    } else {
        set_flash('error', 'Type de journal invalide.');
    }
    log_activity('logs.cleanup', 'Nettoyage des journaux (' . $type . ')');
    header('Location: cleanup.php');
    exit;
}

$nb_activites = prepareQuery('SELECT COUNT(*) AS nb FROM journal_activites')->fetch()['nb'] ?? 0;
$nb_connexions = prepareQuery('SELECT COUNT(*) AS nb FROM historique_connexions')->fetch()['nb'] ?? 0;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-broom me-2"></i>Nettoyage des journaux</div>
    <div class="card-body">
        <div class="row text-center mb-4">
            <div class="col-md-6"><div class="card bg-light"><div class="card-body">
                <div class="display-6 fw-bold"><?= $nb_activites ?></div>
                <div class="text-muted">Activités journalisées</div>
            </div></div></div>
            <div class="col-md-6"><div class="card bg-light"><div class="card-body">
                <div class="display-6 fw-bold"><?= $nb_connexions ?></div>
                <div class="text-muted">Connexions historisées</div>
            </div></div></div>
        </div>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Type de journal</label>
                    <select name="type" class="form-select">
                        <option value="activites">Journal des activités</option>
                        <option value="connexions">Historique des connexions</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Conserver les derniers (jours)</label>
                    <input type="number" name="anciennete" class="form-control" value="30" min="1">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-danger w-100"><i class="fa-solid fa-broom me-1"></i>Nettoyer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
