<?php
/**
 * Import d'élèves à partir d'un fichier CSV
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('eleves.create');

$page_title = 'Importer des élèves';
$active_menu = 'eleves';

$error = '';
$imported = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée.';
    } elseif (empty($_FILES['csv']['tmp_name'])) {
        $error = 'Veuillez sélectionner un fichier CSV.';
    } else {
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        if ($classe_id <= 0) {
            $error = 'Veuillez choisir une classe cible.';
        } else {
            $handle = fopen($_FILES['csv']['tmp_name'], 'r');
            if (!$handle) {
                $error = 'Impossible de lire le fichier.';
            } else {
                // En-têtes attendues : nom;prenom;date_naissance;sexe;parent_nom;parent_tel
                fgetcsv($handle, 0, ';');
                $defaultPw = '12345678';
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    if (count($row) < 4) continue;
                    $nom = trim($row[0]);
                    $prenom = trim($row[1]);
                    $dn = trim($row[2]);
                    $sexe = strtoupper(trim($row[3])) === 'F' ? 'F' : 'M';
                    $parent_nom = trim($row[4] ?? '');
                    $parent_tel = trim($row[5] ?? '');
                    if (empty($nom) || empty($prenom)) continue;

                    try {
                        $hash = password_hash($defaultPw, PASSWORD_BCRYPT);
                        $email = strtolower($prenom . '.' . $nom . $imported . '@eleve.local');
                        $stmt = getDB()->prepare("SELECT sp_inscrire_eleve(:n,:p,:dn,:s,:c,:pn,:pt, NULL, NULL, :ec, :mp)");
                        $stmt->bindValue(':n', $nom);
                        $stmt->bindValue(':p', $prenom);
                        $stmt->bindValue(':dn', $dn);
                        $stmt->bindValue(':s', $sexe);
                        $stmt->bindValue(':c', $classe_id, PDO::PARAM_INT);
                        $stmt->bindValue(':pn', $parent_nom);
                        $stmt->bindValue(':pt', $parent_tel);
                        $stmt->bindValue(':ec', $email);
                        $stmt->bindValue(':mp', $hash);
                        $stmt->execute();
                        $imported++;
                    } catch (Exception $e) {
                        // Ignorer les lignes en erreur
                    }
                }
                fclose($handle);
                log_activity('eleves.import', 'Import CSV de ' . $imported . ' élèves');
                set_flash('success', $imported . ' élève(s) importé(s) avec succès.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-file-import me-2"></i>Import CSV d'élèves</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <div class="alert alert-info">
            <strong>Format du fichier CSV</strong> (séparateur <code>;</code>) :
            <br><code>nom;prenom;date_naissance (YYYY-MM-DD);sexe (M/F);parent_nom;parent_tel</code>
            <br>La première ligne (en-têtes) est ignorée.
        </div>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Classe cible</label>
                    <select name="classe_id" class="form-select" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['nom_classe']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Fichier CSV</label>
                    <input type="file" name="csv" class="form-control" accept=".csv" required>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import me-1"></i>Importer</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
