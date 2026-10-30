<?php
/**
 * ============================================================
 * My_School - Application des migrations SQL
 * ============================================================
 *
 * Applique les fichiers `config/migrations/*.sql` sur la base courante.
 *
 * Utilisation
 *   - Navigateur : ouvrir http://…/migrate.php (connexion administrateur requise)
 *   - Ligne de commande : php migrate.php [--force]
 *
 * Fonctionnement
 *   Chaque migration est appliquée une seule fois, puis enregistrée dans la
 *   table `schema_migrations` (nom du fichier + empreinte SHA-256 + date).
 *   Si un fichier est modifié après coup, il est automatiquement rejoué.
 *   Toutes les migrations du projet étant idempotentes, il suffit donc de
 *   relancer ce fichier après chaque mise à jour du code : seules les
 *   nouvelles migrations (ou celles qui ont changé) sont exécutées.
 *
 *   L'option `--force` (ou `?force=1` dans le navigateur) rejoue toutes les
 *   migrations, même celles déjà appliquées. Sans risque, mais plus lent.
 *
 * Chaque fichier est exécuté dans une transaction : en cas d'erreur, la
 * migration est annulée et le script s'arrête (les précédentes restent
 * appliquées et enregistrées).
 */

$isCli = PHP_SAPI === 'cli';

if ($isCli) {
    require_once __DIR__ . '/config/database.php';
} else {
    require_once __DIR__ . '/includes/auth.php';
    require_role(['admin']);
}

$force = $isCli
    ? in_array('--force', array_slice($argv ?? [], 1), true)
    : (($_GET['force'] ?? '') === '1');

$migrationsDir = __DIR__ . '/config/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$results = [];
$fatal   = null;
$pdo     = null;
$dbInfos = [];

try {
    $pdo = db_connect();
    $cfg = db_config();
    $dbInfos = ['base' => $cfg['dbname'], 'hote' => $cfg['host'], 'port' => $cfg['port']];
} catch (Throwable $e) {
    $fatal = 'Connexion à la base impossible : ' . $e->getMessage();
}

if ($fatal === null && $pdo !== null) {
    try {
        // Journal des migrations déjà appliquées
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                fichier     VARCHAR(255) PRIMARY KEY,
                checksum    VARCHAR(64)  NOT NULL,
                applique_le TIMESTAMP    NOT NULL DEFAULT NOW()
            )"
        );

        $applied = [];
        foreach ($pdo->query('SELECT fichier, checksum FROM schema_migrations') as $row) {
            $applied[$row['fichier']] = $row['checksum'];
        }

        $stmtMaj = $pdo->prepare(
            'INSERT INTO schema_migrations (fichier, checksum) VALUES (:f, :c)
             ON CONFLICT (fichier) DO UPDATE
                SET checksum = EXCLUDED.checksum, applique_le = NOW()'
        );

        foreach ($files as $file) {
            $nom   = basename($file);
            $sql   = (string)file_get_contents($file);
            $somme = hash('sha256', $sql);

            if (trim($sql) === '') {
                $results[] = ['fichier' => $nom, 'etat' => 'ignoree', 'detail' => 'Fichier vide'];
                continue;
            }

            $ancienne = $applied[$nom] ?? null;
            if (!$force && $ancienne !== null && hash_equals($ancienne, $somme)) {
                $results[] = ['fichier' => $nom, 'etat' => 'deja', 'detail' => 'Déjà appliquée'];
                continue;
            }

            try {
                $pdo->beginTransaction();
                $pdo->exec($sql);
                $stmtMaj->execute(['f' => $nom, 'c' => $somme]);
                $pdo->commit();

                $results[] = $ancienne === null
                    ? ['fichier' => $nom, 'etat' => 'appliquee', 'detail' => 'Appliquée']
                    : ['fichier' => $nom, 'etat' => 'rejouee', 'detail' => 'Fichier modifié : rejouée'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $results[] = ['fichier' => $nom, 'etat' => 'erreur', 'detail' => $e->getMessage()];
                $fatal = 'La migration « ' . $nom . ' » a échoué. Corrigez-la puis relancez ce fichier.';
                break;
            }
        }
    } catch (Throwable $e) {
        $fatal = 'Préparation du suivi des migrations impossible : ' . $e->getMessage();
    }
}

$nbAppliquees = 0;
foreach ($results as $r) {
    if ($r['etat'] === 'appliquee' || $r['etat'] === 'rejouee') {
        $nbAppliquees++;
    }
}

