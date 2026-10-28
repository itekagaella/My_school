<?php
/**
 * Transport email.
 *
 * Trois modes (voir MAIL_TRANSPORT dans config/config.php) :
 *   - smtp : client SMTP minimal, sans dépendance externe. Compatible avec
 *            Brevo, Gmail, Mailpit, les serveurs d'hébergeur, etc.
 *            Gère SSL direct (465) et STARTTLS (587), AUTH LOGIN / PLAIN.
 *   - mail : fonction mail() de PHP (déconseillée sous Windows).
 *   - log  : aucun envoi, géré par la file d'attente (archivage .eml).
 *
 * Le client n'est utilisé que par le worker et par les emails immédiats
 * (code 2FA, lien de réinitialisation).
 */

if (!function_exists('mailer_charger_env')) {
    function mailer_charger_env(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $envFile = null;
        if (defined('ROOT_PATH')) {
            $envFile = rtrim(ROOT_PATH, '/\\') . '/.env';
        }
        if (!$envFile || !is_file($envFile)) {
            $envFile = __DIR__ . '/../.env';
        }
        if (!is_file($envFile)) {
            return;
        }

        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if (strlen($val) >= 2) {
                $first = $val[0];
                $last = $val[strlen($val) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }
            if ($key === '') {
                continue;
            }
            if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
                @putenv($key . '=' . $val);
                $_ENV[$key] = $val;
            }
        }
    }
}

class SmtpMailer
{
    private $socket = null;
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private int $timeout;
    private string $from;
    private string $fromName;
    private string $lastError = '';

    public function __construct(array $config = [])
    {
        mailer_charger_env();
        $get = function($key, $const, $default) use ($config) {
            if (isset($config[$key]) && $config[$key] !== '') return $config[$key];
            if (defined($const) && constant($const) !== '') return constant($const);
            $v = getenv($key);
            if ($v !== false && $v !== '') return $v;
            if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
            // try lowercase
            $v2 = getenv(strtolower($key));
            if ($v2 !== false && $v2 !== '') return $v2;
            return $default;
        };

        $this->host       = (string)$get('MAIL_HOST', 'MAIL_HOST', '127.0.0.1');
        $this->port       = (int)$get('MAIL_PORT', 'MAIL_PORT', 1025);
        $this->encryption = strtolower((string)$get('MAIL_ENCRYPTION', 'MAIL_ENCRYPTION', 'none'));
        if (!in_array($this->encryption, ['none', 'tls', 'ssl'], true)) {
            $this->encryption = 'none';
        }
        $this->username  = (string)$get('MAIL_USERNAME', 'MAIL_USERNAME', '');
        $this->password  = (string)$get('MAIL_PASSWORD', 'MAIL_PASSWORD', '');
        $this->timeout   = (int)$get('MAIL_TIMEOUT', 'MAIL_TIMEOUT', 10);
        $this->from      = (string)$get('MAIL_FROM', 'MAIL_FROM', 'no-reply@myschool.edu');
        $this->fromName  = (string)$get('MAIL_FROM_NAME', 'MAIL_FROM_NAME', 'My_School');
    }

    public function dernierErreur(): string
    {
        return $this->lastError;
    }

    /**
     * Envoie un email HTML avec une alternative texte.
     */
    public function envoyer(string $to, string $subject, string $html, string $text = '', ?string $replyTo = null): bool
    {
        $this->lastError = '';

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Adresse destinataire invalide : ' . $to;
            return false;
        }
        if (!filter_var($this->from, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Adresse expéditeur invalide : ' . $this->from;
            return false;
        }

        try {
            $this->connecter();
            $this->envoyerMessage($to, $subject, $html, $text, $replyTo);
            try {
                $this->commande('QUIT', [221]);
            } catch (Throwable $e) {
                // Le message est déjà accepté, on ignore une erreur de fermeture.
            }
            $this->fermer();
            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->fermer();
            return false;
        }
    }

