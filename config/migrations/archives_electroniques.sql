-- Migration : Archivage électronique / GED
CREATE TABLE IF NOT EXISTS archives_electroniques (
    id SERIAL PRIMARY KEY,
    reference VARCHAR(50) UNIQUE,
    titre VARCHAR(255) NOT NULL,
    type_document VARCHAR(50) NOT NULL,
    annee_scolaire VARCHAR(20),
    date_document DATE,
    description TEXT,
    mots_cles VARCHAR(255),
    fichier_nom VARCHAR(255) NOT NULL,
    fichier_chemin VARCHAR(500) NOT NULL,
    fichier_type VARCHAR(50),
    fichier_taille INTEGER,
    vers_par INTEGER REFERENCES utilisateurs(id),
    date_versement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut VARCHAR(20) DEFAULT 'actif' CHECK (statut IN ('actif','supprime')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_archives_elec_type ON archives_electroniques(type_document);
CREATE INDEX IF NOT EXISTS idx_archives_elec_annee ON archives_electroniques(annee_scolaire);
CREATE INDEX IF NOT EXISTS idx_archives_elec_date ON archives_electroniques(date_document);
