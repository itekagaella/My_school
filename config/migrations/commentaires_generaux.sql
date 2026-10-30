-- Migration : Rendre les commentaires génériques (documents, élèves, paiements, incidents, etc.)
BEGIN;

ALTER TABLE commentaires ADD COLUMN IF NOT EXISTS contexte VARCHAR(30) DEFAULT 'document';
ALTER TABLE commentaires ADD COLUMN IF NOT EXISTS contexte_id INTEGER;
ALTER TABLE commentaires ALTER COLUMN document_id DROP NOT NULL;

-- Migrer les commentaires existants vers le nouveau format
UPDATE commentaires SET contexte = 'document', contexte_id = document_id WHERE contexte IS NULL OR contexte = 'document';

COMMIT;
