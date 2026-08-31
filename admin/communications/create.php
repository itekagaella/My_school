<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('communications.create');

$page_title = 'Nouvelle communication';
$active_menu = 'communications';

$error = '';
$d = ['titre'=>'', 'contenu'=>'', 'type'=>'annonce', 'statut'=>'brouillon', 'destinataires'=>[]];

$classes = prepareQuery('SELECT * FROM classes ORDER BY nom_classe')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $d['titre'] = clean_input($_POST['titre'] ?? '');
        $d['contenu'] = trim($_POST['contenu'] ?? '');
        $d['type'] = $_POST['type'] ?? 'annonce';
        $d['statut'] = $_POST['statut'] ?? 'brouillon';
        $d['destinataires'] = $_POST['destinataires'] ?? [];

        if (empty($d['titre']) || empty($d['contenu'])) {
            $error = 'Le titre et le contenu sont obligatoires.';
        } elseif (!in_array($d['type'], ['annonce', 'alerte', 'info'])) {
            $error = 'Type invalide.';
        } elseif (!in_array($d['statut'], ['brouillon', 'publie'])) {
            $error = 'Statut invalide.';
        } elseif (empty($d['destinataires'])) {
            $error = 'Veuillez sélectionner au moins un destinataire.';
        } else {
            $destinatairesStr = implode(',', $d['destinataires']);
            $auteurId = $_SESSION['user_id'];
            $datePub = $d['statut'] === 'publie' ? date('Y-m-d H:i:s') : null;

            $result = prepareQuery(
                "INSERT INTO communications (titre, contenu, auteur_id, destinataires, type, date_publication, statut, updated_at)
                 VALUES (:titre, :contenu, :auteur_id, :destinataires, :type, :date_publication, :statut, NOW())",
                [
                    'titre' => $d['titre'],
                    'contenu' => $d['contenu'],
                    'auteur_id' => $auteurId,
                    'destinataires' => $destinatairesStr,
                    'type' => $d['type'],
                    'date_publication' => $datePub,
                    'statut' => $d['statut'],
                ]
            );

            $commId = getDB()->lastInsertId();

            if ($d['statut'] === 'publie') {
                send_notifications($commId, $d['destinataires'], $d['titre'], $d['contenu'], $d['type']);
            }

            log_activity('communications.create', 'Création de la communication "' . $d['titre'] . '" (ID ' . $commId . ')');
            set_flash('success', 'Communication créée avec succès.');
            header('Location: index.php');
            exit;
        }
    }
}

function send_notifications(int $commId, array $destinataires, string $titre, string $contenu, string $type): void
{
    $lien = 'client/eleve/communication.php?id=' . $commId;
    $message = mb_substr($contenu, 0, 200);
    $userIds = [];

    foreach ($destinataires as $dest) {
        if ($dest === 'tous') {
            $users = prepareQuery('SELECT id FROM utilisateurs WHERE actif = TRUE')->fetchAll();
            foreach ($users as $u) $userIds[] = $u['id'];
            break;
        } elseif (strpos($dest, 'role:') === 0) {
            $role = substr($dest, 5);
            $users = prepareQuery('SELECT id FROM utilisateurs WHERE role = :role AND actif = TRUE', ['role' => $role])->fetchAll();
            foreach ($users as $u) $userIds[] = $u['id'];
        } elseif (strpos($dest, 'classe:') === 0) {
            $classeId = (int)substr($dest, 7);
            $users = prepareQuery(
                'SELECT DISTINCT u.id FROM utilisateurs u JOIN eleves e ON e.utilisateur_id = u.id WHERE e.classe_id = :cid AND u.actif = TRUE',
                ['cid' => $classeId]
            )->fetchAll();
            foreach ($users as $u) $userIds[] = $u['id'];
        }
    }

    $userIds = array_unique($userIds);
    foreach ($userIds as $uid) {
        notify($uid, $titre, $message, $type, $lien);
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-bullhorn me-2"></i>Nouvelle communication</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required">Titre</label>
                    <input type="text" name="titre" class="form-control" value="<?= e($d['titre']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Type</label>
                    <select name="type" class="form-select">
                        <option value="annonce" <?= $d['type']==='annonce'?'selected':'' ?>>Annonce</option>
                        <option value="alerte" <?= $d['type']==='alerte'?'selected':'' ?>>Alerte</option>
                        <option value="info" <?= $d['type']==='info'?'selected':'' ?>>Info</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label required">Contenu</label>
                    <textarea name="contenu" class="form-control" rows="6" required><?= e($d['contenu']) ?></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label required">Destinataires</label>
                    <select name="destinataires[]" class="form-select" multiple size="6">
                        <option value="tous" <?= in_array('tous', $d['destinataires'])?'selected':'' ?>>Tous les utilisateurs</option>
                        <optgroup label="Par rôle">
                            <option value="role:eleve" <?= in_array('role:eleve', $d['destinataires'])?'selected':'' ?>>Élèves</option>
                            <option value="role:prof" <?= in_array('role:prof', $d['destinataires'])?'selected':'' ?>>Professeurs</option>
                            <option value="role:personnel" <?= in_array('role:personnel', $d['destinataires'])?'selected':'' ?>>Personnel</option>
                        </optgroup>
                        <optgroup label="Par classe">
                            <?php foreach ($classes as $c): ?>
                                <option value="classe:<?= $c['id'] ?>" <?= in_array('classe:'.$c['id'], $d['destinataires'])?'selected':'' ?>><?= e($c['nom_classe']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="brouillon" <?= $d['statut']==='brouillon'?'selected':'' ?>>Brouillon</option>
                        <option value="publie" <?= $d['statut']==='publie'?'selected':'' ?>>Publié</option>
                    </select>
                    <small class="text-muted">Si "Publié", les destinataires seront notifiés.</small>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Créer</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
