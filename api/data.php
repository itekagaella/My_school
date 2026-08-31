<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
$user = current_user();

$stat = $_GET['stat'] ?? '';

if ($user['role'] === 'admin') {
    if ($stat === 'dashboard') {
        $d = getDB()->query('SELECT * FROM sp_dashboard_stats()')->fetch();
        echo json_encode(['ok' => true, 'stats' => $d]); exit;
    }
    if ($stat === 'effectifs_classes') {
        $data = prepareQuery(
            "SELECT c.nom_classe, COUNT(e.id) AS nb FROM classes c LEFT JOIN eleves e ON e.classe_id=c.id
             GROUP BY c.id, c.nom_classe ORDER BY c.nom_classe")->fetchAll();
        echo json_encode(['ok' => true, 'data' => $data]); exit;
    }
}

if ($stat === 'moyenne' && $user['role'] === 'eleve') {
    $el = prepareQuery('SELECT id FROM eleves WHERE user_id=:u', ['u'=>$user['id']])->fetch();
    $m = $el ? prepareQuery('SELECT sp_calculer_moyenne(:e) AS moy', ['e'=>$el['id']])->fetch() : ['moy'=>0];
    echo json_encode(['ok' => true, 'moyenne' => $m['moy'] ?? 0]); exit;
}

if ($stat === 'mes_presences' && $user['role'] === 'eleve') {
    $el = prepareQuery('SELECT id FROM eleves WHERE user_id=:u', ['u'=>$user['id']])->fetch();
    $data = $el ? prepareQuery(
        'SELECT statut, COUNT(*) AS nb FROM presences WHERE eleve_id=:e GROUP BY statut',
        ['e'=>$el['id']])->fetchAll() : [];
    echo json_encode(['ok' => true, 'data' => $data]); exit;
}

echo json_encode(['ok' => false, 'error' => 'Statistique non disponible']);
exit;
