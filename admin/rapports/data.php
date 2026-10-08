<?php
/**
 * Source de données commune pour les aperçus et exports de rapports
 *
 * Chaque jeu de données retourne :
 *   titre   : string   – intitulé du rapport
 *   headers : string[] – libellés de colonnes
 *   types   : string[] – 'text' | 'date' | 'num' (par colonne, même index)
 *   rows    : array[]  – valeurs brutes (dates Y-m-d, numériques arrondis à 2 déc.)
 *
 * Un seul jeu = aperçu HTML, PDF et XLSX strictement identiques.
 */

function rapport_types(): array
{
    return ['eleves', 'profs', 'notes', 'presences', 'moyennes'];
}

function rapport_type_valide(string $type): string
{
    return in_array($type, rapport_types(), true) ? $type : 'eleves';
}

function rapport_titre(string $type): string
{
    $titres = [
        'eleves'    => 'Liste des élèves',
        'profs'     => 'Liste des professeurs',
        'notes'     => 'Rapport de notes',
        'presences' => 'Rapport de présences',
        'moyennes'  => 'Moyennes par élève',
    ];
    return $titres[rapport_type_valide($type)] ?? 'Rapport';
}

/**
 * Format d'affichage commun à l'aperçu HTML, au PDF et au XLSX
 */
function rapport_format_value(string $colType, $value): string
{
    if ($value === null || $value === '') return '';
    if ($colType === 'date') {
        $ts = strtotime((string)$value);
        return $ts ? date('d/m/Y', $ts) : (string)$value;
    }
    if ($colType === 'num') return number_format((float)$value, 2);
    return (string)$value;
}

/**
 * Jeu de données d'un rapport standard
 */
