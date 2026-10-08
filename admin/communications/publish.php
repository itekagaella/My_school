<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('communications.publish');

$id = (int)get('id', 0);
$comm = prepareQuery('SELECT * FROM communications WHERE id = :id', ['id' => $id])->fetch();

if (!$comm) {
    set_flash('error', 'Communication introuvable.');
    header('Location: index.php');
    exit;
}

if ($comm['statut'] === 'publie') {
    set_flash('info', 'Cette communication est déjà publiée.');
    header('Location: view.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
        header('Location: index.php');
        exit;
    }

    prepareQuery(
        "UPDATE communications SET statut = 'publie', date_publication = NOW(), updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );

    $destinataires = array_map('trim', explode(',', $comm['destinataires']));
    $lien = 'client/communication.php?id=' . $id;
    $message = mb_substr($comm['contenu'], 0, 200);
    $userIds = [];

    foreach ($destinataires as $dest) {
        if ($dest === 'tous') {
            $users = prepareQuery('SELECT id FROM utilisateurs WHERE actif = TRUE')->fetchAll();
            foreach ($users as $u) $userIds[] = $u['id'];
            break;
        } elseif (strpos($dest, 'role:') === 0) {
            $role = substr($dest, 5);
            $users = prepareQuery('SELECT id FROM utilisateurs WHERE role = :role AND actif = TRUE', ['role' => $role])->fetchAll();
            foreach ($users as $u) $userIds[] = $u['id'];
        } elseif (strpos($dest, 'classe:') === 0) {
            $classeId = (int)substr($dest, 7);
            $users = prepareQuery(
                'SELECT DISTINCT u.id FROM utilisateurs u JOIN eleves e ON e.user_id = u.id WHERE e.classe_id = :cid AND u.actif = TRUE',
                ['cid' => $classeId]
            )->fetchAll();
            foreach ($users as $u) $userIds[] = $u['id'];
        }
    }

    $userIds = array_unique($userIds);
    foreach ($userIds as $uid) {
        notify($uid, $comm['titre'], $message, $comm['type'], $lien);
    }

    log_activity('communications.publish', 'Publication de la communication "' . $comm['titre'] . '" (ID ' . $id . ')');
    set_flash('success', 'Communication publiée. ' . count($userIds) . ' personne(s) notifiée(s).');
    header('Location: view.php?id=' . $id);
    exit;
}

$page_title = 'Publier une communication';
$active_menu = 'communications';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-paper-plane me-2"></i>Confirmer la publication</div>
    <div class="card-body">
        <?php display_flash(); ?>
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info me-2"></i>Cette communication sera publiée et les destinataires seront notifiés.
        </div>
        <div class="mb-3">
            <strong><?= e($comm['titre']) ?></strong><br>
            <span class="text-muted small">Type : <?= e(ucfirst($comm['type'])) ?> | Destinataires : <?= e($comm['destinataires']) ?></span>
        </div>
        <form method="post" action="">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success"><i class="fa-solid fa-paper-plane me-1"></i>Publier</button>
            <a href="view.php?id=<?= $comm['id'] ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
