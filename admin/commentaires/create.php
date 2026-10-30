<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('documents.manage');

$page_title = 'Nouveau commentaire';
$active_menu = 'commentaires';

$error = '';
$document_id = (int)get('document_id', 0);
$user_id = (int)get('user_id', 0);
$contenu = '';
$contexte = clean_input(get('contexte', 'document'));
$contexte_id = (int)get('contexte_id', 0);

$documents = prepareQuery(
    'SELECT id, nom_fichier FROM documents WHERE statut = :s ORDER BY nom_fichier',
    ['s' => 'actif']
)->fetchAll();

$utilisateurs = prepareQuery(
    'SELECT id, nom, prenom FROM utilisateurs WHERE actif = TRUE ORDER BY nom, prenom'
)->fetchAll();

$eleves = prepareQuery(
    "SELECT id, nom, prenom, matricule FROM eleves ORDER BY nom, prenom"
)->fetchAll();

$incidents = [];
try {
    $incidents = prepareQuery(
        "SELECT id, type_incident, date_incident FROM incidents ORDER BY date_incident DESC LIMIT 100"
    )->fetchAll();
} catch (Exception $e) {
    $incidents = [];
}

$paiements = [];
try {
    $paiements = prepareQuery(
        "SELECT id, montant, date_paiement, recu_numero FROM paiements ORDER BY date_paiement DESC LIMIT 100"
    )->fetchAll();
} catch (Exception $e) {
    $paiements = [];
}

