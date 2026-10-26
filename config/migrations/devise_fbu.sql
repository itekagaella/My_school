-- ============================================================
-- My_School - Migration : devise FBu
-- ------------------------------------------------------------
-- Force la devise affichée à FBu (franc burundais) sur les
-- bases existantes, y compris celles qui avaient encore « FC »
-- ou aucune entrée `devise`.
--
-- Idempotente : peut être relancée sans risque.
-- ============================================================

INSERT INTO parametres_systeme (cle, valeur, description)
VALUES ('devise', 'FBu', 'Devise affichée (ex. FBu, FC, €)')
ON CONFLICT (cle) DO UPDATE
SET valeur = EXCLUDED.valeur,
    updated_at = NOW();
