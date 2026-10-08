<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$id = (int)get('id', 0);
$doc = prepareQuery('SELECT * FROM documents WHERE id = :id', ['id' => $id])->fetch();

if (!$doc) {
    set_flash('error', 'Document introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Versions - ' . $doc['nom_fichier'];
$active_menu = 'documents';

$versions = prepareQuery(
    "SELECT dv.*, u.prenom AS modifie_par_prenom, u.nom AS modifie_par_nom
     FROM document_versions dv
     LEFT JOIN utilisateurs u ON u.id = dv.modifie_par
     WHERE dv.document_id = :did
     ORDER BY dv.version DESC",
    ['did' => $id]
)->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        if (empty($_FILES['nouveau_fichier']['name'])) {
            $error = 'Veuillez sélectionner un nouveau fichier.';
        } else {
            $result = upload_file($_FILES['nouveau_fichier'], 'documents');
            if (!$result[0]) {
                $error = $result[1];
            } else {
                $maxVersion = 0;
                if (!empty($versions)) {
                    $maxVersion = (int)$versions[0]['version'];
                }
                $nouvelleVersion = $maxVersion + 1;

                $chemin = str_replace(ROOT_PATH, '', $result[2]);
                $taille = filesize($result[2]);

                prepareQuery(
                    "INSERT INTO document_versions (document_id, version, fichier_path, taille, modifie_par, date_version)
                     VALUES (:did, :v, :fp, :tl, :mp, NOW())",
                    ['did' => $id, 'v' => $nouvelleVersion, 'fp' => $chemin, 'tl' => $taille, 'mp' => $_SESSION['user_id']]
                );

                prepareQuery(
                    "UPDATE documents SET fichier_path = :fp, taille = :tl WHERE id = :id",
                    ['fp' => $chemin, 'tl' => $taille, 'id' => $id]
                );

                log_activity('documents.version', 'Nouvelle version v' . $nouvelleVersion . ' du document ' . $doc['nom_fichier'] . ' (ID ' . $id . ')');
                set_flash('success', 'Version ' . $nouvelleVersion . ' créée avec succès.');
                header('Location: versions.php?id=' . $id);
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fa-solid fa-code-branch me-2 text-primary"></i>Versions de « <?= e($doc['nom_fichier']) ?> »</h4>
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Historique des versions</div>
            <div class="card-body">
                <?php if (empty($versions)): ?>
                    <p class="text-muted text-center py-3">Aucune version enregistrée.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Version</th>
                                    <th>Date</th>
                                    <th>Modifié par</th>
                                    <th>Taille</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($versions as $i => $v): ?>
                                    <tr class="<?= $i === 0 ? 'table-active' : '' ?>">
                                        <td><span class="badge bg-primary">v<?= $v['version'] ?></span></td>
                                        <td class="small"><?= date('d/m/Y H:i', strtotime($v['date_version'])) ?></td>
                                        <td class="small"><?= e(($v['modifie_par_prenom'] ?? '') . ' ' . ($v['modifie_par_nom'] ?? '')) ?></td>
                                        <td class="text-nowrap">
                                            <?php
                                            $size = (int)$v['taille'];
                                            if ($size >= 1048576) echo round($size / 1048576, 1) . ' Mo';
                                            elseif ($size >= 1024) echo round($size / 1024, 1) . ' Ko';
                                            else echo $size . ' o';
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="../documents/download_version.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="fa-solid fa-download"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Ajouter une version</div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <form method="post" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required">Nouveau fichier</label>
                        <input type="file" name="nouveau_fichier" class="form-control" required
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt">
                        <div class="form-text">La nouvelle version sera créée automatiquement (v<?= count($versions) + 1 ?>).</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-upload me-1"></i>Créer la version</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
