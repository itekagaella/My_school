<?php
/**
 * Discipline — Export CSV (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['discipline']);
require_permission('discipline.view');

$type = clean_input(get('type', 'incidents'));
$gravites = gravites_incident();
$statuts = statuts_incident();
$statutsSanction = statuts_sanction();

if ($type === 'sanctions') {
    $rows = prepareQuery(
        "SELECT e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe, s.type_sanction, s.description,
                s.date_debut, s.date_fin, s.duree_jours, s.statut, u.prenom AS pr_prenom, u.nom AS pr_nom
         FROM sanctions s
         JOIN eleves e ON e.id = s.eleve_id
         LEFT JOIN classes c ON c.id = e.classe_id
         LEFT JOIN utilisateurs u ON u.id = s.prononcee_par
         ORDER BY s.date_debut DESC"
    )->fetchAll();
    $data = [];
    foreach ($rows as $r) {
        $data[] = [$r['matricule'], $r['nom'], $r['prenom'], $r['nom_classe'] ?? '',
                   $r['type_sanction'], $r['description'] ?? '',
                   date('d/m/Y', strtotime($r['date_debut'])),
                   $r['date_fin'] ? date('d/m/Y', strtotime($r['date_fin'])) : '',
                   $r['duree_jours'] ?? '', $statutsSanction[$r['statut']] ?? $r['statut'],
                   trim(($r['pr_prenom'] ?? '') . ' ' . ($r['pr_nom'] ?? ''))];
    }
    log_activity('discipline.export', 'Export sanctions');
    export_csv('sanctions_' . date('Ymd') . '.csv',
        ['Matricule', 'Nom', 'Prénom', 'Classe', 'Type', 'Description', 'Début', 'Fin', 'Durée (j)', 'Statut', 'Prononcée par'], $data);
}

// Par défaut : incidents
$rows = prepareQuery(
    "SELECT i.date_incident, e.matricule, e.nom, e.prenom, " . classe_label_sql('c') . " AS nom_classe, i.type_incident,
            i.gravite, i.description, i.lieu, i.statut
     FROM incidents_discipline i
     JOIN eleves e ON e.id = i.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     ORDER BY i.date_incident DESC"
)->fetchAll();

$data = [];
foreach ($rows as $r) {
    $data[] = [date('d/m/Y', strtotime($r['date_incident'])), $r['matricule'], $r['nom'], $r['prenom'],
               $r['nom_classe'] ?? '', $r['type_incident'], $gravites[$r['gravite']] ?? $r['gravite'],
               $r['description'] ?? '', $r['lieu'] ?? '', $statuts[$r['statut']] ?? $r['statut']];
}
log_activity('discipline.export', 'Export incidents');
export_csv('incidents_' . date('Ymd') . '.csv',
    ['Date', 'Matricule', 'Nom', 'Prénom', 'Classe', 'Type', 'Gravité', 'Description', 'Lieu', 'Statut'], $data);
