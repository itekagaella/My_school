<?php
/**
 * Archivage électronique / GED - Ajouter depuis documents existants
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$page_title = 'Archiver un document existant';
$active_menu = 'archivage_electronique';

$types = ['PV' => 'Procès-verbal', 'Courrier' => 'Courrier', 'Rapport' => 'Rapport', 'Decision' => 'Décision', 'Liste' => 'Liste officielle', 'Autre' => 'Autre'];

// Liste des documents existants non encore archivés
$documents = prepareQuery(
    "SELECT d.id, d.nom_fichier, d.type_fichier, d.taille_fichier, d.date_creation, d.description, u.nom, u.prenom
     FROM documents d
     LEFT JOIN utilisateurs u ON u.id = d.proprietaire_id
     WHERE d.statut = 'actif'
       AND NOT EXISTS (SELECT 1 FROM archives_electroniques ae WHERE ae.document_id = d.id AND ae.statut = 'actif')
     ORDER BY d.date_creation DESC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        $document_id = (int)($_POST['document_id'] ?? 0);
        $titre = clean_input($_POST['titre'] ?? '');
        $type_document = clean_input($_POST['type_document'] ?? '');
        $annee_scolaire = clean_input($_POST['annee_scolaire'] ?? '');
        $date_document = clean_input($_POST['date_document'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        $mots_cles = clean_input($_POST['mots_cles'] ?? '');

        if (!$document_id || empty($titre) || empty($type_document)) {
            set_flash('error', 'Le document, le titre et le type sont obligatoires.');
        } else {
            $doc = prepareQuery('SELECT * FROM documents WHERE id = :id AND statut = \'actif\'', ['id' => $document_id])->fetch();
            if (!$doc) {
                set_flash('error', 'Document introuvable.');
            } else {
                // Vérifier s'il n'est pas déjà archivé
                $exists = prepareQuery('SELECT id FROM archives_electroniques WHERE document_id = :id AND statut = \'actif\'', ['id' => $document_id])->fetch();
                if ($exists) {
                    set_flash('error', 'Ce document est déjà archivé électroniquement.');
                } else {
                    $ref = 'GED-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    while (prepareQuery('SELECT id FROM archives_electroniques WHERE reference = :r', ['r' => $ref])->fetch()) {
                        $ref = 'GED-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    }

                    try {
                        prepareQuery(
                            "INSERT INTO archives_electroniques (reference, titre, type_document, annee_scolaire, date_document, description, mots_cles, fichier_nom, fichier_chemin, fichier_type, fichier_taille, vers_par, document_id)
                             VALUES (:ref, :titre, :type, :annee, :date_doc, :desc, :mots, :f_nom, :f_chemin, :f_type, :f_taille, :vers_par, :document_id)",
                            [
                                'ref' => $ref,
                                'titre' => $titre,
                                'type' => $type_document,
                                'annee' => $annee_scolaire ?: null,
                                'date_doc' => $date_document ?: null,
                                'desc' => $description ?: null,
                                'mots' => $mots_cles ?: null,
                                'f_nom' => $doc['nom_fichier'],
                                'f_chemin' => $doc['chemin_fichier'],
                                'f_type' => $doc['type_fichier'],
                                'f_taille' => $doc['taille_fichier'],
                                'vers_par' => $_SESSION['user_id'],
                                'document_id' => $document_id
                            ]
                        );
                        log_activity('archives_electroniques.ajout', 'Archivage électronique du document ' . $doc['nom_fichier'] . ' (' . $ref . ')');
                        set_flash('success', 'Document archivé électroniquement avec succès. Référence : ' . $ref);
                        header('Location: index.php');
                        exit;
                    } catch (Exception $e) {
                        set_flash('error', 'Erreur lors de l\'archivage: ' . $e->getMessage());
                    } catch (Throwable $e) {
                        set_flash('error', 'Erreur lors de l\'archivage: ' . $e->getMessage());
                    }
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
        <span><i class="fa-solid fa-archive me-2"></i>Archiver un document existant du système</span>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour à la liste</a>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle me-1"></i>
                Tous les documents actifs sont déjà archivés électroniquement, ou aucun document n'existe.
                <br><a href="../documents/index.php" class="alert-link">Aller au module Documents</a>
            </div>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Sélectionner le document à archiver <span class="text-danger">*</span></label>
                    <select name="document_id" class="form-select" required id="docSelect">
                        <option value="">-- Choisir un document --</option>
                        <?php foreach ($documents as $d): ?>
                            <option value="<?= $d['id'] ?>" data-nom="<?= e($d['nom_fichier']) ?>">
                                <?= e($d['nom_fichier']) ?> (<?= strtoupper($d['type_fichier']) ?>, <?= round($d['taille_fichier']/1024, 1) ?> Ko)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Seuls les documents déjà présents dans le module "Documents" sont proposés.</small>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Titre dans l'archivage électronique <span class="text-danger">*</span></label>
                        <input type="text" name="titre" class="form-control" required id="titreInput">
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
                        <label class="form-label">Mots-clés</label>
                        <input type="text" name="mots_cles" class="form-control" placeholder="Ex: conseil classe, discipline, PV">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description / Observations</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary"><i class="fa-solid fa-archive me-1"></i>Archiver électroniquement</button>
                </div>
            </form>
            <script>
            document.getElementById('docSelect').addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if (opt.value) {
                    document.getElementById('titreInput').value = opt.getAttribute('data-nom');
                }
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
