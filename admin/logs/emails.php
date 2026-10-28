<?php
/**
 * Boîte email (Admin)
 *
 * Deux parties :
 *   1. File d'attente des notifications (table emails_file_attente) :
 *      traitement manuel, relance des échecs, envoi d'un email de test.
 *   2. Emails archivés dans storage/mail/ (.eml) : trace de tous les envois,
 *      y compris les codes 2FA et les liens de réinitialisation.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('logs.view');

// Fallback si la constante n'est pas encore définie
if (!defined('MAIL_TRANSPORT')) {
    if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
        define('MAIL_TRANSPORT', 'mail');
    } else {
        define('MAIL_TRANSPORT', 'log');
    }
}
$mailTransport = MAIL_TRANSPORT;

$page_title = 'Boîte email';
$active_menu = 'logs_emails';

$dir = ROOT_PATH . 'storage/mail';

/**
 * Badge HTML pour un statut de la file d'attente.
 */
function email_statut_badge(string $statut): string
{
    switch ($statut) {
        case 'envoye':
            return '<span class="badge bg-success">Envoyé</span>';
        case 'en_attente':
            return '<span class="badge bg-warning text-dark">En attente</span>';
        case 'en_cours':
            return '<span class="badge bg-info text-dark">En cours</span>';
        case 'echec':
            return '<span class="badge bg-danger">Échec</span>';
        default:
            return '<span class="badge bg-secondary">' . e($statut) . '</span>';
    }
}

// ---- Actions (POST + CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée, veuillez réessayer.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete_one') {
            $name = basename((string)($_POST['file'] ?? ''));
            if (preg_match('/^[0-9]{8}_[0-9]{6}_[0-9a-f]{8}\.eml$/', $name) && is_file($dir . '/' . $name)) {
                unlink($dir . '/' . $name);
                set_flash('success', 'Email supprimé.');
            } else {
                set_flash('error', 'Fichier invalide.');
            }
        } elseif ($action === 'purge') {
            $n = 0;
            foreach (glob($dir . '/*.eml') ?: [] as $f) {
                if (unlink($f)) $n++;
            }
            set_flash('success', $n . ' email(s) supprimé(s).');
        } elseif ($action === 'traiter_file') {
            $r = email_traiter_file(50);
            set_flash(
                $r['echecs'] > 0 ? 'warning' : 'success',
                sprintf(
                    'File traitée : %d envoyé(s), %d échec(s), %d restant(s).',
                    $r['envoyes'],
                    $r['echecs'],
                    $r['restants']
                )
            );
            if ($r['erreurs']) {
                set_flash('error', 'Dernière erreur : ' . $r['erreurs'][0]);
            }
        } elseif ($action === 'relancer') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && email_relancer($id)) {
                set_flash('success', 'Email remis en attente.');
            } else {
                set_flash('error', 'Impossible de relancer cet email.');
            }
        } elseif ($action === 'test') {
            require_once ROOT_PATH . 'fix_mail.php';
            mailer_charger_env();
            $to = trim((string)($_POST['email'] ?? ''));
            if (!is_valid_email($to)) {
                set_flash('error', 'Adresse email invalide.');
            } else {
                $smtpHost = defined('MAIL_HOST') ? MAIL_HOST : (getenv('MAIL_HOST') ?: '');
                $smtpPort = defined('MAIL_PORT') ? MAIL_PORT : (getenv('MAIL_PORT') ?: '');
                $modele = email_modele(
                    'Test d\'envoi My_School',
                    'Cet email confirme que la configuration email fonctionne.',
                    [
                        'Transport' => $mailTransport,
                        'Serveur'   => $mailTransport === 'smtp' ? ($smtpHost . ':' . $smtpPort) : '—',
                        'Date'      => date('d/m/Y H:i:s'),
                    ]
                );
                email_mettre_en_file($to, 'Test d\'envoi My_School', $modele['html'], $modele['texte'], 'test');
                $r = email_traiter_file(5);
                if ($r['envoyes'] > 0) {
                    set_flash('success', $mailTransport === 'log'
                        ? 'Mode démo : email de test archivé dans la boîte locale.'
                        : 'Email de test envoyé à ' . $to . '.');
                } else {
                    set_flash('error', 'Échec de l\'envoi de test' . ($r['erreurs'] ? ' : ' . $r['erreurs'][0] : '.'));
                }
            }
        }
    }

    $redirect = 'emails.php' . (isset($_GET['file']) ? '?file=' . urlencode(basename((string)$_GET['file'])) : '');
    header('Location: ' . $redirect);
    exit;
}

