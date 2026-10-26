<?php
/**
 * Réparation des accents — corrige les textes « doublement encodés ».
 *
 * Symptôme : des valeurs saisies en base s'affichent « Ã©lÃ¨ve » au lieu de
 * « élève ». Cela arrive quand un texte UTF-8 a été enregistré par une
 * connexion PostgreSQL configurée en Windows-1252 (import psql sur Windows,
 * ancienne version de l'application, etc.).
 *
 * Cette page :
 *   1. affiche un diagnostic (encodage PHP, encodages PostgreSQL) ;
 *   2. détecte les valeurs touchées, avec un aperçu avant → après ;
 *   3. applique la correction après confirmation (admin uniquement).
 *
 * La correction n'est appliquée que si la transformation est exactement
 * réversible (aller-retour UTF-8 → Windows-1252 → UTF-8), ce qui évite de
 * modifier un texte sain.
 *
 * Page de maintenance : peut être supprimée après usage.
 */
require_once __DIR__ . '/includes/auth.php';
require_role(['admin']);

$page_title = 'Réparation des accents';
$active_menu = 'parametres';

/**
 * Vrai si le texte contient des marqueurs typiques de double encodage.
 */
function mojibake_detecte(string $s): bool
{
    if ($s === '') {
        return false;
    }
    foreach (['Ã', 'Â', 'â€', 'ï¿½', "\u{FFFD}"] as $marqueur) {
        if (strpos($s, $marqueur) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Renvoie le texte corrigé, ou null si la valeur n'est pas réparable
 * de façon sûre.
 */
function mojibake_corrige(?string $s): ?string
{
    if ($s === null || $s === '' || !mb_check_encoding($s, 'UTF-8')) {
        return null;
    }

    $courant = $s;
    $modifie = false;

    // Deux passes suffisent pour un double encodage (cas le plus courant).
    for ($i = 0; $i < 2; $i++) {
        if (!mojibake_detecte($courant)) {
            break;
        }

        $essai = @mb_convert_encoding($courant, 'Windows-1252', 'UTF-8');
        if (!is_string($essai) || $essai === '' || !mb_check_encoding($essai, 'UTF-8')) {
            break;
        }

        // Vérifie que l'opération est exactement l'inverse du double encodage.
        $retour = @mb_convert_encoding($essai, 'UTF-8', 'Windows-1252');
        if (!is_string($retour) || $retour !== $courant) {
            break;
        }

        if ($essai === $courant) {
            break;
        }

        $courant = $essai;
        $modifie = true;
    }

    return $modifie ? $courant : null;
}

$motif = 'Ã|Â|â€|ï¿½|�';

$colonnes = prepareQuery(
    "SELECT c.table_name, c.column_name
       FROM information_schema.columns c
       JOIN information_schema.tables t
         ON t.table_schema = c.table_schema AND t.table_name = c.table_name
      WHERE c.table_schema = 'public'
        AND t.table_type = 'BASE TABLE'
        AND c.data_type IN ('character varying', 'text', 'character')
      ORDER BY c.table_name, c.column_name"
)->fetchAll();

$action = $_POST['action'] ?? '';
$rapport = [];
$erreur = null;

// ------------------------------------------------------------
// Application de la correction
// ------------------------------------------------------------
if ($action === 'reparer') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
            foreach ($colonnes as $c) {
                $table = (string)$c['table_name'];
                $col   = (string)$c['column_name'];
                $qt = '"' . str_replace('"', '""', $table) . '"';
                $qc = '"' . str_replace('"', '""', $col) . '"';

                $sel = $db->prepare("SELECT ctid::text AS _ctid, $qc AS _val FROM $qt WHERE $qc ~ :motif");
                $sel->execute([':motif' => $motif]);
                $lignes = $sel->fetchAll(PDO::FETCH_ASSOC);

                if (!$lignes) {
                    continue;
                }

                $upd = $db->prepare("UPDATE $qt SET $qc = :val WHERE ctid = CAST(:ctid AS tid)");
                $n = 0;
                foreach ($lignes as $ligne) {
                    $fixe = mojibake_corrige($ligne['_val']);
                    if ($fixe === null) {
                        continue;
                    }
                    $upd->execute([':val' => $fixe, ':ctid' => $ligne['_ctid']]);
                    $n++;
                }
                if ($n > 0) {
                    $rapport[] = $table . '.' . $col . ' : ' . $n . ' valeur(s) corrigée(s).';
                }
            }

            $db->commit();
            log_activity('encodage.reparation', 'Réparation des accents : ' . (count($rapport) ? implode(' | ', $rapport) : 'aucune valeur'));
            if (count($rapport)) {
                set_flash('success', 'Réparation terminée : ' . array_sum(array_map(static function ($l) {
                    return (int)preg_replace('/\D+/', '', substr($l, strrpos($l, ':') + 1));
                }, $rapport)) . ' valeur(s) corrigée(s).');
            } else {
                set_flash('success', 'Aucune valeur à corriger.');
            }
        } catch (Throwable $e) {
            $db->rollBack();
            $erreur = 'Échec de la réparation : ' . $e->getMessage();
        }
    }
}

