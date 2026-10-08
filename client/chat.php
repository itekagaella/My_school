<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (current_role() === 'admin') { header('Location: ' . BASE_URL . 'admin/index.php'); exit; }
$user = current_user();
$page_title = 'Chat';
$active_menu = 'chat';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $dest_id = intval($_POST['destinataire_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($dest_id > 0 && $message !== '' && $dest_id !== $user['id']) {
        $exists = prepareQuery(
            'SELECT id FROM utilisateurs WHERE id=:id',
            ['id'=>$dest_id]
        )->fetch();
        if ($exists) {
            prepareQuery(
                'INSERT INTO messages_chat (sender_id, receiver_id, message) VALUES (:s, :r, :m)',
                ['s'=>$user['id'], 'r'=>$dest_id, 'm'=>$message]
            );
            log_activity('chat.message_send', 'Message envoyé à #' . $dest_id);
        }
    }
    $avec = $dest_id;
    header('Location: chat.php?avec=' . $avec);
    exit;
}

$avec_id = intval($_GET['avec'] ?? 0);

$mes_conversations = prepareQuery(
    'SELECT
        CASE WHEN mc.sender_id = :uid THEN mc.receiver_id ELSE mc.sender_id END AS interlocuteur_id,
        MAX(mc.created_at) AS derniere_date
    FROM messages_chat mc
    WHERE mc.sender_id = :uid OR mc.receiver_id = :uid
    GROUP BY interlocuteur_id
    ORDER BY derniere_date DESC',
    ['uid'=>$user['id']]
)->fetchAll();

$conversations_detail = [];
foreach ($mes_conversations as $conv) {
    $inter_id = $conv['interlocuteur_id'];
    $u = prepareQuery(
        'SELECT id, nom, prenom, role, avatar FROM utilisateurs WHERE id=:id',
        ['id'=>$inter_id]
    )->fetch();
    if (!$u) continue;
    $unread = prepareQuery(
        'SELECT COUNT(*) AS nb FROM messages_chat
        WHERE sender_id=:sid AND receiver_id=:rid AND lu=FALSE',
        ['sid'=>$inter_id, 'rid'=>$user['id']]
    )->fetch();
    $conversations_detail[] = [
        'user'=>$u,
        'derniere_date'=>$conv['derniere_date'],
        'non_lus'=>$unread['nb'] ?? 0,
    ];
}

$MESSAGES = [];
$interlocuteur = null;
if ($avec_id > 0) {
    $interlocuteur = prepareQuery(
        'SELECT id, nom, prenom, role, avatar FROM utilisateurs WHERE id=:id',
        ['id'=>$avec_id]
    )->fetch();

    prepareQuery(
        'UPDATE messages_chat SET lu=TRUE
        WHERE sender_id=:sid AND receiver_id=:rid AND lu=FALSE',
        ['sid'=>$avec_id, 'rid'=>$user['id']]
    );

    $MESSAGES = prepareQuery(
        'SELECT mc.*, u.nom, u.prenom
        FROM messages_chat mc
        JOIN utilisateurs u ON u.id = mc.sender_id
        WHERE (mc.sender_id=:a AND mc.receiver_id=:b)
           OR (mc.sender_id=:b AND mc.receiver_id=:a)
        ORDER BY mc.created_at ASC',
        ['a'=>$user['id'], 'b'=>$avec_id]
    )->fetchAll();
}

