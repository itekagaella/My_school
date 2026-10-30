<?php
/**
 * Espace Discipline — Élèves (fiche disciplinaire)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['discipline']);
$user = current_user();
$page_title = 'Élèves';
$active_menu = 'eleves';

$pers = prepareQuery('SELECT * FROM personnel WHERE user_id = :u', ['u' => $user['id']])->fetch();
if (!$pers) {
    logout('Profil personnel introuvable. Contactez l\'administrateur.');
}

$search = clean_input(get('search', ''));
$classeId = (int)get('classe_id', 0);

$where = ["e.statut = 'actif'"];
$params = [];
if ($search !== '') {
    $where[] = '(e.nom ILIKE :s OR e.prenom ILIKE :s OR e.matricule ILIKE :s)';
    $params['s'] = '%' . $search . '%';
}
if ($classeId > 0) { $where[] = 'e.classe_id = :cl'; $params['cl'] = $classeId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$eleves = prepareQuery(
    "SELECT e.id, e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe,
            (SELECT COUNT(*) FROM incidents_discipline i WHERE i.eleve_id = e.id) AS nb_incidents,
            (SELECT COUNT(*) FROM sanctions s WHERE s.eleve_id = e.id AND s.statut = 'en_cours') AS nb_sanctions
     FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     $whereSql
     ORDER BY " . classes_order_sql('c.nom_classe') . ", e.nom, e.prenom",
    $params
)->fetchAll();

$classes = prepareQuery('SELECT id, nom_classe, section FROM classes ORDER BY ' . classes_order_sql('nom_classe'))->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_client.php';
?>
<?php display_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Fiches disciplinaires</h4>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-md-5"><input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Nom, prénom ou matricule..."></div>
            <div class="col-md-4">
                <select name="classe_id" class="form-select">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classeId==$c['id']?'selected':'' ?>><?= e(classe_label($c['nom_classe'], $c['section'] ?? null)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrer</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Matricule</th><th>Élève</th><th>Classe</th><th class="text-center">Incidents</th><th class="text-center">Sanctions en cours</th><th class="text-end"></th></tr></thead>
                <tbody>
                    <?php if (empty($eleves)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun élève trouvé.</td></tr>
                    <?php else: foreach ($eleves as $el): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e($el['matricule']) ?></td>
                            <td><?= e($el['prenom'] . ' ' . $el['nom']) ?></td>
                            <td class="small"><?= e($el['nom_classe'] ?? '-') ?></td>
                            <td class="text-center"><span class="badge bg-<?= (int)$el['nb_incidents'] > 0 ? 'warning' : 'light text-dark' ?>"><?= (int)$el['nb_incidents'] ?></span></td>
                            <td class="text-center"><span class="badge bg-<?= (int)$el['nb_sanctions'] > 0 ? 'danger' : 'light text-dark' ?>"><?= (int)$el['nb_sanctions'] ?></span></td>
                            <td class="text-end"><a href="incidents.php?search=<?= urlencode($el['matricule']) ?>" class="btn btn-sm btn-outline-primary" title="Voir les incidents"><i class="fa-solid fa-triangle-exclamation"></i></a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
