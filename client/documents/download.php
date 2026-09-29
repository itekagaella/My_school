<?php
/**
 * Téléchargement de document (espace client : élève / prof / personnel)
 * Accès : document public (jamais partagé) OU partagé avec l'utilisateur
 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)get('id', 0);
$doc = prepareQuery('SELECT * FROM documents WHERE id = :id', ['id' => $id])->fetch();

if (!$doc) {
    set_flash('error', 'Document introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

$uid = $_SESSION['user_id'];

// Accès : auteur, admin, partagé avec moi, ou document public (jamais partagé)
$share = prepareQuery(
    'SELECT COUNT(*) AS nb FROM document_partages WHERE document_id = :did AND user_id = :uid',
    ['did' => $id, 'uid' => $uid]
)->fetch();
$nb_total_partages = prepareQuery(
    'SELECT COUNT(*) AS nb FROM document_partages WHERE document_id = :did',
    ['did' => $id]
)->fetch();

$acces = $uid === $doc['auteur_id']
      || current_role() === 'admin'
      || (int)($share['nb'] ?? 0) > 0
      || (int)($nb_total_partages['nb'] ?? 0) === 0; // document public

if (!has_permission('documents.download') || !$acces) {
    set_flash('error', 'Vous n\'avez pas accès à ce document.');
    header('Location: ' . BASE_URL);
    exit;
}

$fullPath = ROOT_PATH . $doc['fichier_path'];

if (!file_exists($fullPath)) {
    set_flash('error', 'Le fichier physique est introuvable.');
    header('Location: ' . BASE_URL);
    exit;
}

log_activity('documents.download', 'Téléchargement du document ' . $doc['nom_fichier'] . ' (ID ' . $doc['id'] . ')');
download_file($fullPath, $doc['nom_fichier']);