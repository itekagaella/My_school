<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('parametres.view');

$cle = get('cle', 'theme');
$valeur = get('valeur', 'default');

if ($cle === 'theme' && csrf_verify(get('csrf_token') ?? (($_POST['csrf_token'] ?? null)))) {
    $cssPath = 'assets/css/theme-' . preg_replace('/[^a-z]/', '', strtolower($valeur)) . '.css';
    prepareQuery('UPDATE themes SET actif = FALSE');
    prepareQuery('UPDATE themes SET actif = TRUE WHERE css_path = :p', ['p' => $cssPath]);
    log_activity('parametres.theme', 'Changement de thème : ' . $valeur);
    set_flash('success', 'Thème appliqué.');
}

header('Location: index.php');
exit;
