<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.view');

$id = (int)get('id', 0);
$doc = prepareQuery("SELECT * FROM archives_electroniques WHERE id = :id AND statut='actif'", ['id' => $id])->fetch();
if (!$doc) {
    set_flash('error', 'Document introuvable.');
    header('Location: index.php');
    exit;
}

$filepath = ROOT_PATH . $doc['fichier_chemin'];
if (!file_exists($filepath)) {
    set_flash('error', 'Fichier physique introuvable.');
    header('Location: index.php');
    exit;
}

log_activity('archives_electroniques.telechargement', 'Téléchargement du document ' . $doc['reference']);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($doc['fichier_nom']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