// ============================================================
// Sortie en ligne de commande
// ============================================================
if ($isCli) {
    echo "=== My_School - migrations ===\n";
    if ($dbInfos) {
        echo 'Base : ' . $dbInfos['base'] . ' @ ' . $dbInfos['hote'] . ':' . $dbInfos['port'] . "\n";
    }
    echo "\n";

    foreach ($results as $r) {
        printf("  [%-9s] %-34s %s\n", strtoupper($r['etat']), $r['fichier'], $r['detail']);
    }

    if ($fatal !== null) {
        fwrite(STDERR, "\nERREUR : " . $fatal . "\n");
        exit(1);
    }

    echo "\n" . ($nbAppliquees === 0
        ? "Tout est déjà à jour.\n"
        : $nbAppliquees . " migration(s) appliquée(s).\n");
    exit(0);
}

// ============================================================
// Sortie navigateur
// ============================================================
$styles = [
    'appliquee' => ['#0f5132', '#d1e7dd'],
    'rejouee'   => ['#664d03', '#fff3cd'],
    'deja'      => ['#084298', '#cfe2ff'],
    'ignoree'   => ['#41464b', '#e2e3e5'],
    'erreur'    => ['#842029', '#f8d7da'],
];
$libelles = [
    'appliquee' => 'Appliquée',
    'rejouee'   => 'Rejouée',
    'deja'      => 'À jour',
    'ignoree'   => 'Ignorée',
    'erreur'    => 'Erreur',
];
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrations - My_School</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 32px 16px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f3f5f9; color: #212529; line-height: 1.5;
        }
        .card {
            max-width: 860px; margin: 0 auto; background: #fff; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .08); overflow: hidden;
        }
        header { padding: 22px 26px; border-bottom: 1px solid #e6e8ee; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; }
        .sub { color: #6c757d; font-size: .875rem; }
        .content { padding: 22px 26px; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eef0f4; }
        th { color: #6c757d; font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        td.fichier { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .badge {
            display: inline-block; padding: 3px 9px; border-radius: 999px;
            font-size: .75rem; font-weight: 600; white-space: nowrap;
        }
        .detail-erreur { color: #842029; font-size: .8rem; margin-top: 4px; word-break: break-word; }
        .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; font-size: .9rem; }
        .alert-danger { background: #f8d7da; color: #842029; }
        .alert-success { background: #d1e7dd; color: #0f5132; }
        .alert-info { background: #cfe2ff; color: #084298; }
        .actions { margin-top: 22px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: 9px 16px; border-radius: 8px; text-decoration: none;
            font-size: .9rem; font-weight: 600; border: 1px solid transparent; cursor: pointer;
        }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-primary:hover { background: #0b5ed7; }
        .btn-light { background: #fff; color: #212529; border-color: #ced4da; }
        .btn-light:hover { background: #f1f3f5; }
        .note { margin-top: 18px; color: #6c757d; font-size: .8rem; }
    </style>
</head>
<body>
<div class="card">
    <header>
        <h1>Application des migrations</h1>
        <div class="sub">
            My_School
            <?php if ($dbInfos): ?>
                &middot; base <strong><?= $e($dbInfos['base']) ?></strong>
                (<?= $e($dbInfos['hote']) ?>:<?= $e($dbInfos['port']) ?>)
            <?php endif; ?>
        </div>
    </header>

    <div class="content">
        <?php if ($fatal !== null): ?>
            <div class="alert alert-danger"><strong>Échec :</strong> <?= $e($fatal) ?></div>
        <?php elseif ($nbAppliquees === 0): ?>
            <div class="alert alert-success">Tout est déjà à jour : aucune migration à appliquer.</div>
        <?php else: ?>
            <div class="alert alert-success">
                <strong><?= $nbAppliquees ?></strong> migration(s) appliquée(s) avec succès.
            </div>
        <?php endif; ?>

        <?php if (count($results) > 0): ?>
            <table>
                <thead>
                    <tr><th>Fichier</th><th>État</th><th>Détail</th></tr>
                </thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <?php [$couleur, $fond] = $styles[$r['etat']] ?? ['#212529', '#e2e3e5']; ?>
                    <tr>
                        <td class="fichier"><?= $e($r['fichier']) ?></td>
                        <td>
                            <span class="badge" style="color:<?= $couleur ?>;background:<?= $fond ?>;">
                                <?= $e($libelles[$r['etat']] ?? $r['etat']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $e($r['detail']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                Aucun fichier <code>.sql</code> trouvé dans <code>config/migrations/</code>.
            </div>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn-primary" href="scripts/migrate.php">Relancer</a>
            <a class="btn btn-light" href="scripts/migrate.php?force=1">Tout rejouer</a>
            <a class="btn btn-light" href="<?= $e(BASE_URL . 'admin/index.php') ?>">Retour au tableau de bord</a>
        </div>

        <p class="note">
            Sauvegardez la base avant une mise à jour (<code>pg_dump</code>). Les migrations
            déjà appliquées sont ignorées ; un fichier modifié est rejoué automatiquement.
        </p>
    </div>
</div>
</body>
</html>
