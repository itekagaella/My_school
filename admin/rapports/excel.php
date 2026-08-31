<?php
/**
 * Export Excel (format CSV compatible Excel)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('reports.excel');

$type = get('type', 'eleves');
$classe_id = (int)get('classe_id', 0);
$periode_debut = get('debut', date('Y-m-01'));
$periode_fin = get('fin', date('Y-m-d'));

$headers = [];
$rows = [];

if ($type === 'eleves') {
    $headers = ['Matricule','Nom','Prénom','Sexe','Naissance','Classe'];
    $where = $classe_id>0 ? 'WHERE e.classe_id=:cl' : '';
    $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
    $data = prepareQuery("SELECT e.matricule,e.nom,e.prenom,e.sexe,e.date_naissance,c.nom_classe FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id $where ORDER BY c.nom_classe,e.nom",$p)->fetchAll();
    foreach ($data as $d) $rows[] = [$d['matricule'],$d['nom'],$d['prenom'],$d['sexe'],$d['date_naissance'],$d['nom_classe']??''];
} elseif ($type === 'profs') {
    $headers = ['Matricule','Nom','Prénom','Spécialité','Tél','Email'];
    $data = prepareQuery("SELECT matricule,nom,prenom,specialite,tel,email FROM profs WHERE statut='actif' ORDER BY nom")->fetchAll();
    foreach ($data as $d) $rows[] = [$d['matricule'],$d['nom'],$d['prenom'],$d['specialite']??'',$d['tel']??'',$d['email']??''];
} elseif ($type === 'notes') {
    $headers = ['Matricule','Élève','Matière','Note','Type','Date','Classe'];
    $where=[];$p=[];
    if($classe_id>0){$where[]='c.id=:cl';$p['cl']=$classe_id;}
    if($periode_debut&&$periode_fin){$where[]='n.date_evaluation BETWEEN :d1 AND :d2';$p['d1']=$periode_debut;$p['d2']=$periode_fin;}
    $ws=$where?'WHERE '.implode(' AND ',$where):'';
    $data = prepareQuery("SELECT e.matricule,e.nom,e.prenom,m.nom_matiere,n.note,n.type_evaluation,n.date_evaluation,c.nom_classe FROM notes n JOIN eleves e ON e.id=n.eleve_id JOIN matieres m ON m.id=n.matiere_id JOIN classes c ON c.id=e.classe_id $ws ORDER BY c.nom_classe,e.nom",$p)->fetchAll();
    foreach ($data as $d) $rows[] = [$d['matricule'],$d['prenom'].' '.$d['nom'],$d['nom_matiere'],$d['note'],$d['type_evaluation'],$d['date_evaluation'],$d['nom_classe']];
} elseif ($type === 'presences') {
    $headers = ['Matricule','Élève','Date','Statut','Classe'];
    $where=[];$p=[];
    if($classe_id>0){$where[]='c.id=:cl';$p['cl']=$classe_id;}
    if($periode_debut&&$periode_fin){$where[]='p.date_presence BETWEEN :d1 AND :d2';$p['d1']=$periode_debut;$p['d2']=$periode_fin;}
    $ws=$where?'WHERE '.implode(' AND ',$where):'';
    $data = prepareQuery("SELECT e.matricule,e.nom,e.prenom,p.date_presence,p.statut,c.nom_classe FROM presences p JOIN eleves e ON e.id=p.eleve_id JOIN classes c ON c.id=e.classe_id $ws ORDER BY p.date_presence DESC",$p)->fetchAll();
    foreach ($data as $d) $rows[] = [$d['matricule'],$d['prenom'].' '.$d['nom'],$d['date_presence'],$d['statut'],$d['nom_classe']];
} elseif ($type === 'moyennes') {
    $headers = ['Matricule','Nom','Prénom','Classe','Moyenne /20'];
    $where = $classe_id>0 ? 'WHERE e.classe_id=:cl' : '';
    $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
    $data = prepareQuery("SELECT e.id,e.matricule,e.nom,e.prenom,c.nom_classe FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id $where ORDER BY c.nom_classe,e.nom",$p)->fetchAll();
    foreach ($data as $d) {
        $r=prepareQuery('SELECT sp_calculer_moyenne(:e) AS moy',['e'=>$d['id']])->fetch();
        $rows[]=[$d['matricule'],$d['nom'],$d['prenom'],$d['nom_classe']??'',$r['moy']??0];
    }
}

// Génération CSV avec séparateur ; pour Excel
$eton = get_param('nom_etablissement', 'Mon École');
$csv = $eton . "\n";
$csv .= 'Rapport: ' . $type . ' | Periode: ' . $periode_debut . ' - ' . $periode_fin . "\n\n";
$csv .= implode(';', $headers) . "\n";
foreach ($rows as $r) {
    $escaped = array_map(function($c){ return '"'.str_replace('"','""',$c).'"'; }, $r);
    $csv .= implode(';', $escaped) . "\n";
}

// BOM UTF-8 pour Excel
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="rapport_'.$type.'_'.date('Ymd').'.csv"');
echo "\xEF\xBB\xBF" . $csv;
exit;