// ---- File d'attente ----
$stats = ['en_attente' => 0, 'en_cours' => 0, 'envoye' => 0, 'echec' => 0];
$file = [];
try {
    foreach (prepareQuery(
        'SELECT statut, COUNT(*) AS nb FROM emails_file_attente GROUP BY statut'
    )->fetchAll() as $row) {
        $stats[$row['statut']] = (int)$row['nb'];
    }
    $file = prepareQuery(
        'SELECT * FROM emails_file_attente ORDER BY id DESC LIMIT 30'
    )->fetchAll();
} catch (Throwable $e) {
    // Migration non appliquée : la file est simplement vide.
}
$restants = $stats['en_attente'] + $stats['en_cours'];

// ---- Lecture des emails archivés ----
$emails = [];
if (is_dir($dir)) {
    foreach (glob($dir . '/*.eml') ?: [] as $path) {
        $name = basename($path);
        $raw = (string)@file_get_contents($path);
        $headers = [];
        $body = $raw;
        $pos = strpos($raw, "\r\n\r\n");
        if ($pos === false) $pos = strpos($raw, "\n\n");
        if ($pos !== false) {
            $body = substr($raw, $pos + 4);
            foreach (preg_split('/\r?\n/', substr($raw, 0, $pos)) as $line) {
                if (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
            }
        }
        $emails[] = [
            'name'    => $name,
            'path'    => $path,
            'date'    => @filemtime($path),
            'to'      => $headers['to'] ?? '',
            'subject' => $headers['subject'] ?? '',
            'status'  => $headers['x-myschool-status'] ?? 'local',
            'size'    => strlen($raw),
            'body'    => $body,
        ];
    }
    usort($emails, fn($a, $b) => $b['date'] <=> $a['date']);
}

$view = null;
$viewName = basename((string)get('file', ''));
if ($viewName !== '') {
    foreach ($emails as $m) {
        if ($m['name'] === $viewName) { $m['path'] = $viewName; $view = $m; break; }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-envelope-open-text me-2 text-primary"></i>Boîte email
        <span class="text-muted fs-6">(<?= count($emails) ?> archivé(s))</span></h4>
    <div class="d-flex gap-2">
        <a href="activities.php" class="btn btn-outline-secondary"><i class="fa-solid fa-clock-rotate-left me-1"></i>Journal</a>
        <?php if ($emails): ?>
            <form method="post" action="" onsubmit="return confirm('Supprimer tous les emails archivés ?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="purge">
                <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Tout vider</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($mailTransport === 'log'): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info me-1"></i>
        <strong>Mode démo :</strong> transport <code>log</code> — les emails ne sont pas réellement envoyés,
        ils sont archivés ci-dessous (consultables sans SMTP). Pour un envoi réel, configurez
        <code>MAIL_TRANSPORT=smtp</code> dans le fichier <code>.env</code>.
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-paper-plane me-1"></i>
        <strong>Envoi réel :</strong> transport <code><?= e($mailTransport) ?></code>
        <?php if ($mailTransport === 'smtp'): ?>
            — serveur <code><?= e(MAIL_HOST . ':' . MAIL_PORT) ?></code>
            (<?= e(MAIL_ENCRYPTION === 'none' ? 'sans chiffrement' : MAIL_ENCRYPTION) ?>)
        <?php endif; ?>
        — expéditeur <code><?= e(MAIL_FROM) ?></code>.
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-inbox me-2"></i>File d'attente des notifications</span>
        <div class="d-flex gap-2">
            <?php if ($restants > 0): ?>
                <form method="post" action="" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="traiter_file">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-play me-1"></i>Traiter la file (<?= $restants ?>)
                    </button>
                </form>
            <?php else: ?>
                <form method="post" action="" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="traiter_file">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-rotate me-1"></i>Traiter la file
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 mb-3 small">
            <span><span class="badge bg-warning text-dark"><?= $stats['en_attente'] ?></span> en attente</span>
            <span><span class="badge bg-info text-dark"><?= $stats['en_cours'] ?></span> en cours</span>
            <span><span class="badge bg-success"><?= $stats['envoye'] ?></span> envoyés</span>
            <span><span class="badge bg-danger"><?= $stats['echec'] ?></span> échecs</span>
        </div>

        <form method="post" action="" class="row g-2 align-items-end mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test">
            <div class="col-md-5">
                <label class="form-label small mb-1">Envoyer un email de test à</label>
                <input type="email" name="email" class="form-control form-control-sm"
                       placeholder="vous@exemple.com" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-paper-plane me-1"></i>Tester l'envoi
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Destinataire</th>
                        <th>Objet</th>
                        <th>Catégorie</th>
                        <th>Statut</th>
                        <th>Essais</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($file)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Aucun email dans la file.</td></tr>
                <?php else: foreach ($file as $ligne): ?>
                    <tr>
                        <td class="text-nowrap small"><?= date('d/m/Y H:i', strtotime((string)$ligne['cree_le'])) ?></td>
                        <td class="small"><?= e($ligne['destinataire']) ?></td>
                        <td class="small"><?= e($ligne['sujet']) ?></td>
                        <td class="small"><span class="badge bg-light text-dark border"><?= e($ligne['categorie']) ?></span></td>
                        <td><?= email_statut_badge((string)$ligne['statut']) ?></td>
                        <td class="small text-muted"><?= (int)$ligne['tentatives'] ?></td>
                        <td class="text-end text-nowrap">
                            <?php if (!empty($ligne['derniere_erreur'])): ?>
                                <span class="text-danger small me-2" title="<?= e($ligne['derniere_erreur']) ?>">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                            <?php endif; ?>
                            <?php if ($ligne['statut'] === 'echec'): ?>
                                <form method="post" action="" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="relancer">
                                    <input type="hidden" name="id" value="<?= (int)$ligne['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Relancer">
                                        <i class="fa-solid fa-rotate-right"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">
            La file est traitée automatiquement par <code>php bin/envoyer_emails.php</code>
            (Planificateur de tâches Windows, toutes les 2 à 5 minutes) ou manuellement ci-dessus.
        </p>
    </div>
</div>

<?php if ($view): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-paper-plane me-2"></i><?= e($view['subject']) ?></span>
        <a href="emails.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Retour</a>
    </div>
    <div class="card-body">
        <table class="table table-sm mb-3">
            <tr><th style="width:140px">À</th><td><?= e($view['to']) ?></td></tr>
            <tr><th>Date</th><td><?= date('d/m/Y H:i:s', (int)$view['date']) ?></td></tr>
            <tr><th>Statut</th><td>
                <?php if ($view['status'] === 'sent'): ?>
                    <span class="badge bg-success">Envoyé</span>
                <?php elseif ($view['status'] === 'echec'): ?>
                    <span class="badge bg-danger">Échec</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Local (mode démo)</span>
                <?php endif; ?>
            </td></tr>
        </table>
        <pre class="bg-light border rounded p-3 mb-3" style="white-space:pre-wrap;"><?= e($view['body']) ?></pre>
        <form method="post" action="emails.php" onsubmit="return confirm('Supprimer cet email ?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_one">
            <input type="hidden" name="file" value="<?= e($view['name']) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash me-1"></i>Supprimer</button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header"><i class="fa-solid fa-box-archive me-2"></i>Emails archivés
        <span class="text-muted fs-6">(<?= count($emails) ?>)</span></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Destinataire</th>
                        <th>Objet</th>
                        <th>Statut</th>
                        <th>Taille</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun email archivé.</td></tr>
                    <?php else: foreach ($emails as $m): ?>
                        <tr>
                            <td class="text-nowrap small"><?= date('d/m/Y H:i', (int)$m['date']) ?></td>
                            <td><?= e($m['to']) ?></td>
                            <td><?= e($m['subject']) ?></td>
                            <td>
                                <?php if ($m['status'] === 'sent'): ?>
                                    <span class="badge bg-success">Envoyé</span>
                                <?php elseif ($m['status'] === 'echec'): ?>
                                    <span class="badge bg-danger">Échec</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Local</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= number_format($m['size'] / 1024, 1) ?> Ko</td>
                            <td class="text-end text-nowrap">
                                <a href="?file=<?= e($m['name']) ?>" class="btn btn-sm btn-outline-primary"
                                   title="Voir"><i class="fa-solid fa-eye"></i></a>
                                <form method="post" action="" class="d-inline"
                                      onsubmit="return confirm('Supprimer cet email ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_one">
                                    <input type="hidden" name="file" value="<?= e($m['name']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
