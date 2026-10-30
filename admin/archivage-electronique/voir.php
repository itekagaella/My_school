<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.view');

$id = (int)get('id', 0);
$doc = prepareQuery("SELECT a.*, u.nom, u.prenom FROM archives_electroniques a LEFT JOIN utilisateurs u ON u.id = a.vers_par WHERE a.id = :id AND a.statut='actif'", ['id' => $id])->fetch();
if (!$doc) {
    set_flash('error', 'Document introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Consultation - Archivage électronique';
$active_menu = 'archivage_electronique';
$types = ['PV' => 'Procès-verbal', 'Courrier' => 'Courrier', 'Rapport' => 'Rapport', 'Decision' => 'Décision', 'Liste' => 'Liste officielle', 'Autre' => 'Autre'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-file-alt me-2"></i><?= e($doc['titre']) ?></span>
        <div>
            <a href="telecharger.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-download me-1"></i>Télécharger</a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Référence :</strong> <span class="badge bg-secondary"><?= e($doc['reference']) ?></span>
            </div>
            <div class="col-md-6">
                <strong>Type :</strong> <?= $types[$doc['type_document']] ?? e($doc['type_document']) ?>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <strong>Année scolaire :</strong> <?= e($doc['annee_scolaire'] ?? '-') ?>
            </div>
            <div class="col-md-4">
                <strong>Date du document :</strong> <?= $doc['date_document'] ? date('d/m/Y', strtotime($doc['date_document'])) : '-' ?>
            </div>
            <div class="col-md-4">
                <strong>Date de versement :</strong> <?= date('d/m/Y H:i', strtotime($doc['date_versement'])) ?>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Versé par :</strong> <?= e(trim(($doc['prenom'] ?? '') . ' ' . ($doc['nom'] ?? ''))) ?>
            </div>
            <div class="col-md-6">
                <strong>Fichier :</strong> <?= e($doc['fichier_nom']) ?> (<?= round($doc['fichier_taille']/1024, 1) ?> Ko)
            </div>
        </div>
        <?php if ($doc['mots_cles']): ?>
            <div class="mb-3">
                <strong>Mots-clés :</strong> <?= e($doc['mots_cles']) ?>
            </div>
        <?php endif; ?>
        <?php if ($doc['description']): ?>
            <div class="mb-3">
                <strong>Description :</strong><br>
                <p class="text-muted"><?= nl2br(e($doc['description'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
