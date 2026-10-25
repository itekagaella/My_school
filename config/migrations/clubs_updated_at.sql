-- ============================================================
-- Colonne clubs.updated_at manquante
-- ------------------------------------------------------------
-- admin/clubs/edit.php exécute :
--   UPDATE clubs SET ..., updated_at=NOW() WHERE id=:id
-- Or la colonne n'existait pas : toute modification d'un club
-- échouait avec « SQLSTATE[42703]: Undefined column:
-- column "updated_at" of relation "clubs" does not exist ».
-- ============================================================

ALTER TABLE clubs ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW();
