<?php
/**
 * Installeur web My_School
 *
 * Crée la base PostgreSQL, charge le schéma (config/init.sql) puis les
 * corrections (config/migrations/corrections.sql), et génère le fichier .env.
 *
 * Utile pour une installation manuelle sur Laragon/XAMPP ou sur un poste où
 * PostgreSQL est déjà installé.
 *
 * Sécurité : l'installeur se verrouille tout seul. Il refuse de s'exécuter
 * si la base est déjà initialisée ou si le fichier config/.installed existe.
 * Pour réinstaller : supprimer la base puis ce fichier.
 */

require_once __DIR__ . '/includes/functions.php';

// ============================================================
// VERROU D'INSTALLATION
// ============================================================

define('INSTALL_LOCK', __DIR__ . '/config/.installed');
define('SCHEMA_SQL', __DIR__ . '/config/init.sql');
define('MIGRATIONS_SQL', __DIR__ . '/config/migrations/corrections.sql');
define('ENV_FILE', __DIR__ . '/.env');

// ============================================================
// DIAGNOSTIC DE L'ENVIRONNEMENT
// ============================================================

/**
 * Contrôle que PHP et ses extensions peuvent faire tourner l'application.
 * @return array [ ['label' => ..., 'ok' => bool, 'detail' => ...], ... ]
 */
function environment_checks(): array
{
    return [
        [
            'label'  => 'PHP 8.0 ou supérieur',
            'ok'     => version_compare(PHP_VERSION, '8.0.0', '>='),
            'detail' => 'Version détectée : ' . PHP_VERSION,
        ],
        [
            'label'  => 'Extension pdo_pgsql',
            'ok'     => extension_loaded('pdo_pgsql'),
            'detail' => extension_loaded('pdo_pgsql')
                ? 'Extension chargée'
                : 'Activez pdo_pgsql dans votre php.ini puis redémarrez Apache',
        ],
        [
            'label'  => 'Extension pgsql',
            'ok'     => extension_loaded('pgsql'),
            'detail' => extension_loaded('pgsql')
                ? 'Extension chargée'
                : 'Activez pgsql dans votre php.ini puis redémarrez Apache',
        ],
        [
            'label'  => 'Extension gd',
            'ok'     => extension_loaded('gd'),
            'detail' => extension_loaded('gd')
                ? 'Extension chargée (recommandée)'
                : 'Extension gd absente. Certaines fonctionnalités graphiques peuvent ne pas fonctionner.',
        ],
        [
            'label'  => 'Fichier config/init.sql',
            'ok'     => is_readable(SCHEMA_SQL),
            'detail' => is_readable(SCHEMA_SQL)
                ? 'Schéma lisible'
                : 'Fichier introuvable ou non lisible',
        ],
        [
            'label'  => 'Dossier config/ inscriptible',
            'ok'     => is_writable(__DIR__ . '/config'),
            'detail' => is_writable(__DIR__ . '/config')
                ? 'Écriture autorisée (marqueur d\'installation)'
                : ' chmod 755 sur le dossier config/',
        ],
        [
            'label'  => 'Dossier assets/uploads/ inscriptible',
            'ok'     => is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR),
            'detail' => (is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR))
                ? 'Téléversements possibles'
                : 'Créez le dossier assets/uploads/ avec les droits d\'écriture',
        ],
    ];
}

/**
 * Indique si l'application est déjà installée et donc si l'installeur
 * doit refuser de s'exécuter.
 */
function install_lock_reason(): ?string
{
    if (is_file(INSTALL_LOCK)) {
        return 'Le fichier config/.installed est présent : cette installation a déjà été réalisée.';
    }
    if (db_is_ready()) {
        return 'La base « ' . db_config()['dbname'] . ' » contient déjà le schéma : installation déjà effectuée.';
    }
    return null;
}

// ============================================================
// ÉTAPES D'INSTALLATION
// ============================================================

/**
 * Connexion à une base PostgreSQL avec un DSN explicite.
 */
function pdo_connect(string $host, int $port, string $dbname, string $user, string $password): PDO
{
    return new PDO(
        "pgsql:host={$host};port={$port};dbname={$dbname}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
}

/**
 * Exécute un fichier SQL complet (plusieurs instructions) dans une transaction.
 * PostgreSQL rend le DDL transactionnel : soit tout passe, soit rien.
 */
function run_sql_file(PDO $pdo, string $file): void
{
    if (!is_readable($file)) {
        throw new RuntimeException('Fichier SQL illisible : ' . basename($file));
    }
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Fichier SQL vide : ' . basename($file));
    }
    $pdo->exec($sql);
}