$communications = [];
try {
    $communications = prepareQuery(
        "SELECT id, titre FROM communications ORDER BY date_publication DESC LIMIT 100"
    )->fetchAll();
} catch (Exception $e) {
    $communications = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $contexte = clean_input($_POST['contexte'] ?? 'document');
        $contexte_id = (int)($_POST['contexte_id'] ?? 0);
        $document_id = $contexte === 'document' ? $contexte_id : 0;
        $user_id = (int)($_POST['user_id'] ?? 0);
        $contenu = trim($_POST['contenu'] ?? '');

        if (!in_array($contexte, ['document','eleve','paiement','incident','communication','general'], true)) {
            $error = 'Contexte invalide.';
        } elseif ($contexte !== 'general' && $contexte_id <= 0) {
            $error = 'Veuillez sélectionner la cible du commentaire.';
        } elseif ($user_id <= 0) {
            $error = 'Veuillez sélectionner un utilisateur.';
        } elseif ($contenu === '') {
            $error = 'Le contenu du commentaire est obligatoire.';
        } else {
            prepareQuery(
                'INSERT INTO commentaires (document_id, user_id, contenu, created_at, contexte, contexte_id) VALUES (:doc_id, :user_id, :contenu, NOW(), :contexte, :contexte_id)',
                ['doc_id' => $document_id > 0 ? $document_id : null, 'user_id' => $user_id, 'contenu' => $contenu, 'contexte' => $contexte, 'contexte_id' => $contexte_id > 0 ? $contexte_id : null]
            );

            $newId = getDB()->lastInsertId();
            log_activity('commentaires.create', 'Création du commentaire ID ' . $newId . ' sur ' . $contexte . ' #' . $contexte_id);

            // Notifier les utilisateurs concernés selon le contexte
            try {
                $currentUser = current_user();
                $auteur = trim(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? ''));
                if ($contexte === 'document' && $contexte_id > 0) {
                    $doc = prepareQuery('SELECT u.id FROM documents d JOIN utilisateurs u ON u.id = d.proprietaire_id WHERE d.id = :id', ['id' => $contexte_id])->fetch();
                    if ($doc && $doc['id'] != $currentUser['id']) {
                        notify((int)$doc['id'], 'Nouveau commentaire', ($auteur ?: 'Un utilisateur') . ' a commenté un document.', 'info', 'admin/documents/view.php?id=' . $contexte_id);
                    }
                    $users = prepareQuery('SELECT DISTINCT user_id FROM commentaires WHERE document_id = :id AND user_id != :uid', ['id' => $contexte_id, 'uid' => $currentUser['id']])->fetchAll();
                    foreach ($users as $u) {
                        notify((int)$u['user_id'], 'Nouveau commentaire', ($auteur ?: 'Un utilisateur') . ' a répondu dans la discussion du document.', 'info', 'admin/documents/view.php?id=' . $contexte_id);
                    }
                } elseif ($contexte === 'eleve' && $contexte_id > 0) {
                    $eleve = prepareQuery('SELECT utilisateur_id FROM eleves WHERE id = :id', ['id' => $contexte_id])->fetch();
                    if ($eleve && $eleve['utilisateur_id'] && $eleve['utilisateur_id'] != $currentUser['id']) {
                        notify((int)$eleve['utilisateur_id'], 'Nouveau commentaire', ($auteur ?: 'Administration') . ' a ajouté un commentaire concernant votre dossier.', 'info', 'client/eleve/index.php');
                    }
                    $users = prepareQuery("SELECT DISTINCT user_id FROM commentaires WHERE COALESCE(contexte_id, document_id) = :id AND contexte = 'eleve' AND user_id != :uid", ['id' => $contexte_id, 'uid' => $currentUser['id']])->fetchAll();
                    foreach ($users as $u) {
                        notify((int)$u['user_id'], 'Nouveau commentaire', ($auteur ?: 'Un utilisateur') . ' a commenté le dossier élève.', 'info', 'admin/eleves/view.php?id=' . $contexte_id);
                    }
                } elseif ($contexte === 'incident' && $contexte_id > 0) {
                    $inc = prepareQuery('SELECT signale_par FROM incidents WHERE id = :id', ['id' => $contexte_id])->fetch();
                    if ($inc && $inc['signale_par'] && $inc['signale_par'] != $currentUser['id']) {
                        notify((int)$inc['signale_par'], 'Nouveau commentaire', ($auteur ?: 'Administration') . ' a commenté un incident.', 'info', 'client/surveillant/incidents.php');
                    }
                } elseif ($contexte === 'paiement' && $contexte_id > 0) {
                    $pai = prepareQuery('SELECT e.utilisateur_id FROM paiements p JOIN eleves e ON e.id = p.eleve_id WHERE p.id = :id', ['id' => $contexte_id])->fetch();
                    if ($pai && $pai['utilisateur_id'] && $pai['utilisateur_id'] != $currentUser['id']) {
                        notify((int)$pai['utilisateur_id'], 'Nouveau commentaire', ($auteur ?: 'Administration') . ' a commenté votre paiement.', 'info', 'client/eleve/finance.php');
                    }
                } elseif ($contexte === 'communication' && $contexte_id > 0) {
                    // Notifier tous les utilisateurs actifs pour les communications
                    $users = prepareQuery('SELECT id FROM utilisateurs WHERE actif = TRUE AND id != :uid', ['uid' => $currentUser['id']])->fetchAll();
                    foreach ($users as $u) {
                        notify((int)$u['id'], 'Nouveau commentaire', ($auteur ?: 'Un utilisateur') . ' a commenté une communication.', 'info', 'admin/communications/index.php');
                    }
                } else {
                    // Contexte général - notifier tous les admins
                    $admins = prepareQuery("SELECT id FROM utilisateurs WHERE role = 'admin' AND actif = TRUE AND id != :uid", ['uid' => $currentUser['id']])->fetchAll();
                    foreach ($admins as $a) {
                        notify((int)$a['id'], 'Nouveau commentaire', ($auteur ?: 'Un utilisateur') . ' a laissé un commentaire général.', 'info', 'admin/commentaires/index.php');
                    }
                }
            } catch (Exception $e) {
                error_log('Notification commentaire: ' . $e->getMessage());
            }

            set_flash('success', 'Commentaire ajouté avec succès.');
            header('Location: index.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>

<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-comment-dots me-2"></i>Nouveau commentaire</div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Contexte</label>
                    <select name="contexte" id="contexteSelect" class="form-select" required>
                        <option value="document" <?= $contexte === 'document' ? 'selected' : '' ?>>Document</option>
                        <option value="eleve" <?= $contexte === 'eleve' ? 'selected' : '' ?>>Élève</option>
                        <option value="paiement" <?= $contexte === 'paiement' ? 'selected' : '' ?>>Paiement</option>
                        <option value="incident" <?= $contexte === 'incident' ? 'selected' : '' ?>>Incident</option>
                        <option value="communication" <?= $contexte === 'communication' ? 'selected' : '' ?>>Communication</option>
                        <option value="general" <?= $contexte === 'general' ? 'selected' : '' ?>>Général</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Cible</label>
                    <select name="contexte_id" id="cibleSelect" class="form-select" required>
                        <optgroup label="Documents" data-contexte="document">
                            <?php foreach ($documents as $doc): ?>
                                <option value="<?= $doc['id'] ?>" data-contexte="document" <?= ($contexte === 'document' && $contexte_id === (int)$doc['id']) ? 'selected' : '' ?>><?= e($doc['nom_fichier']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Élèves" data-contexte="eleve">
                            <?php foreach ($eleves as $e): ?>
                                <option value="<?= $e['id'] ?>" data-contexte="eleve" <?= ($contexte === 'eleve' && $contexte_id === (int)$e['id']) ? 'selected' : '' ?>><?= e($e['prenom'] . ' ' . $e['nom'] . ' (' . ($e['matricule'] ?? '') . ')') ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Paiements" data-contexte="paiement">
                            <?php foreach ($paiements as $p): ?>
                                <option value="<?= $p['id'] ?>" data-contexte="paiement" <?= ($contexte === 'paiement' && $contexte_id === (int)$p['id']) ? 'selected' : '' ?>>Reçu <?= e($p['recu_numero'] ?? '#' . $p['id']) ?> - <?= number_format($p['montant'], 0, ',', ' ') ?> - <?= date('d/m/Y', strtotime($p['date_paiement'])) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Incidents" data-contexte="incident">
                            <?php foreach ($incidents as $i): ?>
                                <option value="<?= $i['id'] ?>" data-contexte="incident" <?= ($contexte === 'incident' && $contexte_id === (int)$i['id']) ? 'selected' : '' ?>>Incident #<?= $i['id'] ?> - <?= e($i['type_incident']) ?> - <?= date('d/m/Y', strtotime($i['date_incident'])) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Communications" data-contexte="communication">
                            <?php foreach ($communications as $c): ?>
                                <option value="<?= $c['id'] ?>" data-contexte="communication" <?= ($contexte === 'communication' && $contexte_id === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['titre']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Utilisateur</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">— Sélectionner un utilisateur —</option>
                        <?php foreach ($utilisateurs as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_id === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label required">Contenu</label>
                    <textarea name="contenu" class="form-control" rows="5" required><?= e($contenu) ?></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Ajouter</button>
                <a href="index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const ctx = document.getElementById('contexteSelect');
    const cible = document.getElementById('cibleSelect');
    function filterCible(){
        const v = ctx.value;
        for (let i=0;i<cible.options.length;i++){
            const o = cible.options[i];
            if (o.tagName === 'OPTGROUP') { o.style.display = o.dataset.contexte === v ? '' : 'none'; continue; }
            if (o.dataset.contexte) o.style.display = o.dataset.contexte === v ? '' : 'none';
        }
        cible.disabled = (v === 'general');
        if (v === 'general') cible.value = '';
        else {
            for (let i=0;i<cible.options.length;i++){
                const o = cible.options[i];
                if (o.dataset.contexte === v && o.style.display !== 'none'){ cible.value = o.value; break; }
            }
        }
    }
    ctx.addEventListener('change', filterCible);
    filterCible();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
