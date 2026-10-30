-- Migration : Lier l'archivage électronique aux documents existants du système
ALTER TABLE archives_electroniques ADD COLUMN IF NOT EXISTS document_id INTEGER REFERENCES documents(id);
CREATE INDEX IF NOT EXISTS idx_archives_elec_doc ON archives_electroniques(document_id);
