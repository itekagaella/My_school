<?php
/**
 * Publication d'un créneau horaire avec vérification de conflits (procédure stockée)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
require_permission('horaires.publish');

$classe_id = (int)($_GET['classe_id'] ?? $_POST['classe_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Session expirée.');
    } else {
        $id = (int)($_POST['horaire_id'] ?? 0);
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        try {
            // Appel de la procédure stockée sp_publier_horaire qui vérifie les conflits
            $stmt = getDB()->prepare('SELECT sp_publier_horaire(:id) AS ok');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $r = $stmt->fetch();
            $ok = $r['ok'] ?? false;

            if ($ok) {
                log_activity('horaires.publish', 'Publication du créneau #' . $id);
                set_flash('success', 'Créneau publié.');
            } else {
                set_flash('error', 'Conflit détecté, publication impossible.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Conflit d\'horaire détecté : ' . $e->getMessage());
        }
    }
}

header('Location: index.php' . ($classe_id ? '?classe_id=' . $classe_id : ''));
exit;
