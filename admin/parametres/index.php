<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('parametres.view');

$page_title = 'Paramètres';
$active_menu = 'parametres';

$cles = [
    'nom_etablissement' => 'Nom de l\'établissement',
    'annee_scolaire' => 'Année scolaire',
    'adresse_etablissement' => 'Adresse',
    'telephone_etablissement' => 'Téléphone',
    'email_etablissement' => 'Email',
    'session_timeout' => 'Durée de session (secondes)',
    'max_upload_size' => 'Taille max upload (octets)',
    'sauvegarde_auto' => 'Sauvegarde automatique',
    'seuil_absences_alerte' => 'Seuil d\'absences avant alerte',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    foreach (array_keys($cles) as $cle) {
        if (array_key_exists($cle, $_POST)) {
            set_param($cle, trim($_POST[$cle]));
        }
    }
    log_activity('parametres.update', 'Mise à jour des paramètres système');
    set_flash('success', 'Paramètres enregistrés.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-gear me-2"></i>Paramètres généraux</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <?php foreach ($cles as $cle => $label): ?>
                        <div class="mb-3">
                            <label class="form-label"><?= e($label) ?></label>
                            <?php if (in_array($cle, ['sauvegarde_auto'])): ?>
                                <select name="<?= e($cle) ?>" class="form-select">
                                    <option value="true" <?= get_param($cle,'false')==='true'?'selected':'' ?>>Activée</option>
                                    <option value="false" <?= get_param($cle,'false')==='false'?'selected':'' ?>>Désactivée</option>
                                </select>
                            <?php else: ?>
                                <input type="text" name="<?= e($cle) ?>" class="form-control" value="<?= e(get_param($cle,'')) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-palette me-2"></i>Apparence</div>
            <div class="card-body">
                <p class="text-muted small">Choisissez un thème de couleurs pour l'interface.</p>
                <a href="theme.php?cle=theme&valeur=default" class="btn btn-sm btn-outline-secondary w-100 mb-2">Thème par défaut</a>
                <a href="theme.php?cle=theme&valeur=dark" class="btn btn-sm btn-outline-dark w-100 mb-2">Thème sombre</a>
                <a href="theme.php?cle=theme&valeur=blue" class="btn btn-sm btn-outline-primary w-100 mb-2">Thème bleu</a>
                <a href="theme.php?cle=theme&valeur=green" class="btn btn-sm btn-outline-success w-100 mb-2">Thème vert</a>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fa-solid fa-database me-2"></i>Sauvegarde</div>
            <div class="card-body">
                <a href="backup.php" class="btn btn-success w-100"><i class="fa-solid fa-download me-1"></i>Sauvegarder la base de données</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
