<?php
/**
 * Sauvegardes et restauration de la base de données (Admin)
 * - Génération d'un dump SQL complet (stocké dans storage/backups/)
 * - Sauvegarde automatique quotidienne (paramètre `sauvegarde_auto`)
 * - Restauration depuis un dump de la liste ou un fichier .sql importé
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('backup.manage');

$page_title = 'Sauvegardes';
$active_menu = 'backup';

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée, veuillez réessayer.');
        header('Location: backup.php');
        exit;
    }

    // ---- Nom de fichier contrôlé (aucune traversée de chemin) ----
    $safeName = function (?string $name): ?string {
        $name = basename((string)$name);
        return preg_match('/^[0-9A-Za-z_\-]+\.sql$/', $name) ? $name : null;
    };

    if ($action === 'dump') {
        try {
            $sql = backup_generate_sql();
            $path = backup_write($sql);
            log_activity('parametres.backup', 'Dump SQL généré' . ($path ? ' (' . basename($path) . ')' : ''));
            if (!$path) {
                set_flash('error', 'Dump généré mais impossible à écrire dans storage/backups/.');
                header('Location: backup.php');
                exit;
            }
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . strlen($sql));
            echo $sql;
            exit;
        } catch (Throwable $e) {
            set_flash('error', 'Échec de la génération : ' . $e->getMessage());
            header('Location: backup.php');
            exit;
        }
    }

    if ($action === 'download') {
        $name = $safeName($_POST['file'] ?? '');
        $path = $name ? backup_dir() . '/' . $name : null;
        if ($path && is_file($path)) {
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . (string)filesize($path));
            readfile($path);
            exit;
        }
        set_flash('error', 'Fichier introuvable.');
    }

    if ($action === 'delete') {
        $name = $safeName($_POST['file'] ?? '');
        $path = $name ? backup_dir() . '/' . $name : null;
        if ($path && is_file($path)) {
            unlink($path);
            log_activity('parametres.backup_delete', 'Sauvegarde supprimée (' . $name . ')');
            set_flash('success', 'Sauvegarde supprimée.');
        } else {
            set_flash('error', 'Fichier introuvable.');
        }
    }

    if ($action === 'restore_file') {
        $name = $safeName($_POST['file'] ?? '');
        $path = $name ? backup_dir() . '/' . $name : null;
        if (!$path || !is_file($path)) {
            set_flash('error', 'Sauvegarde introuvable.');
        } elseif (($_POST['confirm'] ?? '') !== 'RESTAURER') {
            set_flash('error', 'Confirmation invalide : saisissez RESTAURER.');
        } else {
            [$ok, $msg] = backup_restore((string)file_get_contents($path));
            if ($ok) {
                log_activity('parametres.backup_restore', 'Restauration effectuée depuis ' . $name);
                set_flash('success', $msg);
            } else {
                log_activity('parametres.backup_restore_error', 'Restauration échouée (' . $name . ') : ' . $msg);
                set_flash('error', $msg);
            }
        }
    }

    if ($action === 'restore_upload') {
        $file = $_FILES['backup_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            set_flash('error', 'Aucun fichier reçu ou erreur d\'upload.');
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            set_flash('error', 'Fichier trop volumineux (20 Mo max).');
        } elseif (!preg_match('/\.sql$/i', $file['name'] ?? '')) {
            set_flash('error', 'Seuls les fichiers .sql sont acceptés.');
        } elseif (($_POST['confirm'] ?? '') !== 'RESTAURER') {
            set_flash('error', 'Confirmation invalide : saisissez RESTAURER.');
        } else {
            $sql = (string)file_get_contents($file['tmp_name']);
            // Conserver une copie de l'import pour trace
            $stored = backup_write($sql, 'import_');
            [$ok, $msg] = backup_restore($sql);
            if ($ok) {
                log_activity('parametres.backup_restore', 'Restauration depuis un fichier importé'
                    . ($stored ? ' (copie : ' . basename($stored) . ')' : ''));
                set_flash('success', $msg);
            } else {
                log_activity('parametres.backup_restore_error', 'Import échoué : ' . $msg);
                set_flash('error', $msg);
            }
        }
    }

    header('Location: backup.php');
    exit;
}

// ---- État de la sauvegarde automatique ----
$autoActive = get_param('sauvegarde_auto', 'false') === 'true';
$files = backup_list();
$newest = null;
foreach ($files as $f) {
    if ($newest === null || $f['mtime'] > $newest['mtime']) $newest = $f;
}
$autoFile = null;
foreach ($files as $f) {
    if ($f['auto']) { $autoFile = $f; break; }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-database me-2 text-primary"></i>Sauvegardes
        <span class="text-muted fs-6">(<?= count($files) ?>)</span></h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Paramètres</a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-download me-2"></i>Générer une sauvegarde</div>
            <div class="card-body">
                <p class="text-muted small">Crée un dump SQL complet (structure + données) enregistré
                    dans <code>storage/backups/</code>, puis proposé au téléchargement.</p>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <button type="submit" name="action" value="dump" class="btn btn-success">
                        <i class="fa-solid fa-file-arrow-down me-1"></i>Télécharger le dump SQL
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-clock-rotate-left me-2"></i>Sauvegarde automatique</div>
            <div class="card-body">
                <p class="mb-2">
                    <?php if ($autoActive): ?>
                        <span class="badge bg-success">Activée</span>
                        dernière génération :
                        <?php if ($autoFile): ?>
                            <strong><?= e(date('d/m/Y H:i', $autoFile['mtime'])) ?></strong>
                        <?php else: ?>
                            <em>aucune pour l'instant</em>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-secondary">Désactivée</span>
                        activez-la dans <a href="index.php">Paramètres généraux</a>
                    <?php endif; ?>
                </p>
                <p class="text-muted small mb-2">Un dump est créé automatiquement chaque jour au chargement
                    de l'espace admin, si le paramètre est activé (fichiers <code>sauvegarde_auto_*</code>).</p>
                <a href="../index.php" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-gear me-1"></i>Paramètres</a>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-upload me-2"></i>Restaurer depuis un fichier</div>
            <div class="card-body">
                <p class="text-muted small">Importez un dump <code>.sql</code> (généré par My_School).
                    La restauration remplace l'intégralité des données — elle est annulée si une erreur survient.</p>
                <form method="post" action="" enctype="multipart/form-data"
                      onsubmit="return confirm('ATTENTION : la restauration va remplacer toutes les données. Continuer ?');">
                    <?= csrf_field() ?>
                    <div class="input-group mb-2">
                        <input type="file" name="backup_file" class="form-control" accept=".sql" required>
                        <button type="submit" name="action" value="restore_upload" class="btn btn-danger">
                            <i class="fa-solid fa-rotate-left me-1"></i>Restaurer
                        </button>
                    </div>
                    <input type="text" name="confirm" class="form-control" style="max-width:240px"
                           placeholder="Tapez RESTAURER pour valider" required>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-box-archive me-2"></i>Sauvegardes disponibles
        <span class="text-muted fs-6">(<?= count($files) ?>)</span></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Fichier</th>
                        <th>Date</th>
                        <th>Taille</th>
                        <th>Type</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune sauvegarde enregistrée.
                            Générez-en une pour démarrer.</td></tr>
                    <?php else: foreach ($files as $f): ?>
                        <tr>
                            <td><code class="small"><?= e($f['name']) ?></code></td>
                            <td class="text-nowrap small"><?= date('d/m/Y H:i', $f['mtime']) ?></td>
                            <td class="small text-muted"><?= number_format($f['size'] / 1024, 1) ?> Ko</td>
                            <td>
                                <?php if ($f['auto']): ?>
                                    <span class="badge bg-info">Auto</span>
                                <?php elseif (strpos($f['name'], 'import_') === 0): ?>
                                    <span class="badge bg-warning text-dark">Import</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Manuelle</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="post" action="" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                                    <button type="submit" name="action" value="download" class="btn btn-sm btn-outline-success"
                                            title="Télécharger"><i class="fa-solid fa-download"></i></button>
                                </form>
                                <form method="post" action="" class="d-inline"
                                      onsubmit="return confirm('Restaurer ce dump ? Toutes les données seront remplacées.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                                    <input type="hidden" name="confirm" value="RESTAURER">
                                    <button type="submit" name="action" value="restore_file" class="btn btn-sm btn-outline-warning"
                                            title="Restaurer"><i class="fa-solid fa-rotate-left"></i></button>
                                </form>
                                <form method="post" action="" class="d-inline"
                                      onsubmit="return confirm('Supprimer cette sauvegarde ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger"
                                            title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                </form>
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