    private function connecter(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $scheme . '://' . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new RuntimeException(
                'Connexion SMTP impossible (' . $this->host . ':' . $this->port . ') : ' . ($errstr ?: 'erreur ' . $errno)
            );
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->timeout);
        $this->lire([220]);

        $hostname = gethostname() ?: 'localhost';
        $ehlo = $this->commande('EHLO ' . $hostname, [250]);

        if ($this->encryption === 'tls') {
            $this->commande('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Impossible d\'activer STARTTLS (certificat ou serveur incompatible).');
            }
            $ehlo = $this->commande('EHLO ' . $hostname, [250]);
        }

        $this->authentifier($ehlo);
    }

    private function authentifier(string $ehlo): void
    {
        @file_put_contents(ROOT_PATH . 'storage/mail/smtp_debug.log', '[' . date('Y-m-d H:i:s') . '] Auth check - user: ' . ($this->username === '' ? 'EMPTY' : 'SET') . ' pass: ' . ($this->password === '' ? 'EMPTY' : 'SET') . ' host:' . $this->host . ':' . $this->port . PHP_EOL, FILE_APPEND);
        error_log('[SMTP] Auth check - user: ' . ($this->username === '' ? 'EMPTY' : 'SET') . ' pass: ' . ($this->password === '' ? 'EMPTY' : 'SET'));
        if ($this->username === '' || $this->password === '') {
            error_log('[SMTP] Skipping auth - credentials empty');
            @file_put_contents(ROOT_PATH . 'storage/mail/smtp_debug.log', '[' . date('Y-m-d H:i:s') . '] Skipping auth - credentials empty' . PHP_EOL, FILE_APPEND);
            return;
        }

        $authLine = '';
        foreach (preg_split('/\r?\n/', $ehlo) as $line) {
            if (stripos($line, 'AUTH') !== false) {
                $authLine = strtoupper($line);
                error_log('[SMTP] AUTH line: ' . $authLine);
                @file_put_contents(ROOT_PATH . 'storage/mail/smtp_debug.log', '[' . date('Y-m-d H:i:s') . '] AUTH line: ' . $authLine . PHP_EOL, FILE_APPEND);
            }
        }

        // Certains serveurs (Outlook…) n'annoncent que PLAIN.
        if ($authLine !== '' && strpos($authLine, 'LOGIN') === false && strpos($authLine, 'PLAIN') !== false) {
            error_log('[SMTP] Using AUTH PLAIN');
            $this->commande('AUTH PLAIN ' . base64_encode("\0" . $this->username . "\0" . $this->password), [235]);
            return;
        }

        error_log('[SMTP] Using AUTH LOGIN');
        $this->commande('AUTH LOGIN', [334]);
        $this->commande(base64_encode($this->username), [334]);
        $this->commande(base64_encode($this->password), [235]);
        error_log('[SMTP] Auth completed');
    }

    private function envoyerMessage(string $to, string $subject, string $html, string $text, ?string $replyTo): void
    {
        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags(str_replace(
                ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'],
                "\n",
                $html
            )), ENT_QUOTES, 'UTF-8'));
        }

        $boundary = '=_MySchool_' . bin2hex(random_bytes(12));
        $domain = substr((string)strrchr($this->from, '@'), 1) ?: 'myschool.local';

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $this->encoderNom($this->fromName) . ' <' . $this->from . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>';
        $headers[] = 'MIME-Version: 1.0';
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body  = '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text), 76, "\r\n");

        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html), 76, "\r\n");

        $body .= '--' . $boundary . "--\r\n";

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        // Conformité SMTP : une ligne commençant par un point doit être doublée.
        $data = preg_replace('/^\./m', '..', $data);

        $this->commande('MAIL FROM:<' . $this->from . '>', [250]);
        $this->commande('RCPT TO:<' . $to . '>', [250, 251]);
        $this->commande('DATA', [354]);

        $written = @fwrite($this->socket, $data . "\r\n.\r\n");
        if ($written === false) {
            throw new RuntimeException('Envoi du message interrompu.');
        }

        $this->lire([250]);
    }

    private function commande(string $commande, array $codesAttendus): string
    {
        if ($this->socket === null) {
            throw new RuntimeException('Connexion SMTP non ouverte.');
        }

        $payload = $commande . "\r\n";
        $written = @fwrite($this->socket, $payload);
        if ($written === false || $written < strlen($payload)) {
            throw new RuntimeException('Écriture SMTP interrompue.');
        }

        return $this->lire($codesAttendus);
    }

    private function lire(array $codesAttendus): string
    {
        if ($this->socket === null) {
            throw new RuntimeException('Connexion SMTP non ouverte.');
        }

        $reponse = '';
        while (true) {
            $ligne = @fgets($this->socket, 515);
            if ($ligne === false) {
                $meta = stream_get_meta_data($this->socket);
                throw new RuntimeException(
                    !empty($meta['timed_out'])
                        ? 'Délai SMTP dépassé (' . $this->timeout . ' s).'
                        : 'Connexion SMTP fermée par le serveur.'
                );
            }

            $reponse .= $ligne;

            if (preg_match('/^(\d{3}) /', $ligne, $m)) {
                $code = (int)$m[1];
                if (!in_array($code, $codesAttendus, true)) {
                    throw new RuntimeException('Réponse SMTP ' . $code . ' : ' . trim($reponse));
                }
                return $reponse;
            }
        }
    }

    private function encoderNom(string $nom): string
    {
        $nom = trim(str_replace(["\r", "\n"], '', $nom));
        if ($nom === '') {
            return '';
        }
        if (preg_match('/[^\x20-\x7E]/', $nom)) {
            return '=?UTF-8?B?' . base64_encode($nom) . '?=';
        }
        return '"' . addcslashes($nom, '"\\') . '"';
    }

    private function fermer(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}