/**
 * Crée la base si elle n'existe pas (connexion via la base de maintenance).
 * @return bool true si la base vient d'être créée
 */
function ensure_database(string $host, int $port, string $dbname, string $user, string $password): bool
{
    $maint = pdo_connect($host, $port, 'postgres', $user, $password);
    $stmt = $maint->prepare('SELECT 1 FROM pg_database WHERE datname = :n');
    $stmt->execute(['n' => $dbname]);

    if ($stmt->fetchColumn()) return false;

    // CREATE DATABASE n'accepte pas les identifiants liés : on identifie
    // la base avec un guillemet en échappant les guillemets doubles.
    $safe = str_replace('"', '""', $dbname);
    $maint->exec('CREATE DATABASE "' . $safe . '" ENCODING \'UTF8\'');
    return true;
}

/**
 * Écrit (ou met à jour) le fichier .env à la racine.
 */
function write_env_file(array $values): bool
{
    $lines = [
        '# Configuration PostgreSQL (My_School)',
        '# Généré automatiquement par install.php',
        '',
    ];
    foreach ($values as $key => $value) {
        $lines[] = $key . '=' . $value;
    }
    $lines[] = '';

    $content = implode("\n", $lines);
    // Un secret contenant des espaces ou des quotes doit être quoté
    $content = preg_replace_callback('/^([A-Z0-9_]+)=(.*)$/m', function ($m) {
        $val = $m[2];
        if ($val === '') return $m[0];
        if (preg_match('/^[A-Za-z0-9_\/.:\-]+$/', $val)) return $m[0];
        return $m[1] . '="' . str_replace('"', '\\"', $val) . '"';
    }, $content);

    return file_put_contents(ENV_FILE, $content) !== false;
}

/**
 * Génère une clé de chiffrement aléatoire pour l'application
 * (utilisée par encrypt_data/decrypt_data).
 */
function generate_encryption_key(): string
{
    return bin2hex(random_bytes(32));
}

// ============================================================
// TRAITEMENT DU FORMULAIRE
// ============================================================

$errors = [];
$success = false;
$dbCreated = false;
$done = false;
$adminEmail = 'admin@myschool.edu';
$lockReason = install_lock_reason();

