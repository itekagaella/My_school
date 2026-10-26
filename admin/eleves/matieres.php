<?php
/**
 * Gestion des matières (Admin) - CRUD
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);

$page_title = 'Gestion des matières';
$active_menu = 'eleves';

/**
 * Génère un code court et unique à partir du nom de la matière.
 * Le champ n'est plus saisi : il reste utilisé par les horaires et
 * l'espace professeur, et il est NOT NULL UNIQUE en base.
 */
function generer_code_matiere(string $nom): string
{
    $translitteration = [
        'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n','ÿ'=>'y','œ'=>'oe','æ'=>'ae',
    ];
    $texte = strtr(mb_strtolower($nom, 'UTF-8'), $translitteration);
    $base  = strtoupper(preg_replace('/[^a-z0-9]/i', '', $texte));
    $base  = $base !== '' ? substr($base, 0, 6) : 'MAT';

    $existants = [];
    foreach (prepareQuery('SELECT code FROM matieres')->fetchAll() as $row) {
        $existants[strtoupper($row['code'])] = true;
    }

    $code = $base;
    $suffixe = 1;
    while (isset($existants[$code])) {
        $suffixe++;
        $code = $base . $suffixe; // 6 + 4 chiffres max = 10 caractères
        if ($suffixe > 9999) {
            $code = 'M' . substr((string)time(), -6);
            break;
        }
    }
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $nom = clean_input($_POST['nom_matiere'] ?? '');
            $classe_id = (int)($_POST['classe_id'] ?? 0);
            $prof_id = (int)($_POST['prof_id'] ?? 0);
            if (empty($nom)) set_flash('error', 'Le nom de la matière est requis.');
            else {
                $code = generer_code_matiere($nom);
                prepareQuery(
                    'INSERT INTO matieres (nom_matiere, code, classe_id, prof_id) VALUES (:n,:c,:cl,:p)',
                    ['n'=>$nom,'c'=>$code,'cl'=>$classe_id>0?$classe_id:null,'p'=>$prof_id>0?$prof_id:null]
                );
                log_activity('matieres.create', 'Création de la matière ' . $nom);
                set_flash('success', 'Matière créée.');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            prepareQuery('DELETE FROM matieres WHERE id = :id', ['id'=>$id]);
            log_activity('matieres.delete', 'Suppression de la matière #' . $id);
            set_flash('success', 'Matière supprimée.');
        }
        header('Location: matieres.php');
        exit;
    }
}

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
$profs = prepareQuery('SELECT * FROM profs WHERE statut = :s ORDER BY nom', ['s'=>'actif'])->fetchAll();
$matieres = prepareQuery(
    "SELECT m.*, " . classe_label_sql('c') . " AS nom_classe, p.nom AS prof_nom, p.prenom AS prof_prenom
     FROM matieres m LEFT JOIN classes c ON c.id = m.classe_id LEFT JOIN profs p ON p.id = m.prof_id
     ORDER BY m.nom_matiere"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Nouvelle matière</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-2"><label class="form-label required">Nom de la matière</label>
                        <input type="text" name="nom_matiere" class="form-control" required></div>
                    <div class="mb-2 mt-2">
                        <label class="form-label">Classe</label>
                        <select name="classe_id" class="form-select">
                            <option value="0">-- Toutes / Aucune --</option>
                            <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e(classe_label($c['nom_classe'], $c['section'] ?? null)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Professeur</label>
                        <select name="prof_id" class="form-select">
                            <option value="0">-- Aucun --</option>
                            <?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mt-3"><button class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i>Créer</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-book me-2"></i><?= count($matieres) ?> matières</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Matière</th><th>Classe</th><th>Professeur</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($matieres)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Aucune matière.</td></tr>
                            <?php else: foreach ($matieres as $m): ?>
                                <tr>
                                    <td><strong><?= e($m['nom_matiere']) ?></strong></td>
                                    <td class="small"><?= e($m['nom_classe'] ?? 'Toutes') ?></td>
                                    <td class="small"><?= e($m['prof_prenom'] ?? '') . ' ' . e($m['prof_nom'] ?? '—') ?></td>
                                    <td class="text-end">
                                        <form method="post" action="" class="d-inline">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Supprimer cette matière ?')"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
