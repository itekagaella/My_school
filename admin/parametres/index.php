<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('parametres.view');

$page_title = 'Paramètres';
$active_menu = 'parametres';

$cles = [
    'nom_etablissement' => 'Nom de l\'établissement',
    'logo_etablissement' => 'Logo de l\'établissement (URL ou chemin relatif)',
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
        if (array_key_exists($cle, $_POST) && $cle !== 'logo_etablissement') {
            set_param($cle, trim($_POST[$cle]));
        }
    }
    // Upload du logo si fourni
    if (isset($_FILES['logo_etablissement_upload']) && $_FILES['logo_etablissement_upload']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['logo_etablissement_upload'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            $dir = __DIR__ . '/../../assets/uploads/logo/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $name = 'logo_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
            $dest = $dir . $name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $rel = 'assets/uploads/logo/' . $name;
                set_param('logo_etablissement', $rel);
            }
        }
    } elseif (array_key_exists('logo_etablissement', $_POST)) {
        set_param('logo_etablissement', trim($_POST['logo_etablissement']));
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
                <form method="post" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <?php foreach ($cles as $cle => $label): ?>
                        <div class="mb-3">
                            <label class="form-label"><?= e($label) ?></label>
                            <?php if (in_array($cle, ['sauvegarde_auto'])): ?>
                                <select name="<?= e($cle) ?>" class="form-select">
                                    <option value="true" <?= get_param($cle,'false')==='true'?'selected':'' ?>>Activée</option>
                                    <option value="false" <?= get_param($cle,'false')==='false'?'selected':'' ?>>Désactivée</option>
                                </select>
                            <?php elseif ($cle === 'annee_scolaire'): ?>
                                <?= select_annee_scolaire('annee_scolaire', get_param($cle,''), ['class' => 'form-select']) ?>
                            <?php elseif ($cle === 'logo_etablissement'): ?>
                                <?php $logoPreview = logo_etablissement_url(); ?>
                                <?php if ($logoPreview): ?>
                                    <div class="mb-2">
                                        <img src="<?= e($logoPreview) ?>" alt="Logo actuel" style="height:60px;max-width:200px;object-fit:contain;border:1px solid #eee;padding:4px;border-radius:8px;">
                                    </div>
                                <?php endif; ?>
                                <input type="text" name="<?= e($cle) ?>" class="form-control mb-2" value="<?= e(get_param($cle,'')) ?>" placeholder="assets/uploads/logo/logo.png ou https://...">
                                <input type="file" name="logo_etablissement_upload" class="form-control" accept="image/*,.svg">
                                <small class="text-muted">Vous pouvez soit saisir un chemin/URL, soit uploader un fichier.</small>
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
                <p class="text-muted small">Gérez l'apparence de l'interface depuis la page dédiée aux thèmes.</p>
                <a href="theme.php" class="btn btn-outline-primary w-100"><i class="fa-solid fa-paintbrush me-1"></i>Gérer les thèmes</a>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fa-solid fa-database me-2"></i>Sauvegarde</div>
            <div class="card-body">
                <a href="backup.php" class="btn btn-success w-100"><i class="fa-solid fa-download me-1"></i>Sauvegarder la base de données</a>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Accents</div>
            <div class="card-body">
                <p class="text-muted small">Répare les textes enregistrés avec des accents cassés (« Ã© » au lieu de « é »).</p>
                <a href="<?= BASE_URL ?>reparer_accents.php" class="btn btn-outline-primary w-100"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Réparer les accents</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
