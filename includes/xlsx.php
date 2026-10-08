<?php
/**
 * Générateur XLSX maison (ZipArchive + XMLWriter natifs, aucune connexion requise)
 *
 * Feuille stylée : titre fusionné, en-tête blanc sur orange, lignes figées,
 * filtre automatique, largeurs calculées, zébrage, cellules typées
 * (dates au format JJ/MM/AAAA, notes numériques à 2 décimales).
 */

/** Index de colonne → lettre Excel (0 → A, 26 → AA) */
function xlsx_col_letter(int $i): string
{
    $s = '';
    $i++;
    while ($i > 0) {
        $i--;
        $s = chr(65 + ($i % 26)) . $s;
        $i = intdiv($i, 26);
    }
    return $s;
}

function xlsx_esc(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Date JJ/MM/AAAA → numéro de série Excel (1900 système, epoch 1899-12-30) */
function xlsx_date_serial(string $dmy)
{
    $ts = DateTime::createFromFormat('d/m/Y', $dmy);
    if (!$ts) return null;
    $f = $ts->format('d/m/Y');
    if ($f !== $dmy) return null;
    return (int)(gmmktime(0, 0, 0, (int)$ts->format('m'), (int)$ts->format('d'), (int)$ts->format('Y')) / 86400) + 25569;
}

/**
 * Construit le contenu binaire du classeur XLSX
 *
 * @param array $ds   dataset (titre, headers, types, rows)
 * @param array $meta rapport_meta()
 */
function rapport_xlsx_build(array $ds, array $meta): string
{
    $headers = $ds['headers'];
    $types = $ds['types'];
    $rows = $ds['rows'];
    $nbCols = max(1, count($headers));

    // Valeurs d'affichage (identiques à l'aperçu et au PDF)
    $cells = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as $i => $v) {
            $line[] = rapport_format_value($types[$i] ?? 'text', $v);
        }
        $cells[] = $line;
    }

    $lastCol = xlsx_col_letter($nbCols - 1);

    // Largeurs de colonnes calculées sur l'en-tête + les données
    $widths = array_fill(0, $nbCols, 4);
    foreach ($headers as $i => $h) {
        $widths[$i] = max($widths[$i], mb_strlen((string)$h, 'UTF-8'));
    }
    foreach ($cells as $line) {
        foreach ($line as $i => $v) {
            $widths[$i] = max($widths[$i], mb_strlen((string)$v, 'UTF-8'));
        }
    }

    // ---- Feuille ----
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A5" sqref="A5"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>';
    for ($i = 0; $i < $nbCols; $i++) {
        $w = min(max($widths[$i] + 2, 9), 45);
        $n = $i + 1;
        $sheet .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
    }
    $sheet .= '</cols><sheetData>';

    // Ligne 1 : marque fusionnée
    $sheet .= '<row r="1" ht="22" customHeight="1">'
        . '<c r="A1" s="1" t="inlineStr"><is><t>' . xlsx_esc($meta['etablissement'] ?? '') . '</t></is></c></row>';
    // Ligne 2 : titre du rapport fusionné
    $sheet .= '<row r="2" ht="18" customHeight="1">'
        . '<c r="A2" s="10" t="inlineStr"><is><t>' . xlsx_esc($meta['titre'] ?? '') . '</t></is></c></row>';
    // Ligne 3 : période / date de génération / volume fusionnés
    $sheet .= '<row r="3">'
        . '<c r="A3" s="9" t="inlineStr"><is><t>' . xlsx_esc($meta['ligne'] ?? '') . '</t></is></c></row>';
    // Ligne 4 : en-têtes de colonnes
    $sheet .= '<row r="4" ht="18" customHeight="1">';
    foreach ($headers as $i => $h) {
        $sheet .= '<c r="' . xlsx_col_letter($i) . '4" s="2" t="inlineStr"><is><t>' . xlsx_esc((string)$h) . '</t></is></c>';
    }
    $sheet .= '</row>';

    // Données typées à partir de la ligne 5
    foreach ($cells as $r => $line) {
        $rowNum = $r + 5;
        $sheet .= '<row r="' . $rowNum . '">';
        foreach ($line as $i => $v) {
            $ref = xlsx_col_letter($i) . $rowNum;
            $t = $types[$i] ?? 'text';
            $zebra = ($r % 2) === 1;
            if ($t === 'num' && $v !== '' && is_numeric($v)) {
                $s = $zebra ? 8 : 7;
                $sheet .= '<c r="' . $ref . '" s="' . $s . '"><v>' . (float)$v . '</v></c>';
            } elseif ($t === 'date' && $v !== '') {
                $serial = xlsx_date_serial($v);
                if ($serial !== null) {
                    $s = $zebra ? 6 : 5;
                    $sheet .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $serial . '</v></c>';
                } else {
                    $s = $zebra ? 4 : 3;
                    $sheet .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t>' . xlsx_esc($v) . '</t></is></c>';
                }
            } elseif ($v !== '') {
                $s = $zebra ? 4 : 3;
                $sheet .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc($v) . '</t></is></c>';
            }
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData>';

    // Filtre automatique sur la plage en-tête + données
    $lastRow = 4 + count($cells);
    $sheet .= '<autoFilter ref="A4:' . $lastCol . $lastRow . '"/>';

    // Titres fusionnés
    $sheet .= '<mergeCells count="3">'
        . '<mergeCell ref="A1:' . $lastCol . '1"/>'
        . '<mergeCell ref="A2:' . $lastCol . '2"/>'
        . '<mergeCell ref="A3:' . $lastCol . '3"/>'
        . '</mergeCells>'
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';

    // ---- Styles ----
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="DD/MM/YYYY"/></numFmts>'
        . '<fonts count="5">'
        . '<font><sz val="11"/><color rgb="FF1E293B"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="15"/><color rgb="FF165B54"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><i/><sz val="9"/><color rgb="FF64748B"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="12"/><color rgb="FF1E293B"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF97316"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border>'
        . '<left style="thin"><color rgb="FFE2E8F0"/></left>'
        . '<right style="thin"><color rgb="FFE2E8F0"/></right>'
        . '<top style="thin"><color rgb="FFE2E8F0"/></top>'
        . '<bottom style="thin"><color rgb="FFE2E8F0"/></bottom>'
        . '<diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="11">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                    // 0
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                      // 1 titre
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="center" vertical="center"/></xf>'                                                           // 2 en-tête
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="left" vertical="center"/></xf>'                                                             // 3 texte
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="left" vertical="center"/></xf>'                                                             // 4 texte zébré
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="center" vertical="center"/></xf>'                                                           // 5 date
        . '<xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="center" vertical="center"/></xf>'                                                           // 6 date zébré
        . '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="right" vertical="center"/></xf>'                                                            // 7 numérique
        . '<xf numFmtId="2" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="right" vertical="center"/></xf>'                                                            // 8 numérique zébré
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                      // 9 méta
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                      // 10 sous-titre
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    // ---- Paquetage ----
    $parts = [
        '[Content_Types].xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Rapport" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml'          => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    ];

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    if ($tmp === false) {
        return '';
    }
    @unlink($tmp);
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return '';
    }
    foreach ($parts as $name => $xml) {
        $zip->addFromString($name, $xml);
    }
    $zip->close();
    $data = (string)file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

/**
 * Envoie le classeur au navigateur en téléchargement
 */
function rapport_xlsx_download(array $ds, array $meta, string $filename): void
{
    $data = rapport_xlsx_build($ds, $meta);
    if ($data === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Impossible de générer le fichier XLSX.";
        exit;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('X-Content-Type-Options: nosniff');
    echo $data;
    exit;
}
