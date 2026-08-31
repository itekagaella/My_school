<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
$user = current_user();
$action = $_GET['action'] ?? 'messages';

if ($action === 'users') {
    $users = prepareQuery(
        'SELECT id, nom, prenom, role FROM utilisateurs WHERE id <> :u AND actif = TRUE ORDER BY prenom',
        ['u'=>$user['id']])->fetchAll();
    echo json_encode(['ok' => true, 'users' => $users]);
    exit;
}

if ($action === 'messages') {
    $avec = (int)($_GET['avec'] ?? 0);
    if ($avec > 0) {
        prepareQuery(
            'UPDATE messages_chat SET lu = TRUE WHERE sender_id = :a AND receiver_id = :u AND lu = FALSE',
            ['a'=>$avec, 'u'=>$user['id']]);
        $msgs = prepareQuery(
            'SELECT m.*, u.prenom, u.nom FROM messages_chat m JOIN utilisateurs u ON u.id=m.sender_id
             WHERE (m.sender_id=:u AND m.receiver_id=:a) OR (m.sender_id=:a AND m.receiver_id=:u)
             ORDER BY m.created_at ASC LIMIT 200',
            ['u'=>$user['id'], 'a'=>$avec])->fetchAll();
    } else {
        $msgs = prepareQuery(
            'SELECT m.*, u.prenom, u.nom FROM messages_chat m JOIN utilisateurs u ON u.id=m.sender_id
             WHERE m.receiver_id IS NULL ORDER BY m.created_at DESC LIMIT 50',
            [])->fetchAll();
    }
    echo json_encode(['ok' => true, 'messages' => $msgs]);
    exit;
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $receiver = (int)($input['receiver_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    if (!$message) { echo json_encode(['ok' => false, 'error' => 'Message vide']); exit; }
    prepareQuery(
        'INSERT INTO messages_chat (sender_id, receiver_id, message) VALUES (:s, :r, :m)',
        ['s'=>$user['id'], 'r'=>$receiver > 0 ? $receiver : null, 'm'=>$message]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
exit;
