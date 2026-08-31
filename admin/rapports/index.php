<?php
/**
 * Rapports et exports (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('reports.view');

$page_title = 'Rapports';
$active_menu = 'rapports';

$type = get('type', 'eleves');
$classe_id = (int)get('classe_id', 0);
$periode_debut = get('debut', date('Y-m-01'));
$periode_fin = get('fin', date('Y-m-d'));

$classes = prepareQuery('SELECT * FROM classes ORDER BY nom_classe')->fetchAll();
$profs = prepareQuery('SELECT * FROM profs WHERE statut = :s ORDER BY nom', ['s'=>'actif'])->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';
?>
<?php display_flash(); ?>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-file-lines me-2"></i>Génération de rapports</div>
    <div class="card-body">
        <form method="get" action="" class="row g-3 align-items-end mb-4">
            <div class="col-md-3">
                <label class="form-label">Type de rapport</label>
                <select name="type" class="form-select">
                    <option value="eleves" <?= $type==='eleves'?'selected':'' ?>>Liste des élèves</option>
                    <option value="profs" <?= $type==='profs'?'selected':'' ?>>Liste des professeurs</option>
                    <option value="notes" <?= $type==='notes'?'selected':'' ?>>Rapport de notes</option>
                    <option value="presences" <?= $type==='presences'?'selected':'' ?>>Rapport de présences</option>
                    <option value="moyennes" <?= $type==='moyennes'?'selected':'' ?>>Moyennes par élève</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Classe</label>
                <select name="classe_id" class="form-select">
                    <option value="0">Toutes</option>
                    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classe_id==$c['id']?'selected':'' ?>><?= e($c['nom_classe']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Début</label>
                <input type="date" name="debut" class="form-control" value="<?= e($periode_debut) ?>"></div>
            <div class="col-md-2"><label class="form-label">Fin</label>
                <input type="date" name="fin" class="form-control" value="<?= e($periode_fin) ?>"></div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-eye me-1"></i>Aperçu</button>
            </div>
        </form>

        <div class="d-flex gap-2 mb-3">
            <a href="pdf.php?type=<?= e($type) ?>&classe_id=<?= $classe_id ?>&debut=<?= e($periode_debut) ?>&fin=<?= e($periode_fin) ?>" class="btn btn-danger">
                <i class="fa-solid fa-file-pdf me-1"></i>Exporter en PDF
            </a>
            <a href="excel.php?type=<?= e($type) ?>&classe_id=<?= $classe_id ?>&debut=<?= e($periode_debut) ?>&fin=<?= e($periode_fin) ?>" class="btn btn-success">
                <i class="fa-solid fa-file-excel me-1"></i>Exporter en Excel
            </a>
            <a href="custom.php" class="btn btn-outline-primary"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Rapport personnalisé</a>
        </div>

        <?php
        // Aperçu des données du rapport
        $apercu = [];
        if ($type === 'eleves') {
            $where = $classe_id>0 ? 'WHERE e.classe_id=:cl' : '';
            $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
            $apercu = prepareQuery(
                "SELECT e.matricule, e.nom, e.prenom, e.sexe, e.date_naissance, c.nom_classe, e.created_at
                 FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id $where ORDER BY c.nom_classe, e.nom", $p)->fetchAll();
        } elseif ($type === 'profs') {
            $apercu = prepareQuery("SELECT matricule, nom, prenom, specialite, tel, email FROM profs WHERE statut='actif' ORDER BY nom")->fetchAll();
        } elseif ($type === 'notes') {
            $where = [];
            $p = [];
            if ($classe_id>0) { $where[]='c.id=:cl'; $p['cl']=$classe_id; }
            if ($periode_debut && $periode_fin) { $where[]='n.date_evaluation BETWEEN :d1 AND :d2'; $p['d1']=$periode_debut; $p['d2']=$periode_fin; }
            $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
            $apercu = prepareQuery(
                "SELECT e.nom, e.prenom, e.matricule, m.nom_matiere, n.note, n.type_evaluation, n.date_evaluation, c.nom_classe
                 FROM notes n JOIN eleves e ON e.id=n.eleve_id JOIN matieres m ON m.id=n.matiere_id JOIN classes c ON c.id=e.classe_id
                 $ws ORDER BY c.nom_classe, e.nom", $p)->fetchAll();
        } elseif ($type === 'presences') {
            $where = [];
            $p = [];
            if ($classe_id>0) { $where[]='c.id=:cl'; $p['cl']=$classe_id; }
            if ($periode_debut && $periode_fin) { $where[]='p.date_presence BETWEEN :d1 AND :d2'; $p['d1']=$periode_debut; $p['d2']=$periode_fin; }
            $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
            $apercu = prepareQuery(
                "SELECT e.nom, e.prenom, e.matricule, p.date_presence, p.statut, c.nom_classe
                 FROM presences p JOIN eleves e ON e.id=p.eleve_id JOIN classes c ON c.id=e.classe_id
                 $ws ORDER BY p.date_presence DESC", $p)->fetchAll();
        } elseif ($type === 'moyennes') {
            $where = $classe_id>0 ? 'WHERE e.classe_id=:cl' : '';
            $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
            $eleves_moy = prepareQuery(
                "SELECT e.id, e.matricule, e.nom, e.prenom, c.nom_classe FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id $where ORDER BY c.nom_classe, e.nom", $p)->fetchAll();
            foreach ($eleves_moy as $em) {
                $r = prepareQuery('SELECT sp_calculer_moyenne(:e) AS moy', ['e'=>$em['id']])->fetch();
                $apercu[] = array_merge($em, ['moyenne' => $r['moy'] ?? 0]);
            }
        }
        ?>

        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <?php if ($type==='eleves'||$type==='moyennes'): ?>
                            <th>Matricule</th><th>Nom</th><th>Prénom</th><th>Sexe</th><?php if($type==='eleves'):?><th>Naissance</th><?php endif; ?><th>Classe</th><?php if($type==='moyennes'):?><th>Moyenne /20</th><?php endif; ?>
                        <?php elseif ($type==='profs'): ?>
                            <th>Matricule</th><th>Nom</th><th>Prénom</th><th>Spécialité</th><th>Tél</th>
                        <?php elseif ($type==='notes'): ?>
                            <th>Matricule</th><th>Élève</th><th>Matière</th><th>Note</th><th>Type</th><th>Date</th><th>Classe</th>
                        <?php elseif ($type==='presences'): ?>
                            <th>Matricule</th><th>Élève</th><th>Date</th><th>Statut</th><th>Classe</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($apercu)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune donnée pour ce rapport.</td></tr>
                    <?php else: foreach ($apercu as $i=>$rw): if($i>=50) break; ?>
                        <tr>
                            <?php if ($type==='eleves'||$type==='moyennes'): ?>
                                <td><?= e($rw['matricule']) ?></td><td><?= e($rw['nom']) ?></td><td><?= e($rw['prenom']) ?></td>
                                <td><?= $rw['sexe']??'' ?></td>
                                <?php if($type==='eleves'):?><td><?= date('d/m/Y', strtotime($rw['date_naissance'])) ?></td><?php endif; ?>
                                <td><?= e($rw['nom_classe']??'-') ?></td>
                                <?php if($type==='moyennes'):?><td><strong><?= number_format($rw['moyenne'],2) ?></strong></td><?php endif; ?>
                            <?php elseif ($type==='profs'): ?>
                                <td><?= e($rw['matricule']) ?></td><td><?= e($rw['nom']) ?></td><td><?= e($rw['prenom']) ?></td><td><?= e($rw['specialite']??'') ?></td><td><?= e($rw['tel']??'') ?></td>
                            <?php elseif ($type==='notes'): ?>
                                <td><?= e($rw['matricule']) ?></td><td><?= e($rw['prenom'].' '.$rw['nom']) ?></td><td><?= e($rw['nom_matiere']) ?></td>
                                <td><span class="badge bg-<?= $rw['note']>=10?'success':'danger' ?>"><?= number_format($rw['note'],2) ?></span></td>
                                <td><?= e($rw['type_evaluation']) ?></td><td><?= date('d/m/Y',strtotime($rw['date_evaluation'])) ?></td><td><?= e($rw['nom_classe']) ?></td>
                            <?php elseif ($type==='presences'): ?>
                                <td><?= e($rw['matricule']) ?></td><td><?= e($rw['prenom'].' '.$rw['nom']) ?></td><td><?= date('d/m/Y',strtotime($rw['date_presence'])) ?></td><td><?= e($rw['statut']) ?></td><td><?= e($rw['nom_classe']) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <?php if (count($apercu) > 50): ?><p class="text-muted small">Aperçu limité à 50 lignes. Exportez pour le rapport complet.</p><?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_content.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
