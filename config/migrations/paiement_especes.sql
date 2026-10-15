-- ============================================================
-- My_School - Migration : paiement en espèces uniquement
--   Supprime tous les modes de paiement sauf « espèces ».
-- Application : psql -U postgres -d my_school -f config/migrations/paiement_especes.sql
-- Idempotent : peut être relancé sans risque.
-- ============================================================

DO $$
BEGIN
    -- La table peut ne pas exister encore (elle est créée par init.sql ou par
    -- la migration personnel_roles.sql) : on ne fait rien dans ce cas.
    IF to_regclass('public.paiements') IS NULL THEN
        RETURN;
    END IF;

    -- Les éventuels paiements non-espèces repassent en espèces.
    UPDATE paiements SET mode_paiement = 'especes' WHERE mode_paiement <> 'especes';

    -- Seul « espèces » reste autorisé.
    ALTER TABLE paiements DROP CONSTRAINT IF EXISTS paiements_mode_paiement_check;
    ALTER TABLE paiements
        ADD CONSTRAINT paiements_mode_paiement_check CHECK (mode_paiement IN ('especes'));
END $$;
