<?php
/**
 * Notifications par email : file d'attente, modèles et évènements métier.
 *
 * Principe : une action métier (absence, sanction, paiement, note,
 * communication…) appelle une fonction `notifier_*`. Celle-ci :
 *   - crée la notification interne (cloche) pour l'élève ;
 *   - met un email en file d'attente pour l'élève et/ou ses parents.
 *
 * Aucun envoi réseau n'a lieu pendant la requête : le worker
 * `bin/envoyer_emails.php` (ou le bouton admin) traite la file.
 */

require_once __DIR__ . '/mailer.php';

/**
 * Une catégorie est active par défaut ; une ligne dans preferences_email
 * avec actif = FALSE la désactive.
 */
function email_preference_active(int $userId, string $categorie): bool
{
    try {
        $row = prepareQuery(
            'SELECT actif FROM preferences_email WHERE user_id = :uid AND categorie = :cat',
            ['uid' => $userId, 'cat' => $categorie]
        )->fetch();
        return !$row || !empty($row['actif']);
    } catch (Throwable $e) {
        return true;
    }
}

/**
 * Ajoute un email à la file d'attente.
 *
 * @param ?string $reference clé unique (ex. « presence:12:eleve@… ») pour
 *                           éviter les doublons si l'action est rejouée.
 */
function email_mettre_en_file(
    string $destinataire,
    string $sujet,
    string $html,
    string $texte,
    string $categorie = 'info',
    ?int $userId = null,
    ?string $reference = null
): bool {
    $destinataire = trim($destinataire);
    if (!is_valid_email($destinataire)) {
        error_log('[email] Destinataire invalide : ' . $destinataire);
        return false;
    }

    try {
        $stmt = prepareQuery(
            'INSERT INTO emails_file_attente
                (user_id, destinataire, sujet, corps_texte, corps_html, categorie, reference)
             VALUES (:uid, :to, :sujet, :texte, :html, :cat, :ref)
             ON CONFLICT (reference) DO NOTHING
             RETURNING id',
            [
                'uid'   => $userId,
                'to'    => $destinataire,
                'sujet' => mb_substr($sujet, 0, 250),
                'texte' => $texte,
                'html'  => $html,
                'cat'   => $categorie,
                'ref'   => $reference,
            ]
        );
        // false = doublon (référence déjà en file) ou insertion impossible
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // La table peut ne pas encore exister (migration non lancée) :
        // on ne casse jamais l'action métier pour autant.
        error_log('[email] Mise en file impossible : ' . $e->getMessage());
        return false;
    }
}

/**
 * Construit le HTML + le texte d'un email à partir d'un contenu simple.
 *
 * @param array<string,string> $details lignes « libellé → valeur »
 * @return array{html:string,texte:string}
 */
