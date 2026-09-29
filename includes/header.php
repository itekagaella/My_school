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
    <!-- Google Fonts : Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
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
