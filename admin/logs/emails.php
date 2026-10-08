<?php
/**
 * Boîte email de démo (Admin)
 * Liste les emails générés par le système dans storage/mail/ :
 *  - envoyés réellement (MAIL_ENABLED=true) : statut "Envoyé"
 *  - journalisés faute de SMTP (MAIL_ENABLED=false) : statut "Local"
 * Permet de retrouver les codes 2FA et liens de réinitialisation.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('logs.view');

$page_title = 'Boîte email';
$active_menu = 'logs_emails';

$dir = ROOT_PATH . 'storage/mail';

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
        }
    }
    $redirect = 'emails.php' . (isset($_GET['file']) ? '?file=' . urlencode(basename((string)$_GET['file'])) : '');
    header('Location: ' . $redirect);
    exit;
}

// ---- Lecture des emails ----
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

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-envelope-open-text me-2 text-primary"></i>Boîte email
        <span class="text-muted fs-6">(<?= count($emails) ?>)</span></h4>
    <div class="d-flex gap-2">
        <a href="activities.php" class="btn btn-outline-secondary"><i class="fa-solid fa-clock-rotate-left me-1"></i>Journal</a>
        <?php if ($emails): ?>
            <form method="post" action="" onsubmit="return confirm('Supprimer tous les emails ?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="purge">
                <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Tout vider</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!MAIL_ENABLED): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info me-1"></i>
        <strong>Mode démo :</strong> <code>MAIL_ENABLED=false</code> — les emails ne sont pas réellement envoyés,
        ils sont journalisés ici. Définissez <code>MAIL_ENABLED=true</code> dans le <code>.env</code> pour activer
        l'envoi réel via <code>mail()</code>.
    </div>
<?php endif; ?>

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
                <?php else: ?>
                    <span class="badge bg-secondary">Local (pas de SMTP)</span>
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
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun email enregistré.</td></tr>
                    <?php else: foreach ($emails as $m): ?>
                        <tr>
                            <td class="text-nowrap small"><?= date('d/m/Y H:i', (int)$m['date']) ?></td>
                            <td><?= e($m['to']) ?></td>
                            <td><?= e($m['subject']) ?></td>
                            <td>
                                <?php if ($m['status'] === 'sent'): ?>
                                    <span class="badge bg-success">Envoyé</span>
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