function email_modele(
    string $titre,
    string $intro,
    array $details = [],
    ?string $lien = null,
    string $libelleLien = 'Ouvrir My_School'
): array {
    $ecole = (string)get_param('nom_etablissement', 'My_School');
    $url = null;
    if ($lien !== null && $lien !== '') {
        $url = preg_match('#^https?://#i', $lien)
            ? $lien
            : rtrim(BASE_URL, '/') . '/' . ltrim($lien, '/');
    }

    $titreHtml = e($titre);
    $introHtml = nl2br(e($intro));
    $ecoleHtml = e($ecole);

    $detailsHtml = '';
    foreach ($details as $label => $valeur) {
        if ($valeur === '' || $valeur === null) {
            continue;
        }
        $detailsHtml .= '<tr>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e9ecef;color:#6c757d;font-size:14px;">' . e($label) . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e9ecef;color:#212529;font-size:14px;font-weight:600;">' . e($valeur) . '</td>'
            . '</tr>';
    }

    $bouton = '';
    if ($url !== null) {
        $bouton = '<p style="text-align:center;margin:28px 0 8px;">'
            . '<a href="' . e($url) . '" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;'
            . 'padding:12px 22px;border-radius:8px;font-weight:600;font-size:14px;">' . e($libelleLien) . '</a>'
            . '</p>';
    }

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . $titreHtml . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">'
        . '<tr><td style="background:#4f46e5;padding:18px 24px;color:#ffffff;font-size:18px;font-weight:700;">' . $ecoleHtml . '</td></tr>'
        . '<tr><td style="padding:28px 24px 8px;">'
        . '<h1 style="margin:0 0 14px;font-size:20px;color:#111827;">' . $titreHtml . '</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#374151;">' . $introHtml . '</p>';

    if ($detailsHtml !== '') {
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:8px 0 12px;">' . $detailsHtml . '</table>';
    }

    $html .= $bouton
        . '</td></tr>'
        . '<tr><td style="padding:16px 24px 24px;color:#9ca3af;font-size:12px;line-height:1.5;">'
        . 'Cet email est envoyé automatiquement par ' . $ecoleHtml . '. Merci de ne pas y répondre.'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $texte = $titre . "\n\n" . $intro . "\n\n";
    foreach ($details as $label => $valeur) {
        if ($valeur !== '' && $valeur !== null) {
            $texte .= $label . ' : ' . $valeur . "\n";
        }
    }
    if ($url !== null) {
        $texte .= "\n" . $libelleLien . ' : ' . $url . "\n";
    }
    $texte .= "\n— " . $ecole;

    return ['html' => $html, 'texte' => $texte];
}

/**
 * Destinataires d'un évènement lié à un élève : le compte élève (s'il
 * existe) et l'adresse des parents (sans compte nécessaire).
 *
 * @return array<int,array{email:string,nom:string,type:string,user_id:?int}>
 */
function email_destinataires_eleve(int $eleveId): array
{
    try {
        $row = prepareQuery(
            'SELECT e.prenom, e.nom, e.parent_email,
                    u.id AS eleve_user_id, u.email AS eleve_email, u.actif AS eleve_actif
               FROM eleves e
               LEFT JOIN utilisateurs u ON u.id = e.user_id
              WHERE e.id = :id',
            ['id' => $eleveId]
        )->fetch();
    } catch (Throwable $e) {
        error_log('[email] Destinataires élève : ' . $e->getMessage());
        return [];
    }

    if (!$row) {
        return [];
    }

    $nom = trim((string)$row['prenom'] . ' ' . (string)$row['nom']);
    $destinataires = [];
    $vus = [];

    if (!empty($row['eleve_email']) && !empty($row['eleve_actif']) && is_valid_email($row['eleve_email'])) {
        $email = (string)$row['eleve_email'];
        $destinataires[] = [
            'email'   => $email,
            'nom'     => $nom,
            'type'    => 'eleve',
            'user_id' => $row['eleve_user_id'] !== null ? (int)$row['eleve_user_id'] : null,
        ];
        $vus[mb_strtolower($email)] = true;
    }

    if (!empty($row['parent_email']) && is_valid_email($row['parent_email'])) {
        $email = (string)$row['parent_email'];
        if (!isset($vus[mb_strtolower($email)])) {
            $destinataires[] = ['email' => $email, 'nom' => 'Parent de ' . $nom, 'type' => 'parent', 'user_id' => null];
        }
    }

    return $destinataires;
}

/**
 * Notification (cloche + email) pour un compte utilisateur.
 */
function email_notifier_utilisateur(
    int $userId,
    string $categorie,
    string $titre,
    string $intro,
    array $details = [],
    ?string $lien = null,
    ?string $reference = null,
    string $libelleLien = 'Ouvrir My_School'
): void {
    try {
        $u = prepareQuery(
            'SELECT id, email, prenom, nom, actif FROM utilisateurs WHERE id = :id',
            ['id' => $userId]
        )->fetch();
    } catch (Throwable $e) {
        return;
    }

    if (!$u || empty($u['actif']) || empty($u['email']) || !is_valid_email($u['email'])) {
        return;
    }
    if (!email_preference_active($userId, $categorie)) {
        return;
    }

    $modele = email_modele($titre, $intro, $details, $lien, $libelleLien);
    email_mettre_en_file(
        (string)$u['email'],
        $titre,
        $modele['html'],
        $modele['texte'],
        $categorie,
        $userId,
        $reference
    );
}

