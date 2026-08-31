<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_permission('documents.download');

$id = (int)get('id', 0);
$ver = prepareQuery('SELECT * FROM document_versions WHERE id = :id', ['id' => $id])->fetch();

if (!$ver) {
    set_flash('error', 'Version introuvable.');
    header('Location: ' . BASE_URL . 'admin/documents/index.php');
    exit;
}
if (!file_exists(ROOT_PATH . $ver['fichier_path'])) {
    set_flash('error', 'Fichier introuvable sur le serveur.');
    header('Location: ' . BASE_URL . 'admin/documents/versions.php?document_id=' . $ver['document_id']);
    exit;
}

$display = basename($ver['fichier_path']);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $display . '"');
header('Content-Length: ' . filesize(ROOT_PATH . $ver['fichier_path']));
header('Cache-Control: no-cache');
readfile(ROOT_PATH . $ver['fichier_path']);
exit;
