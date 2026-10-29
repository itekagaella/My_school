<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('parametres.view');

$page_title = 'Thèmes';
$active_menu = 'themes';

// Compatibilité avec les anciens liens
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cle']) && $_GET['cle'] === 'theme' && isset($_GET['valeur'])) {
    if (csrf_verify($_GET['csrf_token'] ?? null)) {
        $valeur = clean_input($_GET['valeur']);
        $cssPath = 'assets/css/theme-' . preg_replace('/[^a-z]/', '', strtolower($valeur)) . '.css';
        prepareQuery('UPDATE themes SET actif = FALSE');
        prepareQuery('UPDATE themes SET actif = TRUE WHERE css_path = :p', ['p' => $cssPath]);
        log_activity('parametres.theme', 'Changement de thème : ' . $valeur);
        set_flash('success', 'Thème appliqué.');
    }
    header('Location: theme.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $valeur = clean_input($_POST['valeur'] ?? 'default');
    $cssPath = 'assets/css/theme-' . preg_replace('/[^a-z]/', '', strtolower($valeur)) . '.css';
    prepareQuery('UPDATE themes SET actif = FALSE');
    prepareQuery('UPDATE themes SET actif = TRUE WHERE css_path = :p', ['p' => $cssPath]);
    log_activity('parametres.theme', 'Changement de thème : ' . $valeur);
    set_flash('success', 'Thème appliqué.');
    header('Location: theme.php');
    exit;
}

$themes = [
    ['key' => 'default', 'nom' => 'Thème par défaut', 'desc' => 'Couleurs classiques et neutres', 'class' => 'btn-outline-secondary'],
    ['key' => 'dark',    'nom' => 'Thème sombre',       'desc' => 'Interface en mode sombre',      'class' => 'btn-outline-dark'],
    ['key' => 'blue',    'nom' => 'Thème bleu',         'desc' => 'Ambiance professionnelle bleue','class' => 'btn-outline-primary'],
    ['key' => 'green',   'nom' => 'Thème vert',         'desc' => 'Ambiance apaisante verte',     'class' => 'btn-outline-success'],
];

$actif = prepareQuery("SELECT css_path FROM themes WHERE actif = TRUE LIMIT 1")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-palette me-2"></i>Apparence & Thèmes</div>
            <div class="card-body">
                <p class="text-muted mb-4">Choisissez le thème de couleurs à appliquer à l'ensemble de l'interface.</p>
                <div class="row g-3">
                    <?php foreach ($themes as $t): ?>
                        <?php $css = 'assets/css/theme-' . $t['key'] . '.css'; $isActive = ($actif === $css); ?>
                        <div class="col-md-6">
                            <div class="card h-100 <?= $isActive ? 'border-primary shadow-sm' : '' ?>">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0"><?= e($t['nom']) ?></h6>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-primary"><i class="fa-solid fa-check me-1"></i>Actif</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small flex-grow-1"><?= e($t['desc']) ?></p>
                                    <?php if ($isActive): ?>
                                        <button class="btn btn-sm btn-primary" disabled><i class="fa-solid fa-check me-1"></i>Déjà appliqué</button>
                                    <?php else: ?>
                                        <form method="post" action="">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="valeur" value="<?= e($t['key']) ?>">
                                            <button type="submit" class="btn btn-sm <?= $t['class'] ?>"><i class="fa-solid fa-paintbrush me-1"></i>Appliquer ce thème</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-circle-info me-2"></i>À propos</div>
            <div class="card-body">
                <p class="small text-muted mb-0">Le thème sélectionné est enregistré en base de données et s'applique à tous les utilisateurs de l'application.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