/**
 * Envoie un évènement « élève » à l'élève et à ses parents.
 */
function email_notifier_eleve(
    int $eleveId,
    string $categorie,
    string $titre,
    string $intro,
    array $details = [],
    ?string $lienEleve = null,
    ?string $referenceBase = null
): void {
    foreach (email_destinataires_eleve($eleveId) as $dest) {
        $lien = $dest['type'] === 'eleve' ? $lienEleve : null;
        $reference = $referenceBase !== null ? $referenceBase . ':' . $dest['email'] : null;
        $modele = email_modele(
            $titre,
            $intro,
            $details,
            $lien,
            $dest['type'] === 'eleve' ? 'Ouvrir mon espace' : 'Ouvrir My_School'
        );
        $enfile = email_mettre_en_file(
            $dest['email'],
            $titre,
            $modele['html'],
            $modele['texte'],
            $categorie,
            $dest['user_id'],
            $reference
        );

        if ($enfile && $dest['type'] === 'eleve' && !empty($dest['user_id'])) {
            notify((int)$dest['user_id'], $titre, mb_substr($intro, 0, 200), 'info', $lien);
        }
    }
}

// ============================================================
// ÉVÈNEMENTS MÉTIER
// ============================================================

function notifier_presence(int $presenceId, int $eleveId, string $date, string $statut, ?string $motif): void
{
    if (!in_array($statut, ['absent', 'retard'], true)) {
        return;
    }

    $row = prepareQuery('SELECT prenom, nom FROM eleves WHERE id = :id', ['id' => $eleveId])->fetch();
    if (!$row) {
        return;
    }

    $nom = trim((string)$row['prenom'] . ' ' . (string)$row['nom']);
    $libelle = $statut === 'retard' ? 'Retard' : 'Absence';
    $titre = $libelle . ' — ' . $nom;
    $intro = 'Une ' . mb_strtolower($libelle) . ' a été enregistrée pour ' . $nom . '.';
    $details = [
        'Élève' => $nom,
        'Date'  => date('d/m/Y', strtotime($date)),
    ];
    if (!empty($motif)) {
        $details['Motif'] = $motif;
    }

    email_notifier_eleve(
        $eleveId,
        'absence',
        $titre,
        $intro,
        $details,
        'client/eleve/index.php',
        'presence:' . $presenceId
    );
}

function notifier_sanction(
    int $sanctionId,
    int $eleveId,
    string $type,
    ?string $description,
    string $dateDebut,
    ?string $dateFin,
    ?int $dureeJours
): void {
    $row = prepareQuery('SELECT prenom, nom FROM eleves WHERE id = :id', ['id' => $eleveId])->fetch();
    if (!$row) {
        return;
    }

    $nom = trim((string)$row['prenom'] . ' ' . (string)$row['nom']);
    $titre = 'Sanction — ' . $nom;
    $intro = 'Une sanction a été enregistrée pour ' . $nom . '.';
    $details = [
        'Élève' => $nom,
        'Type'  => $type,
        'Début' => date('d/m/Y', strtotime($dateDebut)),
    ];
    if (!empty($dateFin)) {
        $details['Fin'] = date('d/m/Y', strtotime($dateFin));
    }
    if ($dureeJours !== null) {
        $details['Durée'] = $dureeJours . ' jour(s)';
    }
    if (!empty($description)) {
        $details['Description'] = $description;
    }

    email_notifier_eleve(
        $eleveId,
        'sanction',
        $titre,
        $intro,
        $details,
        'client/eleve/index.php',
        'sanction:' . $sanctionId
    );
}

