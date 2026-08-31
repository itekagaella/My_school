<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.download');

$id = (int)get('id', 0);
$doc = prepareQuery('SELECT * FROM documents WHERE id = :id', ['id' => $id])->fetch();

if (!$doc) {
    set_flash('error', 'Document introuvable.');
    header('Location: index.php');
    exit;
}

$fullPath = ROOT_PATH . $doc['fichier_path'];

if (!file_exists($fullPath)) {
    set_flash('error', 'Le fichier physique est introuvable.');
    header('Location: index.php');
    exit;
}

log_activity('documents.download', 'Téléchargement du document ' . $doc['nom_fichier'] . ' (ID ' . $doc['id'] . ')');
download_file($fullPath, $doc['nom_fichier']);
