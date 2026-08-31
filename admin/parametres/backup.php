<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('parametres.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? 'dump';

    if ($action === 'dump') {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="sauvegarde_' . date('Ymd_His') . '.sql"');
        $dump = executable_dump();
        if ($dump) {
            prepareQuery('INSERT INTO archives (table_source, record_id, donnees_json, archive_par) VALUES (:t, :r, :d, :p)',
                ['t' => 'system', 'r' => 0, 'd' => json_encode(['sauvegarde' => date('Y-m-d H:i:s'), 'type' => 'dump_sql']), 'p' => $_SESSION['user_id']]);
            log_activity('parametres.backup', 'Sauvegarde SQL de la base de données');
        }
        echo $dump ?: "-- Sauvegarde non générée";
        exit;
    }

    if ($action === 'restore') {
        $sauvegardes = prepareQuery("SELECT * FROM archives WHERE table_source='system' ORDER BY id DESC LIMIT 20")->fetchAll();
        set_flash('info', 'Sélectionnez la sauvegarde dans la liste ci-dessous.');
        header('Location: index.php?restore=1');
        exit;
    }
}

function executable_dump() {
    $db = getDB();
    $pdo = $db;
    $dsn = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) ?? '';
    $dbn = getenv('PGDATABASE') ?: 'my_school';
    $cmd = 'pg_dump --no-owner --no-privileges -h 127.0.0.1 -U postgres ' . escapeshellarg($dbn) . ' 2>/dev/null';
    $out = shell_exec($cmd);
    return $out;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-database me-2"></i>Sauvegarde de la base de données</div>
    <div class="card-body">
        <p>Téléchargez un dump SQL complet de la base de données, ou archivez les données via les procédures stockées.</p>
        <form method="post" action="">
            <?= csrf_field() ?>
            <button type="submit" name="action" value="dump" class="btn btn-success"><i class="fa-solid fa-download me-1"></i>Télécharger le dump SQL</button>
        </form>
        <hr>
        <h6>Dernières sauvegardes</h6>
        <?php
        $archives = prepareQuery("SELECT * FROM archives WHERE table_source='system' ORDER BY id DESC LIMIT 10")->fetchAll();
        ?>
        <table class="table table-sm table-hover">
            <thead><tr><th>ID</th><th>Date</th><th>Contenu</th></tr></thead>
            <tbody>
                <?php if (empty($archives)): ?>
                    <tr><td colspan="3" class="text-center text-muted">Aucune archive système.</td></tr>
                <?php else: foreach ($archives as $a): ?>
                    <tr><td>#<?= $a['id'] ?></td><td><?= e($a['date_archive'] ?? '') ?></td><td><code><?= e($a['donnees_json']) ?></code></td></tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
