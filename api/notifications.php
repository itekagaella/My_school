<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        prepareQuery('UPDATE notifications SET lu = TRUE WHERE id = :id AND user_id = :u', ['id'=>$id,'u'=>$user['id']]);
    } else {
        prepareQuery('UPDATE notifications SET lu = TRUE WHERE user_id = :u', ['u'=>$user['id']]);
    }
    header('Location: notifications.php');
    exit;
}

$notifications = prepareQuery(
    'SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT 50',
    ['u'=>$user['id']])->fetchAll();

$page_title = 'Notifications';
$active_menu = 'notifications';

$role = $user['role'];
require_once __DIR__ . '/../includes/header.php';
if ($role === 'admin') {
    require_once __DIR__ . '/../includes/sidebar_admin.php';
} else {
    require_once __DIR__ . '/../includes/sidebar_client.php';
}
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-bell me-2"></i>Mes notifications</span>
        <?php if ($notifications): ?>
            <form method="post" action="" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="0">
                <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-check-double me-1"></i>Tout marquer comme lu</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($notifications)): ?>
            <p class="text-muted text-center py-5"><i class="fa-solid fa-bell-slash fa-3x d-block mb-3"></i>Aucune notification.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item <?= $n['lu'] ? '' : 'list-group-item-light fw-bold' ?> d-flex justify-content-between align-items-start">
                        <div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-<?= $n['type']==='alerte'?'danger':($n['type']==='succes'?'success':($n['type']==='erreur'?'warning':'info')) ?> me-2"><?= e($n['type']) ?></span>
                                <span><?= e($n['titre']) ?></span>
                            </div>
                            <?php if ($n['message']): ?><div class="text-muted small mt-1"><?= nl2br(e($n['message'])) ?></div><?php endif; ?>
                            <small class="text-muted d-block mt-1"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></small>
                            <?php if ($n['lien']): ?><a href="<?= e($n['lien']) ?>" class="small">Voir</a><?php endif; ?>
                        </div>
                        <?php if (!$n['lu']): ?>
                            <form method="post" action="">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                <button class="btn btn-sm btn-outline-primary" title="Marquer lu"><i class="fa-solid fa-check"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
