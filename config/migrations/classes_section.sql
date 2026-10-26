-- ============================================================
-- My_School - Migration : section des classes
-- ------------------------------------------------------------
-- Le libellé affiché d'une classe inclut désormais sa section
-- (ex. « 1ère année — Sciences »).
--
-- Cette migration garantit la présence de la colonne `section`
-- sur les bases existantes, quelle que soit leur version.
-- Idempotente : peut être relancée sans risque.
-- ============================================================

ALTER TABLE classes ADD COLUMN IF NOT EXISTS section VARCHAR(50);

-- Index utile pour les listes et les regroupements par classe.
CREATE INDEX IF NOT EXISTS idx_classes_nom_section ON classes (nom_classe, section);