// ------------------------------------------------------------
// Aperçu (après une éventuelle réparation)
// ------------------------------------------------------------
$apercu = [];
$total = 0;
foreach ($colonnes as $c) {
    $table = (string)$c['table_name'];
    $col   = (string)$c['column_name'];
    $qt = '"' . str_replace('"', '""', $table) . '"';
    $qc = '"' . str_replace('"', '""', $col) . '"';

    try {
        $nb = (int)prepareQuery("SELECT COUNT(*) FROM $qt WHERE $qc ~ :motif", ['motif' => $motif])->fetchColumn();
        if ($nb === 0) {
            continue;
        }
        $exemples = prepareQuery("SELECT $qc AS val FROM $qt WHERE $qc ~ :motif LIMIT 3", ['motif' => $motif])->fetchAll();
    } catch (Throwable $e) {
        continue;
    }

    $lignes = [];
    foreach ($exemples as $ex) {
        $lignes[] = ['avant' => (string)$ex['val'], 'apres' => mojibake_corrige($ex['val'])];
    }
    $apercu[$table . '.' . $col] = ['nb' => $nb, 'lignes' => $lignes];
    $total += $nb;
}

$db_server = prepareQuery('SHOW server_encoding')->fetchColumn();
$db_client = prepareQuery('SHOW client_encoding')->fetchColumn();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-wand-magic-sparkles me-2 text-primary"></i>Réparation des accents</h4>
    <a href="admin/parametres/index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Paramètres</a>
</div>

<div class="card mb-3">
    <div class="card-header">Diagnostic</div>
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-md-4">
                <div class="text-muted">PHP / serveur</div>
                <div><?= e(PHP_VERSION . ' — ' . PHP_SAPI) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">default_charset</div>
                <div><?= e((string)ini_get('default_charset')) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">mbstring</div>
                <div><?= extension_loaded('mbstring') ? 'activé' : '<span class="text-danger">absent</span>' ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">PostgreSQL (serveur)</div>
                <div><?= e((string)$db_server) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">PostgreSQL (connexion)</div>
                <div><?= e((string)$db_client) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">En-tête envoyé</div>
                <div>
                    <?php
                    $ct = 'inconnu';
                    foreach (headers_list() as $h) {
                        if (stripos($h, 'content-type:') === 0) { $ct = $h; break; }
                    }
                    echo e($ct);
                    ?>
                </div>
            </div>
        </div>
        <hr>
        <div class="small">
            <div class="text-muted mb-1">Texte de contrôle (doit s'afficher correctement) :</div>
            <div class="fs-6">Élève – Français – 1<sup>ère</sup> année — Ç à é è ù î ô û € ’ « »</div>
        </div>
    </div>
</div>

<?php if ($erreur): ?>
    <div class="alert alert-danger"><?= e($erreur) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Valeurs détectées</span>
        <span class="badge bg-<?= $total ? 'warning text-dark' : 'success' ?>"><?= $total ?> valeur(s)</span>
    </div>
    <div class="card-body">
        <?php if (!$total): ?>
            <p class="mb-0 text-success"><i class="fa-solid fa-circle-check me-1"></i>Aucun texte doublement encodé détecté dans la base.</p>
        <?php else: ?>
            <p class="text-muted small">
                Les valeurs ci-dessous seront converties (Windows-1252 → UTF-8). La correction n'est
                appliquée que si l'opération est exactement réversible, pour ne pas abîmer un texte sain.
                <strong>Sauvegardez la base avant de lancer la réparation.</strong>
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr><th>Colonne</th><th>Nb</th><th>Avant → après</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($apercu as $colonne => $info): ?>
                        <tr>
                            <td class="font-monospace small"><?= e($colonne) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= (int)$info['nb'] ?></span></td>
                            <td class="small">
                                <?php foreach ($info['lignes'] as $l): ?>
                                    <div>
                                        <span class="text-danger"><?= e($l['avant']) ?></span>
                                        <i class="fa-solid fa-arrow-right mx-1 text-muted"></i>
                                        <?php if ($l['apres'] !== null): ?>
                                            <span class="text-success"><?= e($l['apres']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">non réparable automatiquement</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" action="" onsubmit="return confirm('Appliquer la correction des accents ? Assurez-vous d\'avoir sauvegardé la base.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reparer">
                <button class="btn btn-primary"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Réparer les <?= $total ?> valeur(s)</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($rapport): ?>
    <div class="card">
        <div class="card-header">Détail de la réparation</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($rapport as $ligne): ?>
                <li class="list-group-item small"><?= e($ligne) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