function notifier_convocation(
    int $convocationId,
    int $eleveId,
    string $date,
    ?string $heure,
    string $motif
): void {
    $row = prepareQuery('SELECT prenom, nom FROM eleves WHERE id = :id', ['id' => $eleveId])->fetch();
    if (!$row) {
        return;
    }

    $nom = trim((string)$row['prenom'] . ' ' . (string)$row['nom']);
    $titre = 'Convocation — ' . $nom;
    $intro = 'Une convocation a été enregistrée pour ' . $nom . '.';
    $details = [
        'Élève' => $nom,
        'Date'  => date('d/m/Y', strtotime($date)),
    ];
    if (!empty($heure)) {
        $details['Heure'] = substr($heure, 0, 5);
    }
    $details['Motif'] = $motif;

    email_notifier_eleve(
        $eleveId,
        'convocation',
        $titre,
        $intro,
        $details,
        'client/eleve/index.php',
        'convocation:' . $convocationId
    );
}

function notifier_paiement(
    int $paiementId,
    int $eleveId,
    float $montant,
    string $recu,
    string $date,
    string $mode,
    ?int $fraisId
): void {
    $row = prepareQuery('SELECT prenom, nom FROM eleves WHERE id = :id', ['id' => $eleveId])->fetch();
    if (!$row) {
        return;
    }

    $nom = trim((string)$row['prenom'] . ' ' . (string)$row['nom']);
    $titre = 'Paiement reçu — ' . $recu;
    $intro = 'Un paiement a été enregistré pour ' . $nom . '.';
    $details = [
        'Élève'  => $nom,
        'Reçu'   => $recu,
        'Montant' => function_exists('format_montant') ? format_montant($montant) : number_format($montant, 2, ',', ' ') . ' FBu',
        'Date'   => date('d/m/Y', strtotime($date)),
        'Mode'   => function_exists('modes_paiement') ? (modes_paiement()[$mode] ?? $mode) : $mode,
    ];

    if ($fraisId !== null && $fraisId > 0) {
        try {
            $libelle = prepareQuery('SELECT libelle FROM frais_scolaires WHERE id = :id', ['id' => $fraisId])->fetchColumn();
            if ($libelle) {
                $details['Frais'] = (string)$libelle;
            }
        } catch (Throwable $e) {
            // sans importance
        }
    }

    email_notifier_eleve(
        $eleveId,
        'paiement',
        $titre,
        $intro,
        $details,
        'client/eleve/index.php',
        'paiement:' . $paiementId
    );
}

function notifier_note(
    int $noteId,
    int $eleveId,
    int $matiereId,
    string $typeEvaluation,
    string $date,
    float $note,
    ?string $appreciation
): void {
    $row = prepareQuery('SELECT prenom, nom FROM eleves WHERE id = :id', ['id' => $eleveId])->fetch();
    if (!$row) {
        return;
    }

    $matiere = '';
    try {
        $matiere = (string)prepareQuery('SELECT nom_matiere FROM matieres WHERE id = :id', ['id' => $matiereId])->fetchColumn();
    } catch (Throwable $e) {
        // sans importance
    }

    $types = [
        'devoir'       => 'Devoir',
        'composition'  => 'Composition',
        'interrogation' => 'Interrogation',
        'trimestriel'  => 'Examen trimestriel',
    ];

    $nom = trim((string)$row['prenom'] . ' ' . (string)$row['nom']);
    $titre = 'Nouvelle note' . ($matiere !== '' ? ' — ' . $matiere : '');
    $intro = 'Une nouvelle note a été enregistrée pour ' . $nom . '.';
    $details = [
        'Élève'       => $nom,
        'Matière'     => $matiere,
        'Évaluation'  => $types[$typeEvaluation] ?? $typeEvaluation,
        'Date'        => date('d/m/Y', strtotime($date)),
        'Note'        => number_format($note, 2, ',', ' ') . ' / 20',
    ];
    if (!empty($appreciation)) {
        $details['Appréciation'] = $appreciation;
    }

    email_notifier_eleve(
        $eleveId,
        'note',
        $titre,
        $intro,
        $details,
        'client/eleve/index.php',
        'note:' . $noteId
    );
}