$autres_users = prepareQuery(
    'SELECT id, nom, prenom, role, avatar FROM utilisateurs
    WHERE id != :uid AND actif = TRUE
    ORDER BY role, prenom, nom',
    ['uid'=>$user['id']]
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="row chat-layout<?= $interlocuteur ? ' has-thread' : '' ?>">
    <div class="col-md-4 col-lg-3 chat-list-pane border-end d-flex flex-column" style="overflow-y:auto;">
        <div class="p-3 border-bottom">
            <h6 class="mb-2"><i class="fa-solid fa-comments me-2"></i>Conversations</h6>
            <input type="text" class="form-control form-control-sm" id="searchConversations" placeholder="Rechercher...">
        </div>
        <div class="list-group list-group-flush flex-grow-1" id="listConversations">
            <?php if (empty($conversations_detail)): ?>
                <div class="text-center text-muted p-3">Aucune conversation</div>
            <?php else: foreach ($conversations_detail as $conv):
                $u = $conv['user'];
                $is_active = ($avec_id == $u['id']) ? 'active' : '';
            ?>
                <a href="chat.php?avec=<?= $u['id'] ?>"
                   class="list-group-item list-group-item-action <?= $is_active ?> d-flex align-items-center">
                    <?php if (!empty($u['avatar']) && file_exists(ROOT_PATH.$u['avatar'])): ?>
                        <img src="<?= BASE_URL.e($u['avatar']) ?>" class="rounded-circle me-2" style="width:36px;height:36px;object-fit:cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;font-size:14px;">
                            <?= e(strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1))) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold text-truncate"><?= e($u['prenom'].' '.$u['nom']) ?></span>
                            <small class="text-muted"><?= date('H:i', strtotime($conv['derniere_date'])) ?></small>
                        </div>
                        <small class="text-muted text-capitalize"><?= e($u['role']) ?></small>
                    </div>
                    <?php if ($conv['non_lus'] > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?= $conv['non_lus'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; endif; ?>
        </div>
        <div class="p-2 border-top">
            <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#newConvModal">
                <i class="fa-solid fa-plus me-1"></i>Nouvelle conversation
            </button>
        </div>
    </div>

    <div class="col-md-8 col-lg-9 chat-main-pane d-flex flex-column">
        <?php if ($interlocuteur): ?>
            <div class="p-3 border-bottom bg-white d-flex align-items-center">
                <a href="chat.php" class="btn btn-light btn-sm me-2 d-md-none" title="Retour aux conversations">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <?php if (!empty($interlocuteur['avatar']) && file_exists(ROOT_PATH.$interlocuteur['avatar'])): ?>
                    <img src="<?= BASE_URL.e($interlocuteur['avatar']) ?>" class="rounded-circle me-2" style="width:40px;height:40px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;font-size:16px;">
                        <?= e(strtoupper(mb_substr($interlocuteur['prenom'],0,1).mb_substr($interlocuteur['nom'],0,1))) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <strong><?= e($interlocuteur['prenom'].' '.$interlocuteur['nom']) ?></strong>
                    <small class="text-muted d-block text-capitalize"><?= e($interlocuteur['role']) ?></small>
                </div>
            </div>
            <div class="flex-grow-1 overflow-auto p-3" id="chatMessages" style="background:#f8f9fa;">
                <?php if (empty($MESSAGES)): ?>
                    <div class="text-center text-muted mt-5">
                        <i class="fa-solid fa-comments fa-3x mb-3"></i>
                        <p>Aucun message. Commencez la conversation !</p>
                    </div>
                <?php else: foreach ($MESSAGES as $msg):
                    $is_mine = ($msg['sender_id'] == $user['id']);
                ?>
                    <div class="d-flex mb-3 <?= $is_mine ? 'justify-content-end' : 'justify-content-start' ?>">
                        <div class="rounded-3 px-3 py-2 <?= $is_mine ? 'bg-primary text-white' : 'bg-white border' ?>" style="max-width:70%;">
                            <div class="mb-1" style="white-space:pre-wrap;"><?= e($msg['message']) ?></div>
                            <div class="text-end">
                                <small class="<?= $is_mine ? 'text-white-50' : 'text-muted' ?>">
                                    <?= date('H:i', strtotime($msg['created_at'])) ?>
                                    <?php if ($is_mine && $msg['lu']): ?>
                                        <i class="fa-solid fa-check-double ms-1"></i>
                                    <?php elseif ($is_mine): ?>
                                        <i class="fa-solid fa-check ms-1"></i>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <form method="post" class="p-3 border-top bg-white d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="destinataire_id" value="<?= $avec_id ?>">
                <input type="text" name="message" class="form-control" placeholder="Écrire un message..." required autocomplete="off">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted">
                <div class="text-center">
                    <i class="fa-solid fa-comments fa-4x mb-3"></i>
                    <h5>Sélectionnez une conversation</h5>
                    <p>ou démarrez-en une nouvelle</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="newConvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>Nouvelle conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control mb-3" id="searchUsers" placeholder="Rechercher un utilisateur...">
                <div class="list-group" id="listUsers" style="max-height:min(300px,50vh);overflow-y:auto;">
                    <?php foreach ($autres_users as $au): ?>
                        <a href="chat.php?avec=<?= $au['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                            <?php if (!empty($au['avatar']) && file_exists(ROOT_PATH.$au['avatar'])): ?>
                                <img src="<?= BASE_URL.e($au['avatar']) ?>" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:12px;">
                                    <?= e(strtoupper(mb_substr($au['prenom'],0,1).mb_substr($au['nom'],0,1))) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="fw-semibold"><?= e($au['prenom'].' '.$au['nom']) ?></span>
                                <small class="text-muted ms-2 text-capitalize"><?= e($au['role']) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var msgs = document.getElementById('chatMessages');
    if (msgs) msgs.scrollTop = msgs.scrollHeight;

    document.getElementById('searchConversations')?.addEventListener('input', function() {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#listConversations .list-group-item').forEach(function(el) {
            el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    document.getElementById('searchUsers')?.addEventListener('input', function() {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#listUsers .list-group-item').forEach(function(el) {
            el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
