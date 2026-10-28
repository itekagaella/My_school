<?php
/**
 * Traite la file d'attente des emails.
 *
 * Usage :
 *   php bin/envoyer_emails.php
 *   php bin/envoyer_emails.php --limite=50
 *
 * À planifier toutes les 2 à 5 minutes :
 *   - Windows : Planificateur de tâches → php.exe bin\envoyer_emails.php
 *   - Linux/macOS : crontab → * /5 * * * * php /chemin/bin/envoyer_emails.php
 *
 * Alternative sans CLI : ouvrir tache_emails.php?token=... (voir .env).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande.\n");
}

require_once __DIR__ . '/../includes/functions.php';

$limite = 20;
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (preg_match('/^--limite=(\d+)$/', $argument, $m)) {
        $limite = (int)$m[1];
    }
}

$resultat = email_traiter_file($limite);

printf(
    "[%s] File emails : %d envoyé(s), %d échec(s), %d restant(s).\n",
    date('Y-m-d H:i:s'),
    $resultat['envoyes'],
    $resultat['echecs'],
    $resultat['restants']
);

foreach ($resultat['erreurs'] as $erreur) {
    echo '  - ' . $erreur . "\n";
}

exit(($resultat['echecs'] > 0 && $resultat['envoyes'] === 0) ? 1 : 0);