/**
 * Communication : notification interne + email pour chaque destinataire
 * concerné (comptes ciblés, et parents pour une communication de classe).
 * Remplace l'ancienne fonction send_notifications() dupliquée dans
 * admin/communications/create.php et edit.php.
 */
function communication_notifier_destinataires(
    int $commId,
    array $destinataires,
    string $titre,
    string $contenu,
    string $type
): void {
    $lien = 'client/communication.php?id=' . $commId;
    $extrait = mb_substr(trim(strip_tags($contenu)), 0, 300);
    $userIds = [];
    $parentsEmails = [];

    foreach ($destinataires as $dest) {
        $dest = (string)$dest;

        if ($dest === 'tous') {
            $users = prepareQuery('SELECT id FROM utilisateurs WHERE actif = TRUE')->fetchAll();
            foreach ($users as $u) {
                $userIds[] = (int)$u['id'];
            }
            break;
        }

        if (strpos($dest, 'role:') === 0) {
            $role = substr($dest, 5);
            $users = prepareQuery(
                'SELECT id FROM utilisateurs WHERE role = :role AND actif = TRUE',
                ['role' => $role]
            )->fetchAll();
            foreach ($users as $u) {
                $userIds[] = (int)$u['id'];
            }
        } elseif (strpos($dest, 'classe:') === 0) {
            $classeId = (int)substr($dest, 7);
            if ($classeId <= 0) {
                continue;
            }
            $users = prepareQuery(
                'SELECT DISTINCT u.id FROM utilisateurs u
                   JOIN eleves e ON e.user_id = u.id
                  WHERE e.classe_id = :cid AND u.actif = TRUE',
                ['cid' => $classeId]
            )->fetchAll();
            foreach ($users as $u) {
                $userIds[] = (int)$u['id'];
            }

            $parents = prepareQuery(
                "SELECT DISTINCT parent_email FROM eleves
                  WHERE classe_id = :cid AND statut = 'actif'
                    AND parent_email IS NOT NULL AND parent_email <> ''",
                ['cid' => $classeId]
            )->fetchAll();
            foreach ($parents as $p) {
                if (is_valid_email((string)$p['parent_email'])) {
                    $parentsEmails[] = (string)$p['parent_email'];
                }
            }
        }
    }

    $typeLabel = ucfirst($type);
    $details = ['Type' => $typeLabel];

    foreach (array_unique($userIds) as $uid) {
        notify($uid, $titre, mb_substr($extrait, 0, 200), $type, $lien);
        email_notifier_utilisateur(
            $uid,
            'communication',
            $titre,
            $extrait,
            $details,
            $lien,
            'communication:' . $commId . ':u' . $uid,
            'Lire la communication'
        );
    }

    foreach (array_unique($parentsEmails) as $email) {
        $modele = email_modele($titre, $extrait, $details, null);
        email_mettre_en_file(
            $email,
            $titre,
            $modele['html'],
            $modele['texte'],
            'communication',
            null,
            'communication:' . $commId . ':' . $email
        );
    }
}

// ============================================================
// TRAITEMENT DE LA FILE
// ============================================================

/**
 * Traite les emails en attente.
 *
 * @return array{envoyes:int,echecs:int,restants:int,erreurs:array<int,string>}
 */
