<?php
/**
 * Traitement de la file d'emails par URL.
 *
 * Pour le Planificateur de tâches Windows (ou un cron sans accès CLI) :
 *   http://localhost:8082/tache_emails.php?token=VOTRE_CRON_TOKEN
 *
 * Le jeton se définit dans .env (CRON_TOKEN). Sans jeton valide, la page
 * exige une session administrateur.
 */

require_once __DIR__ . '/includes/functions.php';

$jetonValide = CRON_TOKEN !== ''
    && isset($_GET['token'])
    && hash_equals(CRON_TOKEN, (string)$_GET['token']);

if (!$jetonValide) {
    require_once __DIR__ . '/includes/auth.php';
    require_role(['admin']);
}

$resultat = email_traiter_file(30);

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
