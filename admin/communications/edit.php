<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('communications.publish');

$id = (int)get('id', 0);
$comm = prepareQuery('SELECT * FROM communications WHERE id = :id', ['id' => $id])->fetch();
if (!$comm) {
    set_flash('error', 'Communication introuvable.');
    header('Location: index.php');
    exit;
}

$page_title = 'Modifier une communication';
$active_menu = 'communications';

$classes = prepareQuery('SELECT * FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();
$selectedDest = array_map('trim', explode(',', $comm['destinataires']));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expirée.');
    else {
        $titre = clean_input($_POST['titre'] ?? '');
        $contenu = trim($_POST['contenu'] ?? '');
        $type = $_POST['type'] ?? 'annonce';
        $statut = $_POST['statut'] ?? $comm['statut'];
        $destinataires = $_POST['destinataires'] ?? [];

        if (empty($titre) || empty($contenu)) set_flash('error', 'Le titre et le contenu sont obligatoires.');
        elseif (!in_array($type, ['annonce', 'alerte', 'info'])) set_flash('error', 'Type invalide.');
        elseif (!in_array($statut, ['brouillon', 'publie', 'archive'])) set_flash('error', 'Statut invalide.');
        elseif (empty($destinataires)) set_flash('error', 'Veuillez sélectionner au moins un destinataire.');
        else {
            $destinatairesStr = implode(',', $destinataires);
            $datePub = $comm['date_publication'];
            if ($statut === 'publie' && $comm['statut'] !== 'publie') {
                $datePub = date('Y-m-d H:i:s');
            }

            prepareQuery(
                "UPDATE communications SET titre=:titre, contenu=:contenu, type=:type, destinataires=:destinataires,
                 date_publication=:date_publication, statut=:statut, updated_at=NOW() WHERE id=:id",
                [
                    'titre' => $titre,
                    'contenu' => $contenu,
                    'type' => $type,
                    'destinataires' => $destinatairesStr,
                    'date_publication' => $datePub,
                    'statut' => $statut,
                    'id' => $id,
                ]
            );

            if ($statut === 'publie' && $comm['statut'] !== 'publie') {
                communication_notifier_destinataires($id, $destinataires, $titre, $contenu, $type);
            }

            log_activity('communications.edit', 'Modification de la communication "' . $titre . '" (ID ' . $id . ')');
            set_flash('success', 'Communication mise à jour.');
            header('Location: view.php?id=' . $id);
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-pen me-2"></i>Modifier : <?= e($comm['titre']) ?></div>
    <div class="card-body">
        <?php display_flash(); ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required">Titre</label>
                    <input type="text" name="titre" class="form-control" value="<?= e($comm['titre']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Type</label>
                    <select name="type" class="form-select">
                        <option value="annonce" <?= $comm['type']==='annonce'?'selected':'' ?>>Annonce</option>
                        <option value="alerte" <?= $comm['type']==='alerte'?'selected':'' ?>>Alerte</option>
                        <option value="info" <?= $comm['type']==='info'?'selected':'' ?>>Info</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label required">Contenu</label>
                    <textarea name="contenu" class="form-control" rows="6" required><?= e($comm['contenu']) ?></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label required">Destinataires</label>
                    <select name="destinataires[]" class="form-select" multiple size="6">
                        <option value="tous" <?= in_array('tous', $selectedDest)?'selected':'' ?>>Tous les utilisateurs</option>
                        <optgroup label="Par rôle">
                            <option value="role:eleve" <?= in_array('role:eleve', $selectedDest)?'selected':'' ?>>Élèves</option>
                            <option value="role:prof" <?= in_array('role:prof', $selectedDest)?'selected':'' ?>>Professeurs</option>
                            <option value="role:personnel" <?= in_array('role:personnel', $selectedDest)?'selected':'' ?>>Personnel</option>
                        </optgroup>
                        <optgroup label="Par classe">
                            <?php foreach ($classes as $c): ?>
                                <option value="classe:<?= $c['id'] ?>" <?= in_array('classe:'.$c['id'], $selectedDest)?'selected':'' ?>><?= e(classe_label($c['nom_classe'], $c['section'] ?? null)) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="brouillon" <?= $comm['statut']==='brouillon'?'selected':'' ?>>Brouillon</option>
                        <option value="publie" <?= $comm['statut']==='publie'?'selected':'' ?>>Publié</option>
                        <option value="archive" <?= $comm['statut']==='archive'?'selected':'' ?>>Archivé</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Enregistrer</button>
                <a href="view.php?id=<?= $comm['id'] ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
