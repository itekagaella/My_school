<?php
/**
 * Gestion financière — Export CSV (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('finance.view');

$type = clean_input(get('type', 'paiements'));

if ($type === 'eleves') {
    $classeId = (int)get('classe_id', 0);
    $where = ["e.statut = 'actif'"];
    $params = [];
    if ($classeId > 0) { $where[] = 'e.classe_id = :cl'; $params['cl'] = $classeId; }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $rows = prepareQuery(
        "SELECT e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe,
                COALESCE((SELECT SUM(f.montant) FROM frais_scolaires f
                          WHERE f.actif = TRUE AND f.obligatoire = TRUE
                            AND (f.niveau IS NULL OR f.niveau = c.niveau)), 0) AS attendu,
                COALESCE((SELECT SUM(pa.montant) FROM paiements pa WHERE pa.eleve_id = e.id), 0) AS paye
         FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
         $whereSql
         ORDER BY e.nom, e.prenom",
        $params
    )->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $data[] = [$r['matricule'], $r['nom'], $r['prenom'], $r['nom_classe'] ?? '',
                   number_format((float)$r['attendu'], 2, ',', ''),
                   number_format((float)$r['paye'], 2, ',', ''),
                   number_format((float)$r['attendu'] - (float)$r['paye'], 2, ',', '')];
    }
    log_activity('finance.export', 'Export soldes élèves');
    export_csv('soldes_eleves_' . date('Ymd') . '.csv',
        ['Matricule', 'Nom', 'Prénom', 'Classe', 'Attendu', 'Payé', 'Solde'], $data);
}

// Par défaut : paiements
$debut = clean_input(get('debut', ''));
$fin = clean_input(get('fin', ''));
$where = []; $params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $debut)) { $where[] = 'pa.date_paiement >= :deb'; $params['deb'] = $debut; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) { $where[] = 'pa.date_paiement <= :fin'; $params['fin'] = $fin; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = prepareQuery(
    "SELECT pa.recu_numero, pa.date_paiement, e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe,
            f.libelle AS frais_libelle, pa.periode, pa.mode_paiement, pa.reference, pa.montant
     FROM paiements pa
     JOIN eleves e ON e.id = pa.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN frais_scolaires f ON f.id = pa.frais_id
     $whereSql
     ORDER BY pa.date_paiement DESC, pa.id DESC",
    $params
)->fetchAll();

$modes = modes_paiement();
$data = [];
foreach ($rows as $r) {
    $data[] = [$r['recu_numero'], date('d/m/Y', strtotime($r['date_paiement'])), $r['matricule'],
               $r['nom'], $r['prenom'], $r['nom_classe'] ?? '', $r['frais_libelle'] ?? '',
               $r['periode'] ?? '', $modes[$r['mode_paiement']] ?? $r['mode_paiement'],
               $r['reference'] ?? '', number_format((float)$r['montant'], 2, ',', '')];
}
log_activity('finance.export', 'Export paiements');
export_csv('paiements_' . date('Ymd') . '.csv',
    ['Reçu', 'Date', 'Matricule', 'Nom', 'Prénom', 'Classe', 'Frais', 'Période', 'Mode', 'Référence', 'Montant'], $data);
