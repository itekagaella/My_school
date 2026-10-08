<?php
/**
 * Export Excel (.xlsx) des rapports — classeur généré en local
 */
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.excel');

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/../../includes/xlsx.php';

$type = rapport_type_valide((string)get('type', 'eleves'));
$classe_id = (int)get('classe_id', 0);
$periode_debut = (string)get('debut', date('Y-m-01'));
$periode_fin = (string)get('fin', date('Y-m-d'));

$ds = rapport_dataset($type, $classe_id, $periode_debut, $periode_fin);
$eton = (string)get_param('nom_etablissement', 'Mon École');

$periode = date('d/m/Y', strtotime($periode_debut) ?: time())
    . ' – ' . date('d/m/Y', strtotime($periode_fin) ?: time());

$meta = rapport_meta($ds, $periode, $eton);

log_activity('reports.excel', 'Export XLSX « ' . $ds['titre'] . ' » (' . count($ds['rows']) . ' lignes)');

$filename = 'rapport_' . preg_replace('/[^a-z0-9_-]/', '', $type) . '_' . date('Ymd') . '.xlsx';
rapport_xlsx_download($ds, $meta, $filename);
