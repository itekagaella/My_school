<?php
/**
 * Génération de rapport PDF simple (sans dépendance externe)
 * Utilise une structure PDF de base en PHP pur.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('reports.pdf');

$type = get('type', 'eleves');
$classe_id = (int)get('classe_id', 0);
$periode_debut = get('debut', date('Y-m-01'));
$periode_fin = get('fin', date('Y-m-d'));

// Récupération des données (même logique que index.php)
$rows = [];
$headers = [];
$eton = get_param('nom_etablissement', 'Mon École');

if ($type === 'eleves') {
    $headers = ['Matricule','Nom','Prénom','Sexe','Naissance','Classe'];
    $where = $classe_id>0 ? 'WHERE e.classe_id=:cl' : '';
    $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
    $data = prepareQuery("SELECT e.matricule,e.nom,e.prenom,e.sexe,e.date_naissance,c.nom_classe FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id $where ORDER BY c.nom_classe,e.nom",$p)->fetchAll();
    foreach ($data as $d) $rows[] = [$d['matricule'],$d['nom'],$d['prenom'],$d['sexe'],date('d/m/Y',strtotime($d['date_naissance'])),$d['nom_classe']??'-'];
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
    foreach ($data as $d) $rows[] = [$d['matricule'],$d['prenom'].' '.$d['nom'],$d['nom_matiere'],number_format($d['note'],2),$d['type_evaluation'],date('d/m/Y',strtotime($d['date_evaluation'])),$d['nom_classe']];
} elseif ($type === 'presences') {
    $headers = ['Matricule','Élève','Date','Statut','Classe'];
    $where=[];$p=[];
    if($classe_id>0){$where[]='c.id=:cl';$p['cl']=$classe_id;}
    if($periode_debut&&$periode_fin){$where[]='p.date_presence BETWEEN :d1 AND :d2';$p['d1']=$periode_debut;$p['d2']=$periode_fin;}
    $ws=$where?'WHERE '.implode(' AND ',$where):'';
    $data = prepareQuery("SELECT e.matricule,e.nom,e.prenom,p.date_presence,p.statut,c.nom_classe FROM presences p JOIN eleves e ON e.id=p.eleve_id JOIN classes c ON c.id=e.classe_id $ws ORDER BY p.date_presence DESC",$p)->fetchAll();
    foreach ($data as $d) $rows[] = [$d['matricule'],$d['prenom'].' '.$d['nom'],date('d/m/Y',strtotime($d['date_presence'])),$d['statut'],$d['nom_classe']];
} elseif ($type === 'moyennes') {
    $headers = ['Matricule','Nom','Prénom','Classe','Moyenne /20'];
    $where = $classe_id>0 ? 'WHERE e.classe_id=:cl' : '';
    $p = $classe_id>0 ? ['cl'=>$classe_id] : [];
    $data = prepareQuery("SELECT e.id,e.matricule,e.nom,e.prenom,c.nom_classe FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id $where ORDER BY c.nom_classe,e.nom",$p)->fetchAll();
    foreach ($data as $d) {
        $r=prepareQuery('SELECT sp_calculer_moyenne(:e) AS moy',['e'=>$d['id']])->fetch();
        $rows[]=[$d['matricule'],$d['nom'],$d['prenom'],$d['nom_classe']??'-',number_format($r['moy']??0,2)];
    }
}

// --- Génération PDF manuelle ---
$nbCols = count($headers);
function pdf_escape($s){ return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); }
$colW = 520 / max(1,$nbCols);
$lines=[];

// En-tête
$lines[]="BT /F1 20 Tf 50 800 Td (".pdf_escape($eton).") Tj ET";
$lines[]="BT /F2 12 Tf 50 780 Td (Rapport : ".pdf_escape(strtoupper($type)).") Tj ET";
$lines[]="BT /F2 10 Tf 50 765 Td (Période : ".pdf_escape($periode_debut)." - ".pdf_escape($periode_fin).") Tj ET";
$lines[]="BT /F2 10 Tf 50 752 Td (Généré le : ".pdf_escape(date('d/m/Y H:i')).") Tj ET";
$lines[]="BT /F1 10 Tf 50 735 Td (Nb enregistrements : ".count($rows).") Tj ET";

// En-têtes de tableau
$y = 705;
$headerText = implode(' | ', $headers);
$lines[]="BT /F1 10 Tf 50 {$y} Td (".pdf_escape($headerText).") Tj ET";
$y -= 15;

// Lignes de données
foreach ($rows as $row) {
    $lineText = implode(' | ', array_map(function($c){ return is_null($c)?'':$c; }, $row));
    $lines[]="BT /F2 9 Tf 50 {$y} Td (".pdf_escape($lineText).") Tj ET";
    $y -= 13;
    if ($y < 50) { // nouvelle page
        $lines[]="0.0 0.0 0.0 RG";
        $lines[]="BT /F1 10 Tf 350 30 Td (page)"." Tj ET";
        // simple saut
        break;
    }
}

$content = implode("\n", $lines);

$pdf = "%PDF-1.4\n";
$pdf .= "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
$pdf .= "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n";
$pdf .= "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<</F1 4 0 R/F2 5 0 R>>>>/Contents 6 0 R>>endobj\n";
$pdf .= "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>>endobj\n";
$pdf .= "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n";
$stream = "BT /F1 14 Tf 50 820 Td (My_School) Tj ET\n" . $content;
$streamObj = "6 0 obj<</Length ".strlen($stream).">>stream\n".$stream."\nendstream\nendobj\n";

$pdf .= $streamObj;
$pdf .= "xref\n0 7\n";
$pdf .= "0000000000 65535 f \n";
$offset = 0;
$offsets = [0, strlen("%PDF-1.4\n")];
$pos = $offsets[1];
foreach ([2=>null,3=>null,4=>null,5=>null,6=>null] as $k=>$v) {
    $offsets[$k] = $pos;
    // recalcul inutile ici
}
// Simplifier: reconstruire avec offsets corrects
$parts=[];
$parts[]="%PDF-1.4\n";
$offsetsCalc=[];
$objs = [
"1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n",
"2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n",
"3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<</F1 4 0 R/F2 5 0 R>>>>/Contents 6 0 R>>endobj\n",
"4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>>endobj\n",
"5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n",
$streamObj,
];
$pos=strlen($parts[0]);
for($i=0;$i<count($objs);$i++){
    $offsetsCalc[$i+1]=$pos;
    $parts[]=$objs[$i];
    $pos+=strlen($objs[$i]);
}
$pdf2=implode("",$parts);
$xrefStart=$pos;
$xref="xref\n0 ".(count($objs)+1)."\n0000000000 65535 f \n";
foreach($offsetsCalc as $o){$xref.=sprintf("%010d 00000 n \n",$o);}
$pdf2.=$xref;
$pdf2.="trailer<</Size ".(count($objs)+1)."/Root 1 0 R>>\n";
$pdf2.="startxref\n$xrefStart\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="rapport_'.$type.'_'.date('Ymd').'.pdf"');
echo $pdf2;
exit;