/**
 * Archive un email au format .eml dans storage/mail/ (boîte de démo).
 *
 * @param ?string $statut sent | local | echec (déduit de $delivered si null)
 */
if (!function_exists('email_store')) {
function email_store(string $to, string $subject, string $body, bool $delivered, ?string $statut = null): void
{

    $dir = ROOT_PATH . 'storage/mail';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }

    $statut = $statut ?? ($delivered ? 'sent' : 'local');
    $statut = preg_replace('/[^a-z]/', '', strtolower($statut));
    if ($statut === '') {
        $statut = 'local';
    }

    $raw  = 'Date: ' . date('r') . "\r\n";
    $raw .= 'To: ' . $to . "\r\n";
    $raw .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
    $raw .= 'Subject: ' . str_replace(["\r", "\n"], '', $subject) . "\r\n";
    $raw .= 'X-MySchool-Status: ' . $statut . "\r\n";
    $raw .= "MIME-Version: 1.0\r\n";
    $raw .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $raw .= "\r\n" . $body . "\r\n";

    $file = $dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.eml';
    @file_put_contents($file, $raw);
}
}

/**
 * Envoie immédiatement un email via le transport configuré.
 * En mode « log », renvoie false : c'est la file d'attente qui archive.
 */
if (!function_exists('email_transporter')) {
function email_transporter(string $to, string $subject, string $html, string $text = ''): bool
{
    mailer_charger_env();
    if (defined('MAIL_TRANSPORT') ? MAIL_TRANSPORT === 'smtp' : (getenv('MAIL_TRANSPORT') === 'smtp')) {
        $mailer = new SmtpMailer();
        $ok = $mailer->envoyer($to, $subject, $html, $text);
        if (!$ok) {
            error_log('[email] Échec envoi à ' . $to . ' : ' . $mailer->dernierErreur());
        }
        return $ok;
    }

    if (MAIL_TRANSPORT === 'mail') {
        $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n"
                 . 'Reply-To: ' . MAIL_FROM . "\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n";
        $sujet = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return (bool)@mail($to, $sujet, $html, $headers);
    }

    return false;
}
}
