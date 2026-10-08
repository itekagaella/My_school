<?php
/**
 * Inscription d'un élève avec requêtes préparées + procédure stockée
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('eleves.create');

$page_title = 'Inscrire un élève';
$active_menu = 'eleves';

$error = '';
$d = ['nom'=>'','prenom'=>'','date_naissance'=>'','sexe'=>'M','classe_id'=>'', 'parent_nom'=>'','parent_tel'=>'','parent_email'=>'','adresse'=>'','email_compte'=>''];

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $d['nom'] = clean_input($_POST['nom'] ?? '');
        $d['prenom'] = clean_input($_POST['prenom'] ?? '');
        $d['date_naissance'] = $_POST['date_naissance'] ?? '';
        $d['sexe'] = $_POST['sexe'] ?? 'M';
        $d['classe_id'] = (int)($_POST['classe_id'] ?? 0);
        $d['parent_nom'] = clean_input($_POST['parent_nom'] ?? '');
        $d['parent_tel'] = clean_input($_POST['parent_tel'] ?? '');
        $d['parent_email'] = clean_input($_POST['parent_email'] ?? '');
        $d['adresse'] = clean_input($_POST['adresse'] ?? '');
        $d['email_compte'] = strtolower(trim($_POST['email_compte'] ?? ''));
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        // Validations
        if (empty($d['nom']) || empty($d['prenom']) || empty($d['date_naissance'])) {
            $error = 'Nom, prénom et date de naissance sont obligatoires.';
        } elseif ($d['classe_id'] <= 0) {
            $error = 'Veuillez sélectionner une classe.';
        } elseif (!in_array($d['sexe'], ['M','F'])) {
            $error = 'Sexe invalide.';
        } elseif ($d['email_compte'] && !is_valid_email($d['email_compte'])) {
            $error = 'Email du compte invalide.';
        } elseif (strlen($motDePasse) < 8) {
            $error = 'Le mot de passe du compte doit contenir au moins 8 caractères.';
        } else {
            // Vérifier email unique si fourni
            if ($d['email_compte']) {
                $exists = prepareQuery('SELECT id FROM utilisateurs WHERE email = :e', ['e'=>$d['email_compte']])->fetch();
                if ($exists) $error = 'Un compte existe déjà avec cet email.';
            }
            if (!$error) {
                try {
                    // Générer le hash du mot de passe
                    $emailCompte = $d['email_compte'] ?: (strtolower($d['prenom']) . '.' . strtolower($d['nom']) . '@eleve.local');
                    $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

                    // Appel de la procédure stockée sp_inscrire_eleve
                    // (crée atomiquement l'utilisateur + l'élève, génère le matricule)
                    $stmt = getDB()->prepare("SELECT sp_inscrire_eleve(:n,:p,:dn,:s,:c,:pn,:pt,:pe,:a,:ec,:mp) AS eleve_id");
                    $stmt->bindValue(':n', $d['nom']);
                    $stmt->bindValue(':p', $d['prenom']);
                    $stmt->bindValue(':dn', $d['date_naissance']);
                    $stmt->bindValue(':s', $d['sexe']);
                    $stmt->bindValue(':c', $d['classe_id'], PDO::PARAM_INT);
                    $stmt->bindValue(':pn', $d['parent_nom']);
                    $stmt->bindValue(':pt', $d['parent_tel']);
                    $stmt->bindValue(':pe', $d['parent_email']);
                    $stmt->bindValue(':a', $d['adresse']);
                    $stmt->bindValue(':ec', $emailCompte);
                    $stmt->bindValue(':mp', $hash);
                    $stmt->execute();
                    $result = $stmt->fetch();
                    $eleveId = $result['eleve_id'] ?? null;

                    if (!$eleveId) throw new Exception('Échec de l\'inscription.');

                    log_activity('eleves.create', 'Inscription de l\'élève ' . $d['prenom'] . ' ' . $d['nom'] . ' (ID ' . $eleveId . ')');
                    set_flash('success', 'Élève inscrit avec succès.');
                    header('Location: index.php');
                    exit;
                } catch (Exception $ex) {
                    $error = 'Erreur lors de l\'inscription : ' . $ex->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-user-plus me-2"></i>Formulaire d'inscription</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <h6 class="text-primary"><i class="fa-solid fa-circle-user me-1"></i>Informations de l'élève</h6>
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
                    <label class="form-label required">Date de naissance</label>
                    <input type="date" name="date_naissance" class="form-control" value="<?= e($d['date_naissance']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Sexe</label>
                    <select name="sexe" class="form-select">
                        <option value="M" <?= $d['sexe']==='M'?'selected':'' ?>>Masculin</option>
                        <option value="F" <?= $d['sexe']==='F'?'selected':'' ?>>Féminin</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Classe</label>
                    <select name="classe_id" class="form-select" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $d['classe_id']==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?> (<?= e($c['annee_scolaire']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= e($d['adresse']) ?>">
                </div>
            </div>

            <h6 class="text-primary"><i class="fa-solid fa-people-roof me-1"></i>Parent / Tuteur</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label required">Nom du parent</label>
                    <input type="text" name="parent_nom" class="form-control" value="<?= e($d['parent_nom']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Téléphone</label>
                    <input type="text" name="parent_tel" class="form-control" value="<?= e($d['parent_tel']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email parent</label>
                    <input type="email" name="parent_email" class="form-control" value="<?= e($d['parent_email']) ?>">
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
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Inscrire</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
