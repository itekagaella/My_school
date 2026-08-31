<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('profs.create');

$page_title = 'Nouveau professeur';
$active_menu = 'profs';

$error = '';
$d = ['nom'=>'','prenom'=>'','specialite'=>'','tel'=>'','email'=>'','adresse'=>'','email_compte'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $d['nom'] = clean_input($_POST['nom'] ?? '');
        $d['prenom'] = clean_input($_POST['prenom'] ?? '');
        $d['specialite'] = clean_input($_POST['specialite'] ?? '');
        $d['tel'] = clean_input($_POST['tel'] ?? '');
        $d['email'] = clean_input($_POST['email'] ?? '');
        $d['adresse'] = clean_input($_POST['adresse'] ?? '');
        $d['email_compte'] = strtolower(trim($_POST['email_compte'] ?? ''));
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if (empty($d['nom']) || empty($d['prenom']) || empty($d['specialite'])) {
            $error = 'Nom, prénom et spécialité sont obligatoires.';
        } elseif ($d['email'] && !is_valid_email($d['email'])) {
            $error = 'Email invalide.';
        } elseif ($d['email_compte'] && !is_valid_email($d['email_compte'])) {
            $error = 'Email du compte invalide.';
        } elseif (strlen($motDePasse) < 8) {
            $error = 'Le mot de passe du compte doit contenir au moins 8 caractères.';
        } else {
            if ($d['email']) {
                $exists = prepareQuery('SELECT id FROM profs WHERE email = :e', ['e'=>$d['email']])->fetch();
                if ($exists) $error = 'Un professeur existe déjà avec cet email.';
            }
            if (!$error && $d['email_compte']) {
                $exists = prepareQuery('SELECT id FROM utilisateurs WHERE email = :e', ['e'=>$d['email_compte']])->fetch();
                if ($exists) $error = 'Un compte existe déjà avec cet email.';
            }
            if (!$error) {
                try {
                    $emailCompte = $d['email_compte'] ?: (strtolower($d['prenom']) . '.' . strtolower($d['nom']) . '@prof.local');
                    $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

                    prepareQuery(
                        'INSERT INTO utilisateurs (nom, prenom, email, password_hash, role, actif, created_at)
                         VALUES (:n, :p, :e, :pw, :r, TRUE, NOW())',
                        ['n'=>$d['nom'], 'p'=>$d['prenom'], 'e'=>$emailCompte, 'pw'=>$hash, 'r'=>'prof']
                    );
                    $userId = getDB()->lastInsertId();

                    $matricule = 'PRF-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                    prepareQuery(
                        'INSERT INTO profs (user_id, matricule, nom, prenom, specialite, tel, email, adresse, statut, created_at, updated_at)
                         VALUES (:uid, :mat, :n, :p, :sp, :t, :e, :a, :s, NOW(), NOW())',
                        ['uid'=>$userId, 'mat'=>$matricule, 'n'=>$d['nom'], 'p'=>$d['prenom'], 'sp'=>$d['specialite'], 't'=>$d['tel'], 'e'=>$d['email'], 'a'=>$d['adresse'], 's'=>'actif']
                    );

                    log_activity('profs.create', 'Création du professeur ' . $d['prenom'] . ' ' . $d['nom'] . ' (ID ' . $userId . ')');
                    set_flash('success', 'Professeur créé avec succès.');
                    header('Location: index.php');
                    exit;
                } catch (Exception $ex) {
                    $error = 'Erreur lors de la création : ' . $ex->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Nouveau professeur</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <h6 class="text-primary"><i class="fa-solid fa-circle-user me-1"></i>Informations du professeur</h6>
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
                    <label class="form-label required">Spécialité</label>
                    <input type="text" name="specialite" class="form-control" value="<?= e($d['specialite']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="tel" class="form-control" value="<?= e($d['tel']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email personnel</label>
                    <input type="email" name="email" class="form-control" value="<?= e($d['email']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= e($d['adresse']) ?>">
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
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Créer</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
