<?php
/**
 * Rendu PDF des rapports (bibliothèque FPDF embarquée, aucune connexion requise)
 *
 * Mise en page : bandeau de marque, tableau paginé avec en-tête répété,
 * alignements selon le type de colonne, pied de page « Page x / y ».
 */
require_once __DIR__ . '/../assets/vendor/fpdf/fpdf.php';

class RapportPDF extends FPDF
{
    /** @var array */
    protected $meta = [];
    /** @var array<int, array{w: float, align: string}> */
    protected $cols = [];

    public function __construct(array $meta)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->meta = $meta;
        $this->AliasNbPages();
        $this->SetMargins(12, 12, 12);
        $this->SetAutoPageBreak(false, 0);
    }

    /** UTF-8 → CP1252 (encodage des polices standard de FPDF) */
    public function enc(string $s): string
    {
        $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        if (!is_string($out)) {
            $out = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $s);
        }
        return $out;
    }

    /** Tronque un texte pour tenir dans une largeur (CP1252 = octets simples) */
    public function clip(string $s, float $w): string
    {
        $t = $this->enc($s);
        if ($w <= 1.6) return '';
        if ($this->GetStringWidth($t) <= $w - 1.6) return $t;
        while ($t !== '' && $this->GetStringWidth($t . '...') > $w - 1.6) {
            $t = substr($t, 0, -1);
        }
        return $t . '...';
    }

    /** @param array<int, array{w: float, align: string}> $cols */
    public function setColumns(array $cols): void
    {
        $this->cols = $cols;
    }

    public function Header(): void
    {
        if ($this->page === 1) {
            // Bandeau de marque (orange #F97316)
            $this->SetFillColor(249, 115, 22);
            $this->Rect(0, 0, 210, 24, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetXY(12, 6);
            $this->SetFont('Arial', 'B', 17);
            $this->Cell(186, 8, $this->clip($this->meta['etablissement'] ?? '', 186), 0, 1, 'L');
            $this->SetX(12);
            $this->SetFont('Arial', '', 10.5);
            $this->Cell(186, 6, $this->clip($this->meta['titre'] ?? '', 186), 0, 1, 'L');
        } else {
            // Barre compacte (teal #165B54) sur les pages suivantes
            $this->SetFillColor(22, 91, 84);
            $this->Rect(0, 0, 210, 14, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetXY(12, 3.5);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(120, 7, $this->clip($this->meta['titre'] ?? '', 120), 0, 0, 'L');
            $this->SetXY(78, 3.5);
            $this->SetFont('Arial', '', 8.5);
            $this->Cell(120, 7, $this->clip($this->meta['etablissement'] ?? '', 120), 0, 0, 'R');
        }

        // Ligne de méta-données + filet
        $this->SetTextColor(71, 85, 105);
        $this->SetFont('Arial', '', 8.5);
        $this->SetXY(12, 27);
        $this->Cell(186, 5, $this->clip($this->meta['ligne'] ?? '', 186), 0, 1, 'L');
        $this->SetDrawColor(226, 232, 240);
        $this->Line(12, 34, 198, 34);
        $this->SetTextColor(30, 41, 59);
        $this->SetY(40);
    }

    public function Footer(): void
    {
        $this->SetY(-13);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->SetY(-11.5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(140, 5, $this->clip($this->meta['pied'] ?? '', 140), 0, 0, 'L');
        $this->Cell(46, 5, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'R');
        $this->SetTextColor(30, 41, 59);
    }

    /** En-tête de tableau (teal, blanc gras) — redessiné à chaque page */
    public function tableHead(array $headers): void
    {
        $x = 12;
        $y = $this->GetY();
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetFillColor(22, 91, 84);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(203, 213, 225);
        foreach ($headers as $i => $h) {
            $w = $this->cols[$i]['w'] ?? 30;
            $this->SetXY($x, $y);
            $this->Cell($w, 7, $this->clip((string)$h, $w), 1, 0, 'C', true);
            $x += $w;
        }
        $this->SetTextColor(30, 41, 59);
        $this->SetY($y + 7);
    }
}

/**
 * Construit le PDF complet d'un jeu de données (retourne l'objet FPDF)
 *
 * @param array $ds  dataset (titre, headers, types, rows)
 * @param array $meta rapport_meta()
 */
function rapport_pdf_build(array $ds, array $meta): RapportPDF
{
    $pdf = new RapportPDF($meta);
    $headers = $ds['headers'];
    $types = $ds['types'];
    $rows = $ds['rows'];
    $nbCols = count($headers);

    // Valeurs d'affichage (mêmes formats que l'aperçu et le XLSX)
    $cells = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as $i => $v) {
            $line[] = rapport_format_value($types[$i] ?? 'text', $v);
        }
        $cells[] = $line;
    }

    // Largeurs de colonnes : plus grand contenu, plafonné puis mis à l'échelle
    $max = array_fill(0, max(1, $nbCols), 0.0);
    $pdf->SetFont('Arial', 'B', 8.5);
    foreach ($headers as $i => $h) {
        $max[$i] = $pdf->GetStringWidth($pdf->enc((string)$h)) + 3;
    }
    $pdf->SetFont('Arial', '', 8);
    foreach ($cells as $line) {
        foreach ($line as $i => $v) {
            $w = $pdf->GetStringWidth($pdf->enc($v)) + 3;
            if ($w > ($max[$i] ?? 0)) $max[$i] = $w;
        }
    }
    $max = array_map(fn($w) => min(max($w, 16), 70), $max);
    $sum = max(1.0, array_sum($max));
    $scale = 186 / $sum;

    $cols = [];
    foreach ($headers as $i => $h) {
        $t = $types[$i] ?? 'text';
        $cols[$i] = [
            'w'     => $max[$i] * $scale,
            'align' => $t === 'num' ? 'R' : ($t === 'date' ? 'C' : 'L'),
        ];
    }
    $pdf->setColumns($cols);

    $pdf->AddPage();
    $pdf->tableHead($headers);

    if (!$cells) {
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(186, 14, $pdf->enc('Aucune donnée pour ce rapport.'), 1, 1, 'C', true);
        $pdf->SetTextColor(30, 41, 59);
        return $pdf;
    }

    $rowH = 6.4;
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetDrawColor(226, 232, 240);
    foreach ($cells as $idx => $line) {
        if ($pdf->GetY() + $rowH > 276) {
            $pdf->AddPage();
            $pdf->tableHead($headers);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetDrawColor(226, 232, 240);
        }
        $fill = ($idx % 2) === 1;
        if ($fill) $pdf->SetFillColor(248, 250, 252);
        $pdf->SetTextColor(30, 41, 59);
        $x = 12;
        $y = $pdf->GetY();
        foreach ($line as $i => $v) {
            $c = $cols[$i];
            $pdf->SetXY($x, $y);
            $pdf->Cell($c['w'], $rowH, $pdf->clip($v, $c['w']), 1, 0, $c['align'], $fill);
            $x += $c['w'];
        }
        $pdf->SetY($y + $rowH);
    }

    return $pdf;
}
