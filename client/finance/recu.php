<?php
/**
 * Reçu de paiement (imprimable) — Admin
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['finance']);
require_permission('finance.view');

$id = (int)get('id', 0);
$p = prepareQuery(
    "SELECT pa.*, e.nom, e.prenom, e.matricule, " . classe_label_sql('c') . " AS nom_classe, f.libelle AS frais_libelle,
            u.prenom AS agent_prenom, u.nom AS agent_nom
     FROM paiements pa
     JOIN eleves e ON e.id = pa.eleve_id
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN frais_scolaires f ON f.id = pa.frais_id
     LEFT JOIN utilisateurs u ON u.id = pa.enregistre_par
     WHERE pa.id = :id",
    ['id' => $id]
)->fetch();

if (!$p) {
    set_flash('error', 'Paiement introuvable.');
    header('Location: paiements.php');
    exit;
}

$modes = modes_paiement();
$etab = get_param('nom_etablissement', 'Mon École');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu <?= e($p['recu_numero']) ?> - My_School</title>
    <link href="<?= BASE_URL ?>assets/vendor/fonts/googlefonts.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .recu { max-width: 720px; margin: 30px auto; background: #fff; padding: 36px; border-radius: 14px; box-shadow: 0 8px 30px rgba(16,24,40,.08); }
        .recu-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #f97316; padding-bottom: 16px; }
        .recu-title { font-size: 24px; font-weight: 800; margin: 0; }
        @media print { body { background: #fff; } .recu { box-shadow: none; margin: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="recu">
    <div class="recu-head">
        <div>
            <h1 class="recu-title"><?= e($etab) ?></h1>
            <div class="text-muted">Reçu de paiement</div>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-5"><?= e($p['recu_numero']) ?></div>
            <div class="text-muted small">Date : <?= date('d/m/Y', strtotime($p['date_paiement'])) ?></div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-6">
            <h6 class="text-muted text-uppercase small">Élève</h6>
            <div class="fw-semibold fs-5"><?= e($p['prenom'] . ' ' . $p['nom']) ?></div>
            <div class="text-muted small">Matricule : <?= e($p['matricule']) ?></div>
            <?php if (!empty($p['nom_classe'])): ?><div class="text-muted small">Classe : <?= e($p['nom_classe']) ?></div><?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <h6 class="text-muted text-uppercase small">Montant reçu</h6>
            <div class="fw-bold fs-3 text-success"><?= e(format_montant($p['montant'])) ?></div>
        </div>
    </div>

    <table class="table table-bordered mt-4">
        <tbody>
            <tr><th style="width:220px;">Objet / Frais</th><td><?= e($p['frais_libelle'] ?? 'Paiement général') ?></td></tr>
            <tr><th>Période</th><td><?= e($p['periode'] ?: '-') ?></td></tr>
            <tr><th>Mode de paiement</th><td><?= e($modes[$p['mode_paiement']] ?? $p['mode_paiement']) ?></td></tr>
            <tr><th>Référence</th><td><?= e($p['reference'] ?: '-') ?></td></tr>
            <?php if (!empty($p['observation'])): ?><tr><th>Observation</th><td><?= e($p['observation']) ?></td></tr><?php endif; ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-between mt-5">
        <div class="text-muted small">Enregistré par : <?= e(trim(($p['agent_prenom'] ?? '') . ' ' . ($p['agent_nom'] ?? ''))) ?: '—' ?></div>
        <div class="text-center">
            <div class="mb-5 text-muted small">Cachet & signature</div>
            <div class="border-top" style="width:200px;"></div>
        </div>
    </div>

    <div class="text-center mt-4 no-print">
        <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimer</button>
        <a href="paiements.php" class="btn btn-secondary">Retour</a>
    </div>
</div>
</body>
</html>