$cfg = db_config();
$form = [
    'host'     => $cfg['host'],
    'port'     => $cfg['port'],
    'dbname'   => $cfg['dbname'],
    'user'     => $cfg['user'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lockReason === null) {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expirée, veuillez recharger la page.';
    } else {
        $form['host']   = trim(post('db_host', $form['host']));
        $form['port']   = trim(post('db_port', $form['port']));
        $form['dbname'] = trim(post('db_name', $form['dbname']));
        $form['user']   = trim(post('db_user', $form['user']));
        $form['pass']   = (string)post('db_password', '');

        $adminEmail    = trim(post('admin_email', $adminEmail));
        $adminPassword = (string)post('admin_password', '');
        $adminConfirm  = (string)post('admin_password_confirm', '');
        $etablissement  = trim(post('etablissement', 'Mon École'));
        $annee         = trim(post('annee_scolaire', '2025-2026'));

        // ---- Validation des paramètres ----
        if ($form['host'] === '') $errors[] = 'L\'hôte PostgreSQL est obligatoire.';
        if (!preg_match('/^\d+$/', (string)$form['port']) || (int)$form['port'] < 1 || (int)$form['port'] > 65535) {
            $errors[] = 'Le port doit être un nombre entre 1 et 65535.';
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', (string)$form['dbname'])) {
            $errors[] = 'Le nom de base ne peut contenir que des lettres, chiffres, _ et -.';
        }
        if ($form['user'] === '') $errors[] = 'L\'utilisateur PostgreSQL est obligatoire.';
        if (!is_valid_email($adminEmail)) $errors[] = 'L\'email administrateur est invalide.';
        if (strlen($adminPassword) < 8) $errors[] = 'Le mot de passe administrateur doit contenir au moins 8 caractères.';
        if ($adminPassword !== $adminConfirm) $errors[] = 'Les deux mots de passe ne correspondent pas.';
        if ($annee !== '' && !preg_match('/^\d{4}\s*-\s*\d{4}$/', $annee)) {
            $errors[] = 'L\'année scolaire doit être au format AAAA-AAAA (ex. 2025-2026).';
        }

        if (!$errors) {
            try {
                $host = $form['host'];
                $port = (int)$form['port'];

                // 1. Créer la base si nécessaire
                $dbCreated = ensure_database($host, $port, $form['dbname'], $form['user'], $form['pass']);

                // 2. Charger le schéma
                $pdo = pdo_connect($host, $port, $form['dbname'], $form['user'], $form['pass']);
                $pdo->exec("SET TIME ZONE 'Africa/Kinshasa'");
                $pdo->beginTransaction();
                try {
                    run_sql_file($pdo, SCHEMA_SQL);
                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }

                // 3. Appliquer les corrections de bugs (hors transaction :
                //    CREATE OR REPLACE FUNCTION est idempotent de toute façon)
                if (is_readable(MIGRATIONS_SQL)) {
                    run_sql_file($pdo, MIGRATIONS_SQL);
                }

                // 4. Personnaliser l'administrateur et l'établissement.
                //    Le schéma livre un compte admin@myschool.edu / admin123 :
                //    on le renomme et on lui met le mot de passe choisi, plutôt
                //    que de laisser un mot de passe par défaut en circulation.
                $hash = password_hash($adminPassword, PASSWORD_DEFAULT);

                $dejaPris = $pdo->prepare('SELECT 1 FROM utilisateurs WHERE email = :e');
                $dejaPris->execute(['e' => $adminEmail]);
                $emailLibre = !$dejaPris->fetchColumn();

                $admin = $pdo->query(
                    "SELECT id FROM utilisateurs WHERE role = 'admin' ORDER BY id LIMIT 1"
                )->fetch();

                if ($admin && $emailLibre) {
                    // Renommer le compte admin livré par le schéma
                    $pdo->prepare(
                        'UPDATE utilisateurs
                         SET email = :e, password_hash = :h, actif = TRUE,
                             deux_facteurs = FALSE, compte_verrouille = FALSE, tentatives_connexion = 0
                         WHERE id = :id'
                    )->execute(['e' => $adminEmail, 'h' => $hash, 'id' => $admin['id']]);
                } elseif ($admin) {
                    // L'email choisi est déjà pris : on ne touche qu'au mot de passe
                    $pdo->prepare(
                        'UPDATE utilisateurs
                         SET password_hash = :h, actif = TRUE,
                             deux_facteurs = FALSE, compte_verrouille = FALSE, tentatives_connexion = 0
                         WHERE id = :id'
                    )->execute(['h' => $hash, 'id' => $admin['id']]);
                    $adminEmail = (string) $pdo->query(
                        "SELECT email FROM utilisateurs WHERE id = " . (int) $admin['id']
                    )->fetchColumn();
                } else {
                    // Aucun admin en base : on en crée un
                    $pdo->prepare(
                        "INSERT INTO utilisateurs (nom, prenom, email, password_hash, role)
                         VALUES ('Administrateur', 'Système', :e, :h, 'admin')"
                    )->execute(['e' => $adminEmail, 'h' => $hash]);
                }

                $setParam = $pdo->prepare(
                    'INSERT INTO parametres_systeme (cle, valeur) VALUES (:k, :v)
                     ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur'
                );
                $setParam->execute(['k' => 'nom_etablissement', 'v' => $etablissement !== '' ? $etablissement : 'Mon École']);
                $setParam->execute(['k' => 'annee_scolaire', 'v' => $annee !== '' ? $annee : '2025-2026']);

                // 5. Écrire le .env pour que l'application utilise cette base
                write_env_file([
                    'DB_HOST'        => $host,
                    'DB_PORT'        => (string)$port,
                    'DB_NAME'        => $form['dbname'],
                    'DB_USER'        => $form['user'],
                    'DB_PASSWORD'    => $form['pass'],
                    'ENCRYPTION_KEY' => generate_encryption_key(),
                ]);

                // 6. Verrouiller l'installeur
                @file_put_contents(INSTALL_LOCK, 'Installation initiale : ' . date('c') . PHP_EOL);

                $done = true;
                $lockReason = install_lock_reason();
            } catch (Throwable $e) {
                $errors[] = 'Installation échouée : ' . $e->getMessage();
            }
        }
    }
}

$checks = environment_checks();
$canInstall = true;
foreach ($checks as $c) {
    if (!$c['ok']) $canInstall = false;
}

