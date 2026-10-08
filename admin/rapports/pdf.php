<?php
/**
 * Export PDF des rapports (FPDF, tableau paginé et mise en page moderne)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.pdf');

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/../../includes/report_pdf.php';

$type = rapport_type_valide((string)get('type', 'eleves'));
$classe_id = (int)get('classe_id', 0);
$periode_debut = (string)get('debut', date('Y-m-01'));
$periode_fin = (string)get('fin', date('Y-m-d'));

$ds = rapport_dataset($type, $classe_id, $periode_debut, $periode_fin);
$eton = (string)get_param('nom_etablissement', 'Mon École');

$periode = date('d/m/Y', strtotime($periode_debut) ?: time())
    . ' – ' . date('d/m/Y', strtotime($periode_fin) ?: time());

$meta = rapport_meta($ds, $periode, $eton);

log_activity('reports.pdf', 'Export PDF « ' . $ds['titre'] . ' » (' . count($ds['rows']) . ' lignes)');

$filename = 'rapport_' . preg_replace('/[^a-z0-9_-]/', '', $type) . '_' . date('Ymd') . '.pdf';
$rapportPdf = rapport_pdf_build($ds, $meta);
$rapportPdf->Output('D', $filename);
exit;
