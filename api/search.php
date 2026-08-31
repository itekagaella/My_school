<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
$result = ['ok' => true, 'eleves' => [], 'profs' => [], 'matieres' => [], 'documents' => [], 'users' => []];

if ($q !== '') {
    $like = '%' . $q . '%';
    $result['eleves'] = prepareQuery(
        "SELECT e.matricule, e.nom, e.prenom, c.nom_classe FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id
         WHERE e.nom ILIKE :q OR e.prenom ILIKE :q OR e.matricule ILIKE :q LIMIT 10", ['q'=>$like])->fetchAll();
    $result['profs'] = prepareQuery(
        "SELECT matricule, nom, prenom, specialite FROM profs WHERE nom ILIKE :q OR prenom ILIKE :q OR matricule ILIKE :q LIMIT 10", ['q'=>$like])->fetchAll();
    $result['matieres'] = prepareQuery(
        "SELECT m.nom_matiere, m.code, c.nom_classe FROM matieres m LEFT JOIN classes c ON c.id=m.classe_id
         WHERE m.nom_matiere ILIKE :q OR m.code ILIKE :q LIMIT 10", ['q'=>$like])->fetchAll();
    $result['documents'] = prepareQuery(
        "SELECT id, nom_fichier, type_fichier FROM documents WHERE nom_fichier ILIKE :q AND statut='actif' LIMIT 10", ['q'=>$like])->fetchAll();
    $result['users'] = prepareQuery(
        "SELECT id, nom, prenom, role FROM utilisateurs WHERE (nom ILIKE :q OR prenom ILIKE :q OR email ILIKE :q) AND id<>:u LIMIT 10", ['q'=>$like,'u'=>current_user()['id']])->fetchAll();
}

echo json_encode($result);
exit;
