<?php
/**
 * Gestion des classes (Admin) - CRUD
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);

$page_title = 'Gestion des classes';
$active_menu = 'eleves';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $nom = clean_input($_POST['nom_classe'] ?? '');
            $niveau = clean_input($_POST['niveau'] ?? '');
            $section = clean_input($_POST['section'] ?? '');
            $annee = clean_input($_POST['annee_scolaire'] ?? date('Y') . '-' . (date('Y')+1));
            $capacite = (int)($_POST['capacite'] ?? 50);
            if (empty($nom) || empty($niveau)) set_flash('error', 'Nom et niveau requis.');
            else {
                prepareQuery(
                    'INSERT INTO classes (nom_classe, niveau, section, annee_scolaire, capacite)
                     VALUES (:n,:l,:s,:a,:c)',
                    ['n'=>$nom,'l'=>$niveau,'s'=>$section,'a'=>$annee,'c'=>$capacite]
                );
                log_activity('classes.create', 'Création de la classe ' . $nom);
                set_flash('success', 'Classe créée.');
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $nom = clean_input($_POST['nom_classe'] ?? '');
            $niveau = clean_input($_POST['niveau'] ?? '');
            $section = clean_input($_POST['section'] ?? '');
            $capacite = (int)($_POST['capacite'] ?? 50);
            prepareQuery(
                'UPDATE classes SET nom_classe=:n, niveau=:l, section=:s, capacite=:c WHERE id=:id',
                ['n'=>$nom,'l'=>$niveau,'s'=>$section,'c'=>$capacite,'id'=>$id]
            );
            log_activity('classes.edit', 'Modification de la classe #' . $id);
            set_flash('success', 'Classe mise à jour.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            prepareQuery('DELETE FROM classes WHERE id = :id', ['id'=>$id]);
            log_activity('classes.delete', 'Suppression de la classe #' . $id);
            set_flash('success', 'Classe supprimée.');
        }
        header('Location: classes.php');
        exit;
    }
}

$annees = [date('Y').'-'.(date('Y')+1), (date('Y')-1).'-'.date('Y')];
$classes = prepareQuery('SELECT * FROM classes ORDER BY nom_classe')->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-plus me-2"></i>Nouvelle classe</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-2"><label class="form-label required">Nom de la classe</label>
                        <input type="text" name="nom_classe" class="form-control" placeholder="Ex: 6ème A" required></div>
                    <div class="mb-2"><label class="form-label required">Niveau</label>
                        <input type="text" name="niveau" class="form-control" placeholder="Ex: 6ème" required></div>
                    <div class="mb-2"><label class="form-label">Section</label>
                        <input type="text" name="section" class="form-control" placeholder="Ex: A"></div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Année scolaire</label>
                            <select name="annee_scolaire" class="form-select">
                                <?php foreach ($annees as $a): ?><option value="<?= e($a) ?>"><?= e($a) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacité</label>
                            <input type="number" name="capacite" class="form-control" value="50" min="1">
                        </div>
                    </div>
                    <div class="mt-3"><button class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i>Créer</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-school me-2"></i><?= count($classes) ?> classes</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Classe</th><th>Niveau</th><th>Section</th><th>Année</th><th>Capacité</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($classes)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Aucune classe.</td></tr>
                        <?php else: foreach ($classes as $c): ?>
                            <tr>
                                <td><strong><?= e($c['nom_classe']) ?></strong></td>
                                <td><?= e($c['niveau']) ?></td>
                                <td><?= e($c['section'] ?? '') ?></td>
                                <td class="small"><?= e($c['annee_scolaire']) ?></td>
                                <td><?= $c['capacite'] ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?= $c['id'] ?>" data-nom="<?= e($c['nom_classe']) ?>" data-niveau="<?= e($c['niveau']) ?>"
                                        data-section="<?= e($c['section']) ?>" data-cap="<?= $c['capacite'] ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="post" action="" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Supprimer cette classe ?')"><i class="fa-solid fa-trash"></i></button>
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

<!-- Modal édition -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="e_id">
                <div class="modal-header"><h5 class="modal-title">Modifier la classe</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">Nom</label><input type="text" name="nom_classe" id="e_nom" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Niveau</label><input type="text" name="niveau" id="e_niveau" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Section</label><input type="text" name="section" id="e_section" class="form-control"></div>
                    <div><label class="form-label">Capacité</label><input type="number" name="capacite" id="e_cap" class="form-control" min="1"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('editModal');
    modal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('e_id').value = btn.getAttribute('data-id');
        document.getElementById('e_nom').value = btn.getAttribute('data-nom');
        document.getElementById('e_niveau').value = btn.getAttribute('data-niveau');
        document.getElementById('e_section').value = btn.getAttribute('data-section') || '';
        document.getElementById('e_cap').value = btn.getAttribute('data-cap');
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
