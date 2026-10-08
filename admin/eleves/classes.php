<?php
/**
 * Gestion des classes (Admin) - CRUD
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);

$page_title = 'Gestion des classes';
$active_menu = 'eleves';

$SECTION_AUTRE = '__autre__';
$niveaux_burundi = classes_burundi_list();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $nom = clean_input($_POST['nom_classe'] ?? '');
            $niveau = niveau_pour_classe($nom);
            $sectionChoisie = clean_input($_POST['section'] ?? '');
            $section = ($sectionChoisie === $SECTION_AUTRE) ? clean_input($_POST['section_autre'] ?? '') : $sectionChoisie;
            $annee = clean_input($_POST['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1));
            $capacite = (int)($_POST['capacite'] ?? 50);

            if ($nom === '') set_flash('error', 'Sélectionnez une classe.');
            elseif ($niveau === '') set_flash('error', 'Cette classe n\'existe pas dans le système scolaire burundais.');
            elseif ($sectionChoisie === $SECTION_AUTRE && $section === '') set_flash('error', 'Précisez la section ou choisissez-en une dans la liste.');
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
            $existant = prepareQuery('SELECT * FROM classes WHERE id = :id', ['id' => $id])->fetch();
            $nom = clean_input($_POST['nom_classe'] ?? '');
            $niveau = niveau_pour_classe($nom);
            $sectionChoisie = clean_input($_POST['section'] ?? '');
            $section = ($sectionChoisie === $SECTION_AUTRE) ? clean_input($_POST['section_autre'] ?? '') : $sectionChoisie;
            $capacite = (int)($_POST['capacite'] ?? 50);

            if (!$existant) set_flash('error', 'Classe introuvable.');
            elseif ($nom === '') set_flash('error', 'Sélectionnez une classe.');
            elseif ($niveau === '' && $nom !== $existant['nom_classe']) set_flash('error', 'Cette classe n\'existe pas dans le système scolaire burundais.');
            elseif ($sectionChoisie === $SECTION_AUTRE && $section === '') set_flash('error', 'Précisez la section ou choisissez-en une dans la liste.');
            else {
                $niveau = ($niveau !== '') ? $niveau : $existant['niveau'];
                prepareQuery(
                    'UPDATE classes SET nom_classe=:n, niveau=:l, section=:s, capacite=:c WHERE id=:id',
                    ['n'=>$nom,'l'=>$niveau,'s'=>$section,'c'=>$capacite,'id'=>$id]
                );
                log_activity('classes.edit', 'Modification de la classe #' . $id);
                set_flash('success', 'Classe mise à jour.');
            }
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
$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql())->fetchAll();

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
                    <div class="mb-2"><label class="form-label required">Classe</label>
                        <select name="nom_classe" id="c_nom" class="form-select" required onchange="majNiveau(this, 'c_niveau')">
                            <option value="">— Choisir une classe —</option>
                            <?php foreach (classes_burundi() as $cycle => $noms): ?>
                                <optgroup label="<?= e($cycle) ?>">
                                    <?php foreach ($noms as $n): ?>
                                        <option value="<?= e($n) ?>"><?= e($n) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="mb-2"><label class="form-label">Niveau <span class="text-muted">(automatique)</span></label>
                        <input type="text" id="c_niveau" class="form-control" readonly placeholder="Choisissez une classe"></div>
                    <div class="mb-2"><label class="form-label">Section / Série</label>
                        <select name="section" id="c_section" class="form-select" onchange="majSection(this, 'c_section_autre')">
                            <option value="">— Aucune —</option>
                            <?php foreach (sections_burundi() as $s): ?>
                                <option value="<?= e($s) ?>"><?= e($s) ?></option>
                            <?php endforeach; ?>
                            <option value="<?= e($SECTION_AUTRE) ?>">Autre (à préciser)</option>
                        </select>
                        <input type="text" name="section_autre" id="c_section_autre" class="form-control mt-2" placeholder="Préciser la section" style="display:none"></div>
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
                    <thead class="table-light"><tr><th>Classe</th><th>Niveau</th><th>Section</th><th>Année</th><th>Capacité</th><th class="text-end">Actions</th></tr></thead>
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
                    <div class="mb-2"><label class="form-label">Classe</label>
                        <select name="nom_classe" id="e_nom" class="form-select" required onchange="majNiveau(this, 'e_niveau')">
                            <option value="">— Choisir une classe —</option>
                            <?php foreach (classes_burundi() as $cycle => $noms): ?>
                                <optgroup label="<?= e($cycle) ?>">
                                    <?php foreach ($noms as $n): ?>
                                        <option value="<?= e($n) ?>"><?= e($n) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="mb-2"><label class="form-label">Niveau <span class="text-muted">(automatique)</span></label>
                        <input type="text" id="e_niveau" class="form-control" readonly></div>
                    <div class="mb-2"><label class="form-label">Section / Série</label>
                        <select name="section" id="e_section" class="form-select" onchange="majSection(this, 'e_section_autre')">
                            <option value="">— Aucune —</option>
                            <?php foreach (sections_burundi() as $s): ?>
                                <option value="<?= e($s) ?>"><?= e($s) ?></option>
                            <?php endforeach; ?>
                            <option value="<?= e($SECTION_AUTRE) ?>">Autre (à préciser)</option>
                        </select>
                        <input type="text" name="section_autre" id="e_section_autre" class="form-control mt-2" placeholder="Préciser la section" style="display:none"></div>
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
var CLASSES_NIVEAUX = <?= json_encode($niveaux_burundi, JSON_UNESCAPED_UNICODE) ?>;
var SECTION_AUTRE = <?= json_encode($SECTION_AUTRE) ?>;

function majNiveau(select, cible) {
    var el = document.getElementById(cible);
    if (el) el.value = CLASSES_NIVEAUX[select.value] || '';
}

function majSection(select, cible) {
    var el = document.getElementById(cible);
    if (!el) return;
    var autre = select.value === SECTION_AUTRE;
    el.style.display = autre ? 'block' : 'none';
    if (autre) el.focus(); else el.value = '';
}

document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('editModal');
    modal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('e_id').value = btn.getAttribute('data-id');

        var nom = btn.getAttribute('data-nom') || '';
        var selNom = document.getElementById('e_nom');
        var legacy = selNom.querySelector('option[data-legacy]');
        if (legacy) legacy.parentNode.removeChild(legacy);
        if (nom && !Object.prototype.hasOwnProperty.call(CLASSES_NIVEAUX, nom)) {
            var opt = document.createElement('option');
            opt.value = nom;
            opt.textContent = nom + ' (classe actuelle)';
            opt.setAttribute('data-legacy', '1');
            selNom.appendChild(opt);
        }
        selNom.value = nom;
        majNiveau(selNom, 'e_niveau');
        if (!selNom.value) document.getElementById('e_niveau').value = btn.getAttribute('data-niveau') || '';

        var sec = btn.getAttribute('data-section') || '';
        var selSec = document.getElementById('e_section');
        var inpSec = document.getElementById('e_section_autre');
        var trouve = false;
        for (var i = 0; i < selSec.options.length; i++) {
            if (selSec.options[i].value === sec) { trouve = true; break; }
        }
        if (trouve) {
            selSec.value = sec;
            inpSec.style.display = 'none';
            inpSec.value = '';
        } else {
            selSec.value = SECTION_AUTRE;
            inpSec.style.display = 'block';
            inpSec.value = sec;
        }

        document.getElementById('e_cap').value = btn.getAttribute('data-cap');
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
