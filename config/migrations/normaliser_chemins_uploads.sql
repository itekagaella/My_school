-- ============================================================
-- Normalisation des chemins de fichiers téléversés
-- ------------------------------------------------------------
-- upload_file() renvoyait le chemin absolu du serveur
-- (ex. /var/www/My_School/assets/uploads/clubs/logo_ab12.png).
-- Les vues, elles, font file_exists(ROOT_PATH . $chemin) et
-- BASE_URL . $chemin : le préfixe était donc doublé et l'image
-- ne s'affichait jamais.
--
-- Ce script convertit les anciennes valeurs en chemin relatif
-- (ex. assets/uploads/clubs/logo_ab12.png).
-- Idempotent : relancer ne change rien aux chemins déjà relatifs.
-- ============================================================

UPDATE clubs
SET logo = 'assets/uploads/' || regexp_replace(logo, '^.*assets/uploads/', '')
WHERE logo IS NOT NULL AND logo LIKE '%assets/uploads/%';

UPDATE utilisateurs
SET avatar = 'assets/uploads/' || regexp_replace(avatar, '^.*assets/uploads/', '')
WHERE avatar IS NOT NULL AND avatar LIKE '%assets/uploads/%';

UPDATE documents
SET fichier_path = 'assets/uploads/' || regexp_replace(fichier_path, '^.*assets/uploads/', '')
WHERE fichier_path IS NOT NULL AND fichier_path LIKE '%assets/uploads/%';

UPDATE document_versions
SET fichier_path = 'assets/uploads/' || regexp_replace(fichier_path, '^.*assets/uploads/', '')
WHERE fichier_path IS NOT NULL AND fichier_path LIKE '%assets/uploads/%';
