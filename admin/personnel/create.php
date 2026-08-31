<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('personnel.create');

$page_title = 'Ajouter un membre du personnel';
$active_menu = 'personnel';

$error = '';
$d = ['nom' => '', 'prenom' => '', 'fonction' => '', 'tel' => '', 'email' => '', 'email_compte' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $d['nom'] = clean_input($_POST['nom'] ?? '');
        $d['prenom'] = clean_input($_POST['prenom'] ?? '');
        $d['fonction'] = clean_input($_POST['fonction'] ?? '');
        $d['tel'] = clean_input($_POST['tel'] ?? '');
        $d['email'] = strtolower(trim($_POST['email'] ?? ''));
        $d['email_compte'] = strtolower(trim($_POST['email_compte'] ?? ''));
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if (empty($d['nom']) || empty($d['prenom']) || empty($d['fonction'])) {
            $error = 'Nom, prénom et fonction sont obligatoires.';
        } elseif ($d['email'] && !is_valid_email($d['email'])) {
            $error = 'Adresse email invalide.';
        } elseif ($d['email_compte'] && !is_valid_email($d['email_compte'])) {
            $error = 'Email du compte invalide.';
        } elseif (strlen($motDePasse) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } else {
            $emailCompte = $d['email_compte'] ?: (strtolower($d['prenom']) . '.' . strtolower($d['nom']) . '@personnel.local');

            $exists = prepareQuery('SELECT id FROM utilisateurs WHERE email = :e', ['e' => $emailCompte])->fetch();
            if ($exists) {
                $error = 'Un compte existe déjà avec cet email.';
            } else {
                if ($d['email']) {
                    $emailPerso = prepareQuery('SELECT id FROM personnel WHERE email = :e', ['e' => $d['email']])->fetch();
                    if ($emailPerso) $error = 'Cette adresse email est déjà utilisée par un autre membre.';
                }
            }

            if (!$error) {
                try {
                    getDB()->beginTransaction();

                    $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

                    $stmtUser = getDB()->prepare(
                        "INSERT INTO utilisateurs (nom, prenom, email, password_hash, role, actif, created_at)
                         VALUES (:n, :p, :e, :pw, 'personnel', TRUE, NOW())"
                    );
                    $stmtUser->execute([':n' => $d['nom'], ':p' => $d['prenom'], ':e' => $emailCompte, ':pw' => $hash]);
                    $userId = (int)getDB()->lastInsertId();

                    $matricule = 'P' . str_pad($userId, 4, '0', STR_PAD_LEFT);

                    prepareQuery(
                        "INSERT INTO personnel (user_id, matricule, nom, prenom, fonction, tel, email, statut, created_at)
                         VALUES (:uid, :mat, :n, :p, :f, :t, :e, 'actif', NOW())",
                        ['uid' => $userId, 'mat' => $matricule, 'n' => $d['nom'], 'p' => $d['prenom'], 'f' => $d['fonction'], 't' => $d['tel'], 'e' => $d['email']]
                    );

                    getDB()->commit();

                    log_activity('personnel.create', 'Ajout du membre ' . $d['prenom'] . ' ' . $d['nom'] . ' (ID ' . $userId . ')');
                    set_flash('success', 'Membre du personnel ajouté avec succès.');
                    header('Location: index.php');
                    exit;
                } catch (Exception $ex) {
                    getDB()->rollBack();
                    $error = 'Erreur lors de l\'ajout : ' . $ex->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Ajouter un membre du personnel</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <h6 class="text-primary"><i class="fa-solid fa-circle-user me-1"></i>Informations personnelles</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label required">Prénom</label>
                    <input type="text" name="prenom" class="form-control" value="<?= e($d['prenom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Nom</label>
                    <input type="text" name="nom" class="form-control" value="<?= e($d['nom']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Fonction</label>
                    <input type="text" name="fonction" class="form-control" value="<?= e($d['fonction']) ?>" required placeholder="Ex: Enseignant, Secrétaire...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="tel" class="form-control" value="<?= e($d['tel']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email personnel</label>
                    <input type="email" name="email" class="form-control" value="<?= e($d['email']) ?>">
                </div>
            </div>

            <h6 class="text-primary"><i class="fa-solid fa-key me-1"></i>Compte de connexion</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Email du compte (optionnel)</label>
                    <input type="email" name="email_compte" class="form-control" value="<?= e($d['email_compte']) ?>" placeholder="Laissez vide pour générer automatiquement">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Mot de passe provisoire</label>
                    <input type="text" name="mot_de_passe" class="form-control" value="<?= e($motDePasse ?? '') ?>" minlength="8" placeholder="Min. 8 caractères" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Ajouter</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
