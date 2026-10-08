<?php
/**
 * Export du rapport personnalisé (PDF ou XLSX) — partagé avec custom.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/../../includes/xlsx.php';
require_once __DIR__ . '/../../includes/report_pdf.php';

$format = strtolower((string)get('format', 'xlsx'));
if (!in_array($format, ['pdf', 'xlsx'], true)) {
    $format = 'xlsx';
}
require_permission($format === 'pdf' ? 'reports.pdf' : 'reports.excel');

$type = rapport_type_valide((string)get('type', 'eleves'));
$classe_id = (int)get('classe_id', 0);

// Colonnes cochées (priorité GET/POST)
$colonnes = get('colonnes', []);
if (empty($colonnes) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $colonnes = $_POST['colonnes'] ?? [];
}
if (!is_array($colonnes)) $colonnes = [$colonnes];

$defs = rapport_custom_colonnes($type);
$colonnes_sel = [];
$seen = [];
foreach ($colonnes as $col) {
    if (is_string($col) && isset($defs[$col]) && !isset($seen[$col])) {
        $seen[$col] = true;
        $colonnes_sel[] = $col;
    }
}

$ds = rapport_custom_dataset($type, $colonnes_sel, $classe_id);
$eton = (string)get_param('nom_etablissement', 'Mon École');
$periode = date('d/m/Y');

$meta = rapport_meta($ds, $periode, $eton);

$slug = preg_replace('/[^a-z0-9_-]/', '', $type) . '_' . date('Ymd') . '_' . date('His');
log_activity('reports.custom.export', 'Export custom ' . $format . ' — ' . $ds['titre'] . ' (' . count($ds['rows']) . ')');

if ($format === 'pdf') {
    require_permission('reports.pdf');
    $filename = 'rapport_custom_' . $slug . '.pdf';
    $rapportPdf = rapport_pdf_build($ds, $meta);
    $rapportPdf->Output('D', $filename);
} else {
    require_permission('reports.excel');
    $filename = 'rapport_custom_' . $slug . '.xlsx';
    rapport_xlsx_download($ds, $meta, $filename);
}
exit;