function email_traiter_file(int $limite = 20): array
{
    $limite = max(1, min(100, $limite));
    $resultat = ['envoyes' => 0, 'echecs' => 0, 'restants' => 0, 'erreurs' => []];
    $db = getDB();

    // Débloque les lignes restées « en_cours » après un arrêt brutal.
    try {
        $db->exec("UPDATE emails_file_attente SET statut = 'en_attente'
                   WHERE statut = 'en_cours' AND cree_le < NOW() - INTERVAL '10 minutes'");
    } catch (Throwable $e) {
        // table absente : rien à faire
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare(
            "UPDATE emails_file_attente SET statut = 'en_cours'
              WHERE id IN (
                    SELECT id FROM emails_file_attente
                     WHERE statut = 'en_attente'
                     ORDER BY id
                     LIMIT :limite
                     FOR UPDATE SKIP LOCKED
              )
              RETURNING *"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[email] Lecture de la file impossible : ' . $e->getMessage());
        return $resultat;
    }

    foreach ($lignes as $ligne) {
        $ok = false;
        $erreur = null;

        if (MAIL_TRANSPORT === 'log') {
            // Mode démo : l'archivage .eml tient lieu d'envoi.
            $ok = true;
        } elseif (MAIL_TRANSPORT === 'smtp') {
            $mailer = new SmtpMailer([
                'host' => defined('MAIL_HOST') ? MAIL_HOST : getenv('MAIL_HOST'),
                'port' => defined('MAIL_PORT') ? MAIL_PORT : (int)getenv('MAIL_PORT'),
                'encryption' => defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : getenv('MAIL_ENCRYPTION'),
                'username' => defined('MAIL_USERNAME') ? MAIL_USERNAME : getenv('MAIL_USERNAME'),
                'password' => defined('MAIL_PASSWORD') ? MAIL_PASSWORD : getenv('MAIL_PASSWORD'),
                'from' => defined('MAIL_FROM') ? MAIL_FROM : getenv('MAIL_FROM'),
                'from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : getenv('MAIL_FROM_NAME'),
                'timeout' => defined('MAIL_TIMEOUT') ? MAIL_TIMEOUT : (int)(getenv('MAIL_TIMEOUT') ?: 10),
            ]);
            $ok = $mailer->envoyer(
                (string)$ligne['destinataire'],
                (string)$ligne['sujet'],
                (string)$ligne['corps_html'],
                (string)$ligne['corps_texte']
            );
            if (!$ok) {
                $erreur = $mailer->dernierErreur();
            }
        } else {
            $ok = email_transporter(
                (string)$ligne['destinataire'],
                (string)$ligne['sujet'],
                (string)$ligne['corps_html'],
                (string)$ligne['corps_texte']
            );
            if (!$ok) {
                $erreur = 'La fonction mail() a échoué.';
            }
        }

        email_store(
            (string)$ligne['destinataire'],
            (string)$ligne['sujet'],
            (string)($ligne['corps_texte'] !== '' ? $ligne['corps_texte'] : strip_tags((string)$ligne['corps_html'])),
            $ok && MAIL_TRANSPORT !== 'log',
            $ok ? (MAIL_TRANSPORT === 'log' ? 'local' : 'sent') : 'echec'
        );

        if ($ok) {
            prepareQuery(
                "UPDATE emails_file_attente
                    SET statut = 'envoye', envoye_le = NOW(), derniere_erreur = NULL
                  WHERE id = :id",
                ['id' => $ligne['id']]
            );
            $resultat['envoyes']++;
        } else {
            $tentatives = (int)$ligne['tentatives'] + 1;
            $statut = $tentatives >= 3 ? 'echec' : 'en_attente';
            prepareQuery(
                'UPDATE emails_file_attente
                    SET statut = :st, tentatives = :t, derniere_erreur = :err
                  WHERE id = :id',
                ['st' => $statut, 't' => $tentatives, 'err' => $erreur, 'id' => $ligne['id']]
            );
            $resultat['echecs']++;
            $resultat['erreurs'][] = $ligne['destinataire'] . ' : ' . $erreur;
        }
    }

    try {
        $resultat['restants'] = (int)prepareQuery(
            "SELECT COUNT(*) FROM emails_file_attente WHERE statut IN ('en_attente', 'en_cours')"
        )->fetchColumn();
    } catch (Throwable $e) {
        // sans importance
    }

    return $resultat;
}

/**
 * Remet un email en échec dans la file.
 */
function email_relancer(int $id): bool
{
    try {
        prepareQuery(
            "UPDATE emails_file_attente
                SET statut = 'en_attente', tentatives = 0, derniere_erreur = NULL
              WHERE id = :id AND statut = 'echec'",
            ['id' => $id]
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