function rapport_dataset(string $type, int $classe_id, string $debut, string $fin): array
{
    $type = rapport_type_valide($type);
    $headers = [];
    $types = [];
    $rows = [];

    if ($type === 'eleves') {
        $headers = ['Matricule', 'Nom', 'Prénom', 'Sexe', 'Naissance', 'Classe'];
        $types   = ['text', 'text', 'text', 'text', 'date', 'text'];
        $where = $classe_id > 0 ? 'WHERE e.classe_id = :cl' : '';
        $p = $classe_id > 0 ? ['cl' => $classe_id] : [];
        $sql = 'SELECT e.matricule, e.nom, e.prenom, e.sexe, e.date_naissance, c.nom_classe
                FROM eleves e
                LEFT JOIN classes c ON c.id = e.classe_id
                ' . $where . '
                ORDER BY ' . classes_order_sql('c.nom_classe') . ', e.nom';
        foreach (prepareQuery($sql, $p)->fetchAll() as $d) {
            $rows[] = [$d['matricule'], $d['nom'], $d['prenom'], $d['sexe'], $d['date_naissance'], $d['nom_classe'] ?? '-'];
        }

    } elseif ($type === 'profs') {
        $headers = ['Matricule', 'Nom', 'Prénom', 'Spécialité', 'Tél', 'Email'];
        $types   = ['text', 'text', 'text', 'text', 'text', 'text'];
        $sql = "SELECT matricule, nom, prenom, specialite, tel, email
                FROM profs WHERE statut = 'actif' ORDER BY nom";
        foreach (prepareQuery($sql)->fetchAll() as $d) {
            $rows[] = [$d['matricule'], $d['nom'], $d['prenom'], $d['specialite'] ?? '', $d['tel'] ?? '', $d['email'] ?? ''];
        }

    } elseif ($type === 'notes') {
        $headers = ['Matricule', 'Élève', 'Matière', 'Note', 'Type', 'Date', 'Classe'];
        $types   = ['text', 'text', 'text', 'num', 'text', 'date', 'text'];
        $where = [];
        $p = [];
        if ($classe_id > 0) { $where[] = 'c.id = :cl'; $p['cl'] = $classe_id; }
        if ($debut && $fin) { $where[] = 'n.date_evaluation BETWEEN :d1 AND :d2'; $p['d1'] = $debut; $p['d2'] = $fin; }
        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = 'SELECT e.matricule, e.nom, e.prenom, m.nom_matiere, n.note, n.type_evaluation, n.date_evaluation, c.nom_classe
                FROM notes n
                JOIN eleves e ON e.id = n.eleve_id
                JOIN matieres m ON m.id = n.matiere_id
                JOIN classes c ON c.id = e.classe_id
                ' . $ws . '
                ORDER BY ' . classes_order_sql('c.nom_classe') . ', e.nom, m.nom_matiere';
        foreach (prepareQuery($sql, $p)->fetchAll() as $d) {
            $rows[] = [$d['matricule'], $d['prenom'] . ' ' . $d['nom'], $d['nom_matiere'],
                       round((float)$d['note'], 2), $d['type_evaluation'], $d['date_evaluation'], $d['nom_classe']];
        }

    } elseif ($type === 'presences') {
        $headers = ['Matricule', 'Élève', 'Date', 'Statut', 'Classe'];
        $types   = ['text', 'text', 'date', 'text', 'text'];
        $where = [];
        $p = [];
        if ($classe_id > 0) { $where[] = 'c.id = :cl'; $p['cl'] = $classe_id; }
        if ($debut && $fin) { $where[] = 'p.date_presence BETWEEN :d1 AND :d2'; $p['d1'] = $debut; $p['d2'] = $fin; }
        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = 'SELECT e.matricule, e.nom, e.prenom, p.date_presence, p.statut, c.nom_classe
                FROM presences p
                JOIN eleves e ON e.id = p.eleve_id
                JOIN classes c ON c.id = e.classe_id
                ' . $ws . '
                ORDER BY p.date_presence DESC, e.nom';
        foreach (prepareQuery($sql, $p)->fetchAll() as $d) {
            $rows[] = [$d['matricule'], $d['prenom'] . ' ' . $d['nom'], $d['date_presence'], $d['statut'], $d['nom_classe']];
        }

    } elseif ($type === 'moyennes') {
        $headers = ['Matricule', 'Nom', 'Prénom', 'Classe', 'Moyenne /20'];
        $types   = ['text', 'text', 'text', 'text', 'num'];
        $where = $classe_id > 0 ? 'WHERE e.classe_id = :cl' : '';
        $p = ['d1' => $debut ?: '1900-01-01', 'd2' => $fin ?: date('Y-m-d')];
        if ($classe_id > 0) $p['cl'] = $classe_id;
        // Une seule requête agrégée : même pondération que sp_calculer_moyenne
        // (composition x2), sans boucle N+1 par élève.
        $sql = 'SELECT e.matricule, e.nom, e.prenom, c.nom_classe,
                       COALESCE(moy.moyenne, 0) AS moyenne
                FROM eleves e
                LEFT JOIN classes c ON c.id = e.classe_id
                LEFT JOIN (
                    SELECT n.eleve_id,
                           SUM(CASE WHEN n.type_evaluation = \'composition\' THEN n.note * 2 * m.coefficient
                                    ELSE n.note * m.coefficient END)
                         / NULLIF(SUM(CASE WHEN n.type_evaluation = \'composition\' THEN 2 * m.coefficient
                                           ELSE m.coefficient END), 0) AS moyenne
                    FROM notes n
                    JOIN matieres m ON m.id = n.matiere_id
                    WHERE n.date_evaluation BETWEEN :d1 AND :d2
                    GROUP BY n.eleve_id
                ) moy ON moy.eleve_id = e.id
                ' . $where . '
                ORDER BY ' . classes_order_sql('c.nom_classe') . ', e.nom';
        foreach (prepareQuery($sql, $p)->fetchAll() as $d) {
            $rows[] = [$d['matricule'], $d['nom'], $d['prenom'], $d['nom_classe'] ?? '-', round((float)$d['moyenne'], 2)];
        }
    }

    return [
        'titre'   => rapport_titre($type),
        'headers' => $headers,
        'types'   => $types,
        'rows'    => $rows,
    ];
}

/**
 * Colonnes disponibles pour le rapport personnalisé
 * clé => [label, type, expression SQL]
 */
