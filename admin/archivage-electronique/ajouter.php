<?php
/**
 * Archivage électronique / GED - Ajouter
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$page_title = 'Verser un document - Archivage électronique';
$active_menu = 'archivage_electronique';

$types = ['PV' => 'Procès-verbal', 'Courrier' => 'Courrier', 'Rapport' => 'Rapport', 'Decision' => 'Décision', 'Liste' => 'Liste officielle', 'Autre' => 'Autre'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        $titre = clean_input($_POST['titre'] ?? '');
        $type_document = clean_input($_POST['type_document'] ?? '');
        $annee_scolaire = clean_input($_POST['annee_scolaire'] ?? '');
        $date_document = clean_input($_POST['date_document'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        $mots_cles = clean_input($_POST['mots_cles'] ?? '');

        if (empty($titre) || empty($type_document)) {
            set_flash('error', 'Le titre et le type de document sont obligatoires.');
        } elseif (empty($_FILES['fichier']['name'])) {
            set_flash('error', 'Veuillez sélectionner un fichier à verser.');
        } else {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
            $ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                set_flash('error', 'Type de fichier non autorisé.');
            } else {
                $uploadDir = ROOT_PATH . 'assets/uploads/archives-electroniques/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = 'GED_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['fichier']['tmp_name'], $filepath)) {
                    // Générer une référence
                    $ref = 'GED-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    while (prepareQuery('SELECT id FROM archives_electroniques WHERE reference = :r', ['r' => $ref])->fetch()) {
                        $ref = 'GED-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    }

                    try {
                        prepareQuery(
                            "INSERT INTO archives_electroniques (reference, titre, type_document, annee_scolaire, date_document, description, mots_cles, fichier_nom, fichier_chemin, fichier_type, fichier_taille, vers_par)
                             VALUES (:ref, :titre, :type, :annee, :date_doc, :desc, :mots, :f_nom, :f_chemin, :f_type, :f_taille, :vers_par)",
                            [
                                'ref' => $ref,
                                'titre' => $titre,
                                'type' => $type_document,
                                'annee' => $annee_scolaire ?: null,
                                'date_doc' => $date_document ?: null,
                                'desc' => $description ?: null,
                                'mots' => $mots_cles ?: null,
                                'f_nom' => $_FILES['fichier']['name'],
                                'f_chemin' => 'assets/uploads/archives-electroniques/' . $filename,
                                'f_type' => $ext,
                                'f_taille' => $_FILES['fichier']['size'],
                                'vers_par' => $_SESSION['user_id']
                            ]
                        );
                        log_activity('archives_electroniques.ajout', 'Versement du document ' . $ref . ' - ' . $titre);
                        set_flash('success', 'Document versé dans l\'archivage électronique avec succès. Référence : ' . $ref);
                        header('Location: index.php');
                        exit;
                    } catch (Exception $e) {
                        @unlink($filepath);
                        set_flash('error', 'Erreur lors de l\'enregistrement.');
                    }
                } else {
                    set_flash('error', 'Erreur lors du téléversement du fichier.');
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-file-arrow-up me-2"></i>Verser un document dans l'archivage électronique</span>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour à la liste</a>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Titre du document <span class="text-danger">*</span></label>
                    <input type="text" name="titre" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type de document <span class="text-danger">*</span></label>
                    <select name="type_document" class="form-select" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($types as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Année scolaire</label>
                    <input type="text" name="annee_scolaire" class="form-control" placeholder="Ex: 2025-2026">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date du document</label>
                    <input type="date" name="date_document" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fichier <span class="text-danger">*</span></label>
                    <input type="file" name="fichier" class="form-control" required>
                    <small class="text-muted">PDF, Word, Excel, Images, TXT (max 10 Mo)</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mots-clés</label>
                    <input type="text" name="mots_cles" class="form-control" placeholder="Ex: conseil classe, discipline, PV">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description / Observations</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>Verser dans l'archivage</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