// ============================================================
// AFFICHAGE
// ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - My_School</title>
    <!-- Google Fonts : Plus Jakarta Sans (local) -->
    <link href="assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <!-- Font Awesome (local) -->
    <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-card { max-width: 660px; }
        .step-list { list-style: none; padding: 0; margin: 0; }
        .step-list li {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 9px 0; font-size: 13.5px;
            border-bottom: 1px solid var(--slate-100);
        }
        .step-list li:last-child { border-bottom: none; }
        .step-list .ico { width: 20px; flex: 0 0 20px; text-align: center; }
        .step-list .ok   { color: #047857; }
        .step-list .ko   { color: #B91C1C; }
        .step-list .detail { display: block; color: var(--text-sub); font-size: 12px; margin-top: 2px; }
        code.k { background: var(--slate-100); padding: 2px 6px; border-radius: 5px; font-size: 12.5px; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo-circle"><i class="fa-solid fa-graduation-cap"></i></div>
            <h1>My_School</h1>
            <p>Installation du système</p>
        </div>

        <?php if ($done): ?>
            <!-- ================= SUCCÈS ================= -->
            <div class="alert alert-success" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i>
                Installation terminée<?= $dbCreated ? ' (base créée)' : '' ?>.
            </div>

            <div class="mb-3">
                <label class="form-label">Compte administrateur</label>
                <table class="table table-sm">
                    <tbody>
                        <tr><td>Email</td><td><code class="k"><?= e($adminEmail) ?></code></td></tr>
                        <tr><td>Mot de passe</td><td><code class="k"><?= e($_POST['admin_password'] ?? '') ?></code></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="alert" style="background:#FEF3C7;color:#B45309;border-color:#FDE68A">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                Notez ce mot de passe : il n'est plus affiché ensuite.
            </div>

            <div class="alert" style="background:#EFF6FF;color:#1D4ED8;border-color:#BFDBFE">
                <i class="fa-solid fa-shield-halved me-2"></i>
                L'installeur est désormais verrouillé. Vous pouvez supprimer
                <code class="k">install.php</code> du serveur.
            </div>

            <a href="<?= e(BASE_URL) ?>" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Aller à l'application
            </a>

        <?php elseif ($lockReason !== null): ?>
            <!-- ================= DÉJÀ INSTALLÉ ================= -->
            <div class="alert" style="background:#EFF6FF;color:#1D4ED8;border-color:#BFDBFE">
                <i class="fa-solid fa-circle-info me-2"></i><?= e($lockReason) ?>
            </div>
            <p class="text-muted small">
                Pour repartir d'une installation vierge : supprimez d'abord la base
                <code class="k"><?= e(db_config()['dbname']) ?></code>, puis le fichier
                <code class="k">config/.installed</code>, et rechargez cette page.
            </p>
            <a href="<?= e(BASE_URL) ?>" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-arrow-right me-2"></i>Retour à l'application
            </a>

        <?php else: ?>
            <!-- ================= FORMULAIRE ================= -->
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <h2 class="form-label mb-2"><i class="fa-solid fa-list-check me-2"></i>Étape 1 — Vérification de l'environnement</h2>
            <ul class="step-list mb-4">
                <?php foreach ($checks as $c): ?>
                    <li>
                        <span class="ico <?= $c['ok'] ? 'ok' : 'ko' ?>">
                            <i class="fa-solid <?= $c['ok'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                        </span>
                        <span>
                            <?= e($c['label']) ?>
                            <span class="detail"><?= e($c['detail']) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (!$canInstall): ?>
                <div class="alert alert-danger">
                    Corrigez les points en rouge ci-dessus avant de poursuivre.
                </div>
            <?php else: ?>
                <h2 class="form-label mb-2"><i class="fa-solid fa-database me-2"></i>Étape 2 — Base de données</h2>
                <form method="post" action="" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Hôte PostgreSQL</label>
                        <input type="text" name="db_host" class="form-control" value="<?= e($form['host']) ?>" required>
                        <span class="form-text">Par défaut : <code class="k">127.0.0.1</code> (PostgreSQL local)</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" name="db_port" class="form-control" value="<?= e($form['port']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nom de la base</label>
                        <input type="text" name="db_name" class="form-control" value="<?= e($form['dbname']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Utilisateur</label>
                        <input type="text" name="db_user" class="form-control" value="<?= e($form['user']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mot de passe PostgreSQL</label>
                        <input type="password" name="db_password" class="form-control" autocomplete="new-password">
                        <span class="form-text">Laisser vide si l'utilisateur n'a pas de mot de passe.</span>
                    </div>

                    <div class="divider"></div>

                    <h2 class="form-label mb-2"><i class="fa-solid fa-user-shield me-2"></i>Étape 3 — Établissement et administrateur</h2>

                    <div class="mb-3">
                        <label class="form-label">Nom de l'établissement</label>
                        <input type="text" name="etablissement" class="form-control" value="Mon École">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Année scolaire</label>
                        <input type="text" name="annee_scolaire" class="form-control" value="2025-2026">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email administrateur</label>
                        <input type="email" name="admin_email" class="form-control" value="<?= e($adminEmail) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mot de passe administrateur</label>
                        <input type="password" name="admin_password" class="form-control" autocomplete="new-password" required>
                        <span class="form-text">8 caractères minimum.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmer le mot de passe</label>
                        <input type="password" name="admin_password_confirm" class="form-control" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fa-solid fa-wrench me-2"></i>Installer My_School
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