function rapport_custom_colonnes(string $type): array
{
    if ($type === 'eleves') {
        return [
            'matricule'     => ['Matricule', 'text', 'e.matricule'],
            'nom'           => ['Nom', 'text', 'e.nom'],
            'prenom'        => ['Prénom', 'text', 'e.prenom'],
            'sexe'          => ['Sexe', 'text', 'e.sexe'],
            'date_naissance' => ['Date naissance', 'date', 'e.date_naissance'],
            'adresse'       => ['Adresse', 'text', 'e.adresse'],
            'parent_tel'    => ['Tél. parent', 'text', 'e.parent_tel'],
            'parent_email'  => ['Email parent', 'text', 'e.parent_email'],
            'classe'        => ['Classe', 'text', 'c.nom_classe'],
        ];
    }
    if ($type === 'profs') {
        return [
            'matricule'  => ['Matricule', 'text', 'p.matricule'],
            'nom'        => ['Nom', 'text', 'p.nom'],
            'prenom'     => ['Prénom', 'text', 'p.prenom'],
            'specialite' => ['Spécialité', 'text', 'p.specialite'],
            'tel'        => ['Tél', 'text', 'p.tel'],
            'email'      => ['Email', 'text', 'p.email'],
            'statut'     => ['Statut', 'text', 'p.statut'],
        ];
    }
    if ($type === 'notes') {
        return [
            'matricule' => ['Matricule', 'text', 'e.matricule'],
            'eleve'     => ['Élève', 'text', "(e.prenom || ' ' || e.nom)"],
            'matiere'   => ['Matière', 'text', 'm.nom_matiere'],
            'note'      => ['Note', 'num', 'n.note'],
            'type'      => ['Type', 'text', 'n.type_evaluation'],
            'date'      => ['Date', 'date', 'n.date_evaluation'],
            'classe'    => ['Classe', 'text', 'c.nom_classe'],
        ];
    }
    return [];
}

/**
 * Jeu de données du rapport personnalisé (aperçus ET exports partagent ce code)
 */
function rapport_custom_dataset(string $type, array $colonnes, int $classe_id): array
{
    $defs = rapport_custom_colonnes($type);
    $headers = [];
    $types = [];
    $keys = [];
    $selects = [];
    $seen = [];

    foreach ($colonnes as $col) {
        if (!is_string($col) || !isset($defs[$col]) || isset($seen[$col])) continue;
        $seen[$col] = true;
        $headers[] = $defs[$col][0];
        $types[] = $defs[$col][1];
        $keys[] = $col;
        $selects[] = $defs[$col][2] . ' AS "' . $col . '"';
    }

    $sources = ['eleves' => 'Élèves', 'profs' => 'Professeurs', 'notes' => 'Notes'];
    $dataset = ['titre' => 'Rapport personnalisé — ' . ($sources[$type] ?? ''),
                'headers' => $headers, 'types' => $types, 'rows' => []];
    if (!$selects) return $dataset;

    $sql = '';
    $p = [];
    if ($type === 'eleves') {
        $where = $classe_id > 0 ? 'WHERE e.classe_id = :cl' : '';
        if ($classe_id > 0) $p['cl'] = $classe_id;
        $sql = 'SELECT ' . implode(', ', $selects) . '
                FROM eleves e
                LEFT JOIN classes c ON c.id = e.classe_id
                ' . $where . '
                ORDER BY ' . classes_order_sql('c.nom_classe') . ', e.nom';
    } elseif ($type === 'profs') {
        $sql = 'SELECT ' . implode(', ', $selects) . " FROM profs p WHERE p.statut = 'actif' ORDER BY p.nom";
    } elseif ($type === 'notes') {
        $where = $classe_id > 0 ? 'WHERE c.id = :cl' : '';
        if ($classe_id > 0) $p['cl'] = $classe_id;
        $sql = 'SELECT ' . implode(', ', $selects) . '
                FROM notes n
                JOIN eleves e ON e.id = n.eleve_id
                JOIN matieres m ON m.id = n.matiere_id
                JOIN classes c ON c.id = e.classe_id
                ' . $where . '
                ORDER BY ' . classes_order_sql('c.nom_classe') . ', e.nom';
    }

    if ($sql) {
        foreach (prepareQuery($sql, $p)->fetchAll() as $d) {
            $row = [];
            foreach ($keys as $i => $key) {
                $v = $d[$key];
                $row[] = $types[$i] === 'num' && $v !== null ? round((float)$v, 2) : $v;
            }
            $dataset['rows'][] = $row;
        }
    }

    return $dataset;
}

/**
 * Métadonnées de générique affichées par les exports (PDF et XLSX)
 */
function rapport_meta(array $ds, string $periode, string $etablissement): array
{
    return [
        'etablissement' => $etablissement,
        'titre'         => $ds['titre'],
        'ligne'         => 'Période : ' . $periode
            . '  •  Généré le ' . date('d/m/Y H:i')
            . '  •  ' . count($ds['rows']) . ' enregistrement(s)',
        'pied'          => $etablissement . ' — ' . date('d/m/Y'),
    ];
}
