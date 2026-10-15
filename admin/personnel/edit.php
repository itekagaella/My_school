<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('personnel.edit');

$id = (int)get('id', 0);
$p = prepareQuery('SELECT * FROM personnel WHERE id = :id', ['id' => $id])->fetch();
if (!$p) {
    set_flash('error', 'Membre du personnel introuvable.');
    header('Location: index.php');
    exit;
}

// Rôle du compte lié (détermine le profil : surveillant / finance / discipline)
$compte = $p['user_id']
    ? prepareQuery('SELECT id, role, email FROM utilisateurs WHERE id = :id', ['id' => $p['user_id']])->fetch()
    : null;
$roleActuel = $compte['role'] ?? 'personnel';
if (!array_key_exists($roleActuel, personnel_profil_options())) $roleActuel = 'personnel';

$page_title = 'Modifier un membre du personnel';
$active_menu = 'personnel';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $nom = clean_input($_POST['nom'] ?? '');
        $prenom = clean_input($_POST['prenom'] ?? '');
        $fonction = clean_input($_POST['fonction'] ?? '');
        $tel = clean_input($_POST['tel'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $statut = $_POST['statut'] ?? $p['statut'];
        $role = $_POST['role'] ?? $roleActuel;

        if (empty($nom) || empty($prenom) || empty($fonction)) {
            $error = 'Nom, prénom et fonction sont obligatoires.';
        } elseif (!array_key_exists($role, personnel_profil_options())) {
            $error = 'Profil de personnel invalide.';
        } elseif ($email && !is_valid_email($email)) {
            $error = 'Adresse email invalide.';
        } else {
            if ($email) {
                $exists = prepareQuery('SELECT id FROM personnel WHERE email = :e AND id != :id', ['e' => $email, 'id' => $id])->fetch();
                if ($exists) $error = 'Cet email est déjà utilisé par un autre membre.';
            }

            if (!$error) {
                try {
                    getDB()->beginTransaction();

                    prepareQuery(
                        "UPDATE personnel SET nom=:n, prenom=:p, fonction=:f, tel=:t, email=:e, statut=:st WHERE id=:id",
                        ['n' => $nom, 'p' => $prenom, 'f' => $fonction, 't' => $tel, 'e' => $email, 'st' => $statut, 'id' => $id]
                    );

                    // Mise à jour du profil d'accès (rôle du compte lié)
                    if ($compte) {
                        prepareQuery(
                            'UPDATE utilisateurs SET role = :r, nom = :n, prenom = :p, updated_at = NOW() WHERE id = :uid',
                            ['r' => $role, 'n' => $nom, 'p' => $prenom, 'uid' => $compte['id']]
                        );
                    }

                    getDB()->commit();

                    log_activity('personnel.edit', 'Modification du membre ' . $p['matricule'] . ' (profil ' . $role . ')');
                    set_flash('success', 'Membre du personnel mis à jour.');
                    header('Location: view.php?id=' . $id);
                    exit;
                } catch (Exception $ex) {
                    getDB()->rollBack();
                    $error = 'Erreur lors de la mise à jour : ' . $ex->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-user-pen me-2"></i>Modifier : <?= e($p['prenom'] . ' ' . $p['nom']) ?> (<?= e($p['matricule']) ?>)</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Prénom</label>
                    <input type="text" name="prenom" class="form-control" value="<?= e($p['prenom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Nom</label>
                    <input type="text" name="nom" class="form-control" value="<?= e($p['nom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Fonction</label>
                    <input type="text" name="fonction" class="form-control" value="<?= e($p['fonction']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Profil d'accès</label>
                    <select name="role" class="form-select" required <?= $compte ? '' : 'disabled' ?>>
                        <?php foreach (personnel_profil_options() as $r => $l): ?>
                            <option value="<?= $r ?>" <?= $roleActuel === $r ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$compte): ?>
                        <div class="form-text text-warning">Aucun compte de connexion n'est lié à ce membre.</div>
                    <?php else: ?>
                        <div class="form-text">Compte : <?= e($compte['email']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="tel" class="form-control" value="<?= e($p['tel']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($p['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="actif" <?= $p['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactif" <?= $p['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                        <option value="archive" <?= $p['statut'] === 'archive' ? 'selected' : '' ?>>Archivé</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
