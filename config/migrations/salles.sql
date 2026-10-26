-- ============================================================
-- My_School - Migration : salles de classe et salles administratives
-- ------------------------------------------------------------
-- - Crée la table `salles`.
-- - Ajoute une salle numérotée (Salle 1, Salle 2, …) pour chaque
--   classe existante.
-- - Ajoute les salles administratives de base.
-- - Élargit `horaires.salle` pour accueillir les libellés longs.
--
-- Idempotente : peut être relancée sans risque.
-- ============================================================

-- ---------- Table des salles ----------
CREATE TABLE IF NOT EXISTS salles (
    id         SERIAL PRIMARY KEY,
    numero     INT,                                   -- numéro des salles de classe (1, 2, 3, …)
    nom        VARCHAR(80) NOT NULL UNIQUE,           -- « Salle 1 », « Direction », …
    type       VARCHAR(20) NOT NULL DEFAULT 'classe'
               CHECK (type IN ('classe', 'administratif')),
    classe_id  INT REFERENCES classes(id) ON DELETE CASCADE,
    actif      BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_salles_numero
    ON salles (numero) WHERE numero IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_salles_classe
    ON salles (classe_id) WHERE classe_id IS NOT NULL;

-- La salle d'une classe est supprimée avec la classe (bases déjà migrées).
ALTER TABLE salles DROP CONSTRAINT IF EXISTS salles_classe_id_fkey;
ALTER TABLE salles ADD CONSTRAINT salles_classe_id_fkey
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE;

-- ---------- Colonne horaires.salle : libellés plus longs ----------
ALTER TABLE horaires ALTER COLUMN salle TYPE VARCHAR(80);

-- ---------- Création automatique de la salle d'une classe ----------
CREATE OR REPLACE FUNCTION sp_creer_salle_classe(p_classe_id INT)
RETURNS INT AS $$
DECLARE
    v_numero INT;
    v_id     INT;
BEGIN
    -- Une salle existe déjà pour cette classe : on la renvoie.
    SELECT id INTO v_id
    FROM salles
    WHERE classe_id = p_classe_id AND type = 'classe'
    LIMIT 1;

    IF v_id IS NOT NULL THEN
        RETURN v_id;
    END IF;

    -- Numérotation séquentielle des salles de classe : 1, 2, 3, …
    SELECT COALESCE(MAX(numero), 0) + 1 INTO v_numero
    FROM salles
    WHERE type = 'classe';

    INSERT INTO salles (numero, nom, type, classe_id)
    VALUES (v_numero, 'Salle ' || v_numero, 'classe', p_classe_id)
    RETURNING id INTO v_id;

    RETURN v_id;
END;
$$ LANGUAGE plpgsql;

-- ---------- Salles administratives ----------
INSERT INTO salles (numero, nom, type, actif) VALUES
    (NULL, 'Direction',              'administratif', TRUE),
    (NULL, 'Secrétariat',            'administratif', TRUE),
    (NULL, 'Économat',               'administratif', TRUE),
    (NULL, 'Salle des professeurs',  'administratif', TRUE),
    (NULL, 'Bibliothèque',           'administratif', TRUE),
    (NULL, 'Salle de réunion',       'administratif', TRUE),
    (NULL, 'Infirmerie',             'administratif', TRUE),
    (NULL, 'Salle informatique',     'administratif', TRUE)
ON CONFLICT (nom) DO NOTHING;

-- ---------- Une salle pour chaque classe existante ----------
DO $$
DECLARE
    r RECORD;
BEGIN
    FOR r IN
        SELECT id
        FROM classes
        ORDER BY CASE niveau
                     WHEN 'Enseignement fondamental (cycle 4)' THEN 1
                     WHEN 'Enseignement post-fondamental' THEN 2
                     ELSE 99
                 END,
                 nom_classe,
                 section,
                 id
    LOOP
        PERFORM sp_creer_salle_classe(r.id);
    END LOOP;
END $$;

-- ---------- Affecter la salle de classe aux créneaux sans salle ----------
UPDATE horaires h
SET salle = s.nom
FROM salles s
WHERE s.classe_id = h.classe_id
  AND s.type = 'classe'
  AND (h.salle IS NULL OR btrim(h.salle) = '');
