<?php
/**
 * Header commun des pages
 * Paramètre attendu : $page_title (optionnel), $active_menu (optionnel)
 */
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config/config.php';
}
if (!isset($page_title)) $page_title = 'My_School';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - My_School</title>
    <!-- Google Fonts : Plus Jakarta Sans (local) -->
    <link href="<?= BASE_URL ?>assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <!-- Font Awesome (local) -->
    <link href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Styles personnalisés (Academix UI) -->
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <?php
    // Chargement du thème actif
    try {
        $theme = prepareQuery('SELECT * FROM themes WHERE actif = TRUE ORDER BY id LIMIT 1')->fetch();
        if ($theme && $theme['css_path'] && file_exists(ROOT_PATH . $theme['css_path'])) {
            echo '<link href="' . BASE_URL . $theme['css_path'] . '" rel="stylesheet">';
        }
    } catch (Exception $e) { /* thème par défaut */ }
    ?>
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="app-body">
    <main class="page-wrapper">
