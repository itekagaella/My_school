<?php
/**
 * Rapport personnalisé (sélection de colonnes et filtres avancés)
 * La génération et les exports partagent rapport_custom_dataset() (data.php).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('reports.view');

require_once __DIR__ . '/data.php';

$page_title = 'Rapport personnalisé';
$active_menu = 'rapports';

$type = get('type', 'eleves');
if (!rapport_custom_colonnes($type)) $type = 'eleves';
$classe_id = (int)get('classe_id', 0);
$colonnes_dispo = rapport_custom_colonnes($type);

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

$ds = null;
$selColonnes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $type = clean_input($_POST['type'] ?? 'eleves');
    if (!rapport_custom_colonnes($type)) $type = 'eleves';
    $colonnes_dispo = rapport_custom_colonnes($type);
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $postColonnes = $_POST['colonnes'] ?? [];
    if (!is_array($postColonnes)) $postColonnes = [$postColonnes];

    // Ne garder que les colonnes valides pour la source choisie
    foreach ($postColonnes as $c) {
        if (is_string($c) && isset($colonnes_dispo[$c]) && !in_array($c, $selColonnes, true)) {
            $selColonnes[] = $c;
        }
    }

    $ds = rapport_custom_dataset($type, $selColonnes, $classe_id);
    log_activity('reports.custom', 'Rapport personnalisé ' . $type . ' (' . count($ds['rows']) . ' lignes)');
}

$classe_nom = 'Toutes';
foreach ($classes as $c) {
    if ((int)$c['id'] === $classe_id) { $classe_nom = $c['nom_classe']; break; }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-sliders me-2"></i>Paramètres du rapport</div>
            <div class="card-body">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Source</label>
                        <select name="type" id="type_source" class="form-select" onchange="this.form.submit()">
                            <option value="eleves" <?= $type==='eleves'?'selected':'' ?>>Élèves</option>
                            <option value="profs" <?= $type==='profs'?'selected':'' ?>>Professeurs</option>
                            <option value="notes" <?= $type==='notes'?'selected':'' ?>>Notes</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Classe</label>
                        <select name="classe_id" class="form-select">
                            <option value="0">Toutes</option>
                            <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classe_id==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Colonnes à inclure</label>
                        <?php foreach ($colonnes_dispo as $key=>$def): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="colonnes[]" value="<?= e($key) ?>" id="col_<?= e($key) ?>" <?= in_array($key, $selColonnes, true) ? 'checked':'' ?>>
                                <label class="form-check-label" for="col_<?= e($key) ?>"><?= e($def[0]) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-play me-1"></i>Générer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-table me-2"></i>Aperçu du rapport</div>
            <div class="card-body">
                <?php if ($ds !== null && !empty($ds['headers'])): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light"><tr><?php foreach ($ds['headers'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <?php if (empty($ds['rows'])): ?>
                                    <tr><td colspan="<?= count($ds['headers']) ?>" class="text-center text-muted">Aucun résultat.</td></tr>
                                <?php else: foreach ($ds['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $i => $v): ?>
                                            <td><?= e(rapport_format_value($ds['types'][$i] ?? 'text', $v)) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 mt-2">
                        <?php if (has_permission('reports.pdf')): ?>
                        <a class="btn btn-danger btn-sm"
                           href="custom_export.php?format=pdf&type=<?= e($type) ?>&classe_id=<?= (int)$classe_id ?><?= $selColonnes ? '&amp;' . http_build_query(['colonnes' => $selColonnes], '', '&amp;') : '' ?>">
                            <i class="fa-solid fa-file-pdf me-1"></i>Exporter en PDF
                        </a>
                        <?php endif; ?>
                        <?php if (has_permission('reports.excel')): ?>
                        <a class="btn btn-success btn-sm"
                           href="custom_export.php?format=xlsx&amp;type=<?= e($type) ?>&amp;classe_id=<?= (int)$classe_id ?><?= $selColonnes ? '&amp;' . http_build_query(['colonnes' => $selColonnes], '', '&amp;') : '' ?>">
                            <i class="fa-solid fa-file-excel me-1"></i>Exporter en Excel
                        </a>
                        <?php endif; ?>
                        <span class="text-muted small align-self-center"><?= count($ds['rows']) ?> ligne(s) — classe : <?= e($classe_nom) ?></span>
                    </div>
                <?php elseif ($ds !== null): ?>
                    <p class="text-muted text-center py-5">Cochez au moins une colonne puis cliquez sur « Générer ».</p>
                <?php else: ?>
                    <p class="text-muted text-center py-5">Sélectionnez les colonnes puis cliquez sur « Générer ».</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
