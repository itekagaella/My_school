-- ============================================================
-- File d'attente des emails + préférences de notification
-- ============================================================

CREATE TABLE IF NOT EXISTS emails_file_attente (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER REFERENCES utilisateurs(id) ON DELETE SET NULL,
    destinataire    VARCHAR(255) NOT NULL,
    sujet           VARCHAR(255) NOT NULL,
    corps_texte     TEXT,
    corps_html      TEXT,
    categorie       VARCHAR(40) NOT NULL DEFAULT 'info',
    reference       VARCHAR(160) UNIQUE,
    statut          VARCHAR(20) NOT NULL DEFAULT 'en_attente'
                    CHECK (statut IN ('en_attente', 'en_cours', 'envoye', 'echec')),
    tentatives      INTEGER NOT NULL DEFAULT 0,
    derniere_erreur TEXT,
    cree_le         TIMESTAMP NOT NULL DEFAULT NOW(),
    envoye_le       TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_emails_file_attente_statut
    ON emails_file_attente (statut, cree_le);

CREATE INDEX IF NOT EXISTS idx_emails_file_attente_destinataire
    ON emails_file_attente (destinataire);

-- Les références contiennent parfois l'adresse email complète.
ALTER TABLE emails_file_attente ALTER COLUMN reference TYPE VARCHAR(255);

-- Préférences : une ligne absente = catégorie active.
CREATE TABLE IF NOT EXISTS preferences_email (
    user_id   INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    categorie VARCHAR(40) NOT NULL,
    actif     BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (user_id, categorie)
);
