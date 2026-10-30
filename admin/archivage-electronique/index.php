<?php
/**
 * Archivage électronique / GED - Liste
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.view');

$page_title = 'Archivage électronique';
$active_menu = 'archivage_electronique';

// Filtres
$type = clean_input($_GET['type'] ?? '');
$annee = clean_input($_GET['annee'] ?? '');
$q = clean_input($_GET['q'] ?? '');

$sql = "SELECT a.*, u.nom, u.prenom
        FROM archives_electroniques a
        LEFT JOIN utilisateurs u ON u.id = a.vers_par
        WHERE a.statut = 'actif'";
$params = [];

if ($type) {
    $sql .= " AND a.type_document = :type";
    $params['type'] = $type;
}
if ($annee) {
    $sql .= " AND a.annee_scolaire = :annee";
    $params['annee'] = $annee;
}
if ($q) {
    $sql .= " AND (LOWER(a.titre) LIKE LOWER(:q) OR LOWER(a.reference) LIKE LOWER(:q) OR LOWER(a.mots_cles) LIKE LOWER(:q))";
    $params['q'] = '%' . $q . '%';
}

$sql .= " ORDER BY a.date_versement DESC";
$docs = prepareQuery($sql, $params)->fetchAll();

// Types de documents
$types = ['PV' => 'Procès-verbal', 'Courrier' => 'Courrier', 'Rapport' => 'Rapport', 'Decision' => 'Décision', 'Liste' => 'Liste officielle', 'Autre' => 'Autre'];

// Années scolaires
$annees = prepareQuery("SELECT DISTINCT annee_scolaire FROM archives_electroniques WHERE annee_scolaire IS NOT NULL AND statut='actif' ORDER BY annee_scolaire DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-archive me-2"></i>Archivage électronique (GED)</span>
        <a href="ajouter.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-archive me-1"></i>Archiver un document existant</a>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">Tous les types</option>
                    <?php foreach ($types as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="annee" class="form-select">
                    <option value="">Toutes les années</option>
                    <?php foreach ($annees as $a): ?>
                        <option value="<?= e($a['annee_scolaire']) ?>" <?= $annee === $a['annee_scolaire'] ? 'selected' : '' ?>><?= e($a['annee_scolaire']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" name="q" class="form-control" placeholder="Rechercher par titre, référence, mots-clés..." value="<?= e($q) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="fa-solid fa-search me-1"></i>Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Référence</th>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Année scolaire</th>
                        <th>Date document</th>
                        <th>Versé par</th>
                        <th>Date versement</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($docs)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Aucun document archivé.</td></tr>
                    <?php else: foreach ($docs as $d): ?>
                        <tr>
                            <td class="small"><span class="badge bg-secondary"><?= e($d['reference']) ?></span></td>
                            <td><strong><?= e($d['titre']) ?></strong><?php if ($d['mots_cles']): ?><br><small class="text-muted"><?= e($d['mots_cles']) ?></small><?php endif; ?></td>
                            <td><?= $types[$d['type_document']] ?? e($d['type_document']) ?></td>
                            <td><?= e($d['annee_scolaire'] ?? '-') ?></td>
                            <td class="small"><?= $d['date_document'] ? date('d/m/Y', strtotime($d['date_document'])) : '-' ?></td>
                            <td class="small"><?= e(trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''))) ?></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($d['date_versement'])) ?></td>
                            <td class="text-end">
                                <a href="voir.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary" title="Consulter"><i class="fa-solid fa-eye"></i></a>
                                <a href="telecharger.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="fa-solid fa-download"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
