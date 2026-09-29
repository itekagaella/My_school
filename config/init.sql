-- ============================================================
-- My_School - Schéma de la Base de Données PostgreSQL
-- Système de Gestion Scolaire
-- ============================================================

-- Requiert PostgreSQL 12+ (JSONB, procédures stockées PL/pgSQL)

-- Base de données : my_school
-- CREATE DATABASE my_school;

-- ============================================================
-- EXTENSIONS
-- ============================================================
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ============================================================
-- TABLES
-- ============================================================

-- ---------- AUTHENTIFICATION & SÉCURITÉ ----------

-- Rôles du système
CREATE TABLE IF NOT EXISTS roles (
    id          SERIAL PRIMARY KEY,
    nom_role    VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- Permissions du système
CREATE TABLE IF NOT EXISTS permissions (
    id            SERIAL PRIMARY KEY,
    nom_permission VARCHAR(100) NOT NULL UNIQUE,
    description   TEXT,
    created_at    TIMESTAMP DEFAULT NOW()
);

-- Association rôles - permissions
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

-- Utilisateurs principaux
CREATE TABLE IF NOT EXISTS utilisateurs (
    id                  SERIAL PRIMARY KEY,
    nom                 VARCHAR(100) NOT NULL,
    prenom              VARCHAR(100) NOT NULL,
    email               VARCHAR(150) NOT NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    role                VARCHAR(20) NOT NULL CHECK (role IN ('admin','eleve','prof','personnel')),
    avatar              VARCHAR(255),
    actif               BOOLEAN DEFAULT TRUE,
    deux_facteurs       BOOLEAN DEFAULT FALSE,
    dernier_code_otp    VARCHAR(10),
    dernier_code_exp    TIMESTAMP,
    derniere_connexion TIMESTAMP,
    tentatives_connexion INT DEFAULT 0,
    compte_verrouille   BOOLEAN DEFAULT FALSE,
    verrou_date         TIMESTAMP,
    created_at          TIMESTAMP DEFAULT NOW(),
    updated_at          TIMESTAMP DEFAULT NOW()
);

-- Sessions actives
CREATE TABLE IF NOT EXISTS sessions_utilisateur (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    token      VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Historique des connexions
CREATE TABLE IF NOT EXISTS historique_connexions (
    id            SERIAL PRIMARY KEY,
    user_id       INT REFERENCES utilisateurs(id) ON DELETE CASCADE,
    ip_address    VARCHAR(45) NOT NULL,
    user_agent    TEXT,
    date_connexion TIMESTAMP DEFAULT NOW(),
    succes        BOOLEAN NOT NULL,
    raison        VARCHAR(255)
);

-- Journal des activités
CREATE TABLE IF NOT EXISTS journal_activites (
    id          SERIAL PRIMARY KEY,
    user_id     INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45),
    date_action TIMESTAMP DEFAULT NOW()
);

-- Tokens de réinitialisation de mot de passe
CREATE TABLE IF NOT EXISTS password_resets (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    token      VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used       BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ---------- GESTION DES DONNÉES ----------

-- Classes
CREATE TABLE IF NOT EXISTS classes (
    id            SERIAL PRIMARY KEY,
    nom_classe    VARCHAR(50) NOT NULL,
    niveau        VARCHAR(50) NOT NULL,
    section       VARCHAR(50),
    annee_scolaire VARCHAR(9) NOT NULL,
    capacite      INT DEFAULT 50,
    created_at    TIMESTAMP DEFAULT NOW()
);

-- Élèves
CREATE TABLE IF NOT EXISTS eleves (
    id            SERIAL PRIMARY KEY,
    user_id       INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    matricule     VARCHAR(20) NOT NULL UNIQUE,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    date_naissance DATE NOT NULL,
    sexe          VARCHAR(1) NOT NULL CHECK (sexe IN ('M','F')),
    classe_id     INT REFERENCES classes(id) ON DELETE SET NULL,
    parent_nom    VARCHAR(200),
    parent_tel    VARCHAR(20),
    parent_email  VARCHAR(150),
    adresse       TEXT,
    photo         VARCHAR(255),
    statut        VARCHAR(20) DEFAULT 'actif' CHECK (statut IN ('actif','inactif','archive')),
    created_at    TIMESTAMP DEFAULT NOW(),
    updated_at    TIMESTAMP DEFAULT NOW()
);

-- Professeurs
CREATE TABLE IF NOT EXISTS profs (
    id            SERIAL PRIMARY KEY,
    user_id       INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    matricule     VARCHAR(20) NOT NULL UNIQUE,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    specialite    VARCHAR(100),
    tel           VARCHAR(20),
    email         VARCHAR(150),
    adresse       TEXT,
    photo         VARCHAR(255),
    statut        VARCHAR(20) DEFAULT 'actif' CHECK (statut IN ('actif','inactif','archive')),
    created_at    TIMESTAMP DEFAULT NOW(),
    updated_at    TIMESTAMP DEFAULT NOW()
);

-- Personnel
CREATE TABLE IF NOT EXISTS personnel (
    id            SERIAL PRIMARY KEY,
    user_id       INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    matricule     VARCHAR(20) NOT NULL UNIQUE,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    fonction      VARCHAR(100),
    tel           VARCHAR(20),
    email         VARCHAR(150),
    statut        VARCHAR(20) DEFAULT 'actif' CHECK (statut IN ('actif','inactif','archive')),
    created_at    TIMESTAMP DEFAULT NOW()
);

-- Matières
CREATE TABLE IF NOT EXISTS matieres (
    id          SERIAL PRIMARY KEY,
    nom_matiere VARCHAR(100) NOT NULL,
    code        VARCHAR(10) NOT NULL UNIQUE,
    coefficient DECIMAL(3,1) DEFAULT 1,
    classe_id   INT REFERENCES classes(id) ON DELETE CASCADE,
    prof_id     INT REFERENCES profs(id) ON DELETE SET NULL,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- Horaires
CREATE TABLE IF NOT EXISTS horaires (
    id            SERIAL PRIMARY KEY,
    classe_id     INT NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    matiere_id    INT NOT NULL REFERENCES matieres(id) ON DELETE CASCADE,
    prof_id       INT REFERENCES profs(id) ON DELETE CASCADE,
    jour          VARCHAR(10) NOT NULL CHECK (jour IN ('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi')),
    heure_debut   TIME NOT NULL,
    heure_fin     TIME NOT NULL,
    salle         VARCHAR(20),
    statut        VARCHAR(20) DEFAULT 'brouillon' CHECK (statut IN ('brouillon','publie','annule')),
    annee_scolaire VARCHAR(9),
    created_at    TIMESTAMP DEFAULT NOW(),
    updated_at    TIMESTAMP DEFAULT NOW()
);

-- Notes
CREATE TABLE IF NOT EXISTS notes (
    id             SERIAL PRIMARY KEY,
    eleve_id       INT NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    matiere_id     INT NOT NULL REFERENCES matieres(id) ON DELETE CASCADE,
    prof_id        INT REFERENCES profs(id) ON DELETE SET NULL,
    note           DECIMAL(4,2) NOT NULL,
    type_evaluation VARCHAR(20) NOT NULL CHECK (type_evaluation IN ('devoir','composition','interrogation','trimestriel')),
    date_evaluation DATE NOT NULL,
    appreciation   TEXT,
    created_at     TIMESTAMP DEFAULT NOW(),
    UNIQUE (eleve_id, matiere_id, type_evaluation, date_evaluation)
);

-- Présences
CREATE TABLE IF NOT EXISTS presences (
    id            SERIAL PRIMARY KEY,
    eleve_id      INT NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    prof_id       INT REFERENCES profs(id) ON DELETE SET NULL,
    date_presence DATE NOT NULL,
    heure_arrivee TIME,
    statut        VARCHAR(20) NOT NULL CHECK (statut IN ('present','absent','retard','excuse')),
    motif         TEXT,
    created_at    TIMESTAMP DEFAULT NOW(),
    UNIQUE (eleve_id, date_presence)
);

-- ---------- GESTION DOCUMENTAIRE ----------

-- Documents
CREATE TABLE IF NOT EXISTS documents (
    id           SERIAL PRIMARY KEY,
    nom_fichier  VARCHAR(255) NOT NULL,
    fichier_path VARCHAR(500) NOT NULL,
    type_fichier VARCHAR(50),
    taille       BIGINT,
    auteur_id    INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    description  TEXT,
    statut       VARCHAR(20) DEFAULT 'actif' CHECK (statut IN ('actif','archive')),
    created_at   TIMESTAMP DEFAULT NOW()
);

-- Versions des documents
CREATE TABLE IF NOT EXISTS document_versions (
    id          SERIAL PRIMARY KEY,
    document_id INT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    version     INT NOT NULL,
    fichier_path VARCHAR(500) NOT NULL,
    taille      BIGINT,
    modifie_par INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    date_version TIMESTAMP DEFAULT NOW(),
    UNIQUE (document_id, version)
);

-- Partage des documents
CREATE TABLE IF NOT EXISTS document_partages (
    id           SERIAL PRIMARY KEY,
    document_id  INT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    user_id      INT NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    date_partage TIMESTAMP DEFAULT NOW(),
    UNIQUE (document_id, user_id)
);

-- Commentaires
CREATE TABLE IF NOT EXISTS commentaires (
    id           SERIAL PRIMARY KEY,
    document_id  INT REFERENCES documents(id) ON DELETE CASCADE,
    user_id      INT NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    contenu      TEXT NOT NULL,
    created_at   TIMESTAMP DEFAULT NOW()
);

-- ---------- COMMUNICATION ----------

-- Communications
CREATE TABLE IF NOT EXISTS communications (
    id              SERIAL PRIMARY KEY,
    titre           VARCHAR(200) NOT NULL,
    contenu         TEXT NOT NULL,
    auteur_id       INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    destinataires   TEXT NOT NULL, -- 'tous', 'role:eleve,role:prof', 'classe:1,classe:2'
    type            VARCHAR(20) DEFAULT 'info' CHECK (type IN ('annonce','alerte','info')),
    date_publication TIMESTAMP DEFAULT NOW(),
    statut          VARCHAR(20) DEFAULT 'publie' CHECK (statut IN ('brouillon','publie','archive')),
    updated_at      TIMESTAMP DEFAULT NOW()
);

-- Notifications internes
CREATE TABLE IF NOT EXISTS notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    titre      VARCHAR(200) NOT NULL,
    message    TEXT,
    type       VARCHAR(20) DEFAULT 'info' CHECK (type IN ('info','alerte','succes','erreur')),
    lu         BOOLEAN DEFAULT FALSE,
    lien       VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Messages de chat
CREATE TABLE IF NOT EXISTS messages_chat (
    id          SERIAL PRIMARY KEY,
    sender_id   INT NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    receiver_id INT REFERENCES utilisateurs(id) ON DELETE CASCADE, -- NULL = message général
    message     TEXT NOT NULL,
    lu          BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- ---------- CLUBS SCOLAIRES ----------

CREATE TABLE IF NOT EXISTS clubs (
    id                SERIAL PRIMARY KEY,
    nom_club          VARCHAR(100) NOT NULL,
    description       TEXT,
    prof_responsable_id INT REFERENCES profs(id) ON DELETE SET NULL,
    logo              VARCHAR(255),
    date_creation     DATE DEFAULT CURRENT_DATE,
    statut            VARCHAR(20) DEFAULT 'actif' CHECK (statut IN ('actif','inactif')),
    created_at        TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS club_membres (
    id               SERIAL PRIMARY KEY,
    club_id          INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
    eleve_id         INT NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    date_inscription TIMESTAMP DEFAULT NOW(),
    UNIQUE (club_id, eleve_id)
);

-- ---------- ADMINISTRATION ----------

-- Paramètres système
CREATE TABLE IF NOT EXISTS parametres_systeme (
    id          SERIAL PRIMARY KEY,
    cle         VARCHAR(100) NOT NULL UNIQUE,
    valeur      TEXT,
    description TEXT,
    updated_at  TIMESTAMP DEFAULT NOW()
);

-- Thèmes
CREATE TABLE IF NOT EXISTS themes (
    id        SERIAL PRIMARY KEY,
    nom_theme VARCHAR(50) NOT NULL,
    css_path  VARCHAR(255),
    actif     BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Sauvegardes
CREATE TABLE IF NOT EXISTS sauvegardes (
    id            SERIAL PRIMARY KEY,
    nom_fichier   VARCHAR(255) NOT NULL,
    chemin        VARCHAR(500),
    taille        BIGINT,
    type          VARCHAR(20) DEFAULT 'manuel' CHECK (type IN ('auto','manuel')),
    date_sauvegarde TIMESTAMP DEFAULT NOW(),
    effectuee_par INT REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- Archives
CREATE TABLE IF NOT EXISTS archives (
    id           SERIAL PRIMARY KEY,
    table_source VARCHAR(50) NOT NULL,
    record_id    INT NOT NULL,
    donnees_json JSONB,
    date_archive TIMESTAMP DEFAULT NOW(),
    archive_par  INT REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ============================================================
-- INDEX
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_users_email ON utilisateurs(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON utilisateurs(role);
CREATE INDEX IF NOT EXISTS idx_users_verrou ON utilisateurs(compte_verrouille);

CREATE INDEX IF NOT EXISTS idx_eleves_classe ON eleves(classe_id);
CREATE INDEX IF NOT EXISTS idx_eleves_matricule ON eleves(matricule);
CREATE INDEX IF NOT EXISTS idx_eleves_nom ON eleves(nom, prenom);

CREATE INDEX IF NOT EXISTS idx_profs_nom ON profs(nom, prenom);

CREATE INDEX IF NOT EXISTS idx_notes_eleve ON notes(eleve_id);
CREATE INDEX IF NOT EXISTS idx_notes_matiere ON notes(matiere_id);
CREATE INDEX IF NOT EXISTS idx_notes_date ON notes(date_evaluation);

CREATE INDEX IF NOT EXISTS idx_presences_eleve ON presences(eleve_id);
CREATE INDEX IF NOT EXISTS idx_presences_date ON presences(date_presence);

CREATE INDEX IF NOT EXISTS idx_horaires_classe_jour ON horaires(classe_id, jour);
CREATE INDEX IF NOT EXISTS idx_horaires_prof ON horaires(prof_id);

CREATE INDEX IF NOT EXISTS idx_matieres_classe ON matieres(classe_id);

CREATE INDEX IF NOT EXISTS idx_histo_user_date ON historique_connexions(user_id, date_connexion);
CREATE INDEX IF NOT EXISTS idx_journal_user_date ON journal_activites(user_id, date_action);
CREATE INDEX IF NOT EXISTS idx_journal_date ON journal_activites(date_action);

CREATE INDEX IF NOT EXISTS idx_notif_user_lu ON notifications(user_id, lu);
CREATE INDEX IF NOT EXISTS idx_chat_sender ON messages_chat(sender_id);
CREATE INDEX IF NOT EXISTS idx_chat_receiver ON messages_chat(receiver_id);

CREATE INDEX IF NOT EXISTS idx_docs_auteur ON documents(auteur_id);
CREATE INDEX IF NOT EXISTS idx_comm_dest ON communications(date_publication);
CREATE INDEX IF NOT EXISTS idx_archives_source ON archives(table_source, record_id);

-- ============================================================
-- PROCÉDURES STOCKÉES
-- ============================================================

-- ---------- Inscription d'un élève (transaction + génération matricule) ----------
CREATE OR REPLACE FUNCTION sp_inscrire_eleve(
    p_nom          VARCHAR,
    p_prenom       VARCHAR,
    p_date_naissance DATE,
    p_sexe         VARCHAR,
    p_classe_id    INT,
    p_parent_nom   VARCHAR,
    p_parent_tel   VARCHAR,
    p_parent_email VARCHAR,
    p_adresse      TEXT,
    p_email_compte VARCHAR,
    p_mot_de_passe VARCHAR
) RETURNS INT AS $$
DECLARE
    v_user_id INT;
    v_eleve_id INT;
    v_matricule VARCHAR(20);
    v_annee INT;
    v_seq INT;
BEGIN
    -- Vérifier que la classe existe
    IF NOT EXISTS (SELECT 1 FROM classes WHERE id = p_classe_id) THEN
        RAISE EXCEPTION 'Classe inexistante';
    END IF;

    -- Générer matricule: ANNEE + séquence (corrigé: position correcte après 'ELV-YYYY-')
    SELECT EXTRACT(YEAR FROM CURRENT_DATE)::INT INTO v_annee;
    SELECT COALESCE(MAX(CAST(SUBSTRING(matricule FROM 10) AS INT)), 0) + 1
    INTO v_seq FROM eleves;
    v_matricule := 'ELV-' || v_annee::TEXT || '-' || LPAD(v_seq::TEXT, 4, '0');

    -- Transaction atomique
    BEGIN
        -- Créer le compte utilisateur (hash du mot de passe en PHP passe par la couche)
        INSERT INTO utilisateurs (nom, prenom, email, password_hash, role)
        VALUES (p_nom, p_prenom, p_email_compte, p_mot_de_passe, 'eleve')
        RETURNING id INTO v_user_id;

        -- Créer l'élève
        INSERT INTO eleves (user_id, matricule, nom, prenom, date_naissance, sexe,
                            classe_id, parent_nom, parent_tel, parent_email, adresse)
        VALUES (v_user_id, v_matricule, p_nom, p_prenom, p_date_naissance, p_sexe,
                p_classe_id, p_parent_nom, p_parent_tel, p_parent_email, p_adresse)
        RETURNING id INTO v_eleve_id;

        RETURN v_eleve_id;
    EXCEPTION
        WHEN OTHERS THEN
            RAISE;
    END;
END;
$$ LANGUAGE plpgsql;

-- ---------- Saisie des notes avec validation ----------
CREATE OR REPLACE FUNCTION sp_saisir_notes(
    p_eleve_id     INT,
    p_matiere_id   INT,
    p_prof_id      INT,
    p_note         NUMERIC,
    p_type         VARCHAR,
    p_date_eval    DATE,
    p_appreciation TEXT
) RETURNS INT AS $$
DECLARE
    v_note_id INT;
BEGIN
    -- Validation : note entre 0 et 20
    IF p_note < 0 OR p_note > 20 THEN
        RAISE EXCEPTION 'Note hors limites (0-20) : %', p_note;
    END IF;

    -- Vérifier que l'élève appartient à la même classe que la matière
    IF NOT EXISTS (
        SELECT 1 FROM eleves e
        JOIN matieres m ON m.classe_id = e.classe_id
        WHERE e.id = p_eleve_id AND m.id = p_matiere_id
    ) THEN
        RAISE EXCEPTION 'Incompatibilité élève/matière';
    END IF;

    INSERT INTO notes (eleve_id, matiere_id, prof_id, note, type_evaluation,
                       date_evaluation, appreciation)
    VALUES (p_eleve_id, p_matiere_id, p_prof_id, p_note, p_type, p_date_eval, p_appreciation)
    ON CONFLICT (eleve_id, matiere_id, type_evaluation, date_evaluation)
    DO UPDATE SET note = EXCLUDED.note, appreciation = EXCLUDED.appreciation
    RETURNING id INTO v_note_id;

    RETURN v_note_id;
END;
$$ LANGUAGE plpgsql;

-- ---------- Calcul de moyenne d'un élève (avec coefficients) ----------
CREATE OR REPLACE FUNCTION sp_calculer_moyenne(
    p_eleve_id INT,
    p_periode  DATE DEFAULT NULL
) RETURNS NUMERIC AS $$
DECLARE
    v_total_note NUMERIC := 0;
    v_total_coef NUMERIC := 0;
    v_moyenne NUMERIC;
BEGIN
    SELECT
        SUM(
            CASE
                WHEN n.type_evaluation = 'composition' THEN n.note * 2 * m.coefficient
                ELSE n.note * m.coefficient
            END
        ) / NULLIF(
            SUM(
                CASE
                    WHEN n.type_evaluation = 'composition' THEN 2 * m.coefficient
                    ELSE m.coefficient
                END
            ), 0
        )
    INTO v_moyenne
    FROM notes n
    JOIN matieres m ON m.id = n.matiere_id
    WHERE n.eleve_id = p_eleve_id
      AND (p_periode IS NULL OR n.date_evaluation <= p_periode);

    RETURN COALESCE(v_moyenne, 0);
END;
$$ LANGUAGE plpgsql;

-- ---------- Marquer présence avec déclencheur d'alerte si absences >= seuil ----------
CREATE OR REPLACE FUNCTION sp_marquer_presence(
    p_eleve_id   INT,
    p_prof_id    INT,
    p_date       DATE,
    p_statut     VARCHAR,
    p_motif      TEXT
) RETURNS INT AS $$
DECLARE
    v_presence_id INT;
    v_abs_count INT;
    v_user_id INT;
BEGIN
    INSERT INTO presences (eleve_id, prof_id, date_presence, statut, motif)
    VALUES (p_eleve_id, p_prof_id, p_date, p_statut, p_motif)
    ON CONFLICT (eleve_id, date_presence)
    DO UPDATE SET statut = EXCLUDED.statut, motif = EXCLUDED.motif
    RETURNING id INTO v_presence_id;

    -- Compter les absences (30 dernières journées, hors excuses)
    SELECT COUNT(*) INTO v_abs_count
    FROM presences
    WHERE eleve_id = p_eleve_id
      AND statut = 'absent'
      AND date_presence > CURRENT_DATE - 30;

    -- Générer une alerte si ≥ 3 absences non excusées
    IF v_abs_count >= 3 THEN
        SELECT user_id INTO v_user_id FROM eleves WHERE id = p_eleve_id;
        IF v_user_id IS NOT NULL THEN
            INSERT INTO notifications (user_id, titre, message, type)
            VALUES (v_user_id,
                    'Alerte d''absences',
                    'Vous comptez ' || v_abs_count || ' absences sur les 30 derniers jours.',
                    'alerte');
        END IF;
    END IF;

    RETURN v_presence_id;
END;
$$ LANGUAGE plpgsql;

-- ---------- Publication d'un horaire avec vérification de conflits ----------
CREATE OR REPLACE FUNCTION sp_publier_horaire(
    p_horaire_id INT
) RETURNS BOOLEAN AS $$
DECLARE
    v_conflit INT;
    v_classe_id INT;
    v_prof_id INT;
    v_jour VARCHAR(10);
    v_debut TIME;
    v_fin TIME;
    v_salle VARCHAR(20);
BEGIN
    SELECT classe_id, prof_id, jour, heure_debut, heure_fin, salle
    INTO v_classe_id, v_prof_id, v_jour, v_debut, v_fin, v_salle
    FROM horaires WHERE id = p_horaire_id;

    IF v_classe_id IS NULL THEN
        RAISE EXCEPTION 'Horaire introuvable';
    END IF;

    -- Vérifier conflit de salle/classe/prof (chevauchement des plages horaires)
    SELECT COUNT(*) INTO v_conflit
    FROM horaires
    WHERE id <> p_horaire_id
      AND jour = v_jour
      AND statut = 'publie'
      AND heure_debut < v_fin
      AND heure_fin > v_debut
      AND (classe_id = v_classe_id OR prof_id = v_prof_id
           OR (v_salle IS NOT NULL AND salle = v_salle));

    IF v_conflit > 0 THEN
        RAISE EXCEPTION 'Conflit d''horaire détecté';
    END IF;

    UPDATE horaires SET statut = 'publie', updated_at = NOW()
    WHERE id = p_horaire_id;

    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- ---------- Archivage des données (JSONB) ----------
CREATE OR REPLACE FUNCTION sp_archiver_donnees(
    p_table      VARCHAR,
    p_record_id  INT,
    p_data       JSONB,
    p_archive_par INT
) RETURNS INT AS $$
DECLARE
    v_archive_id INT;
BEGIN
    INSERT INTO archives (table_source, record_id, donnees_json, archive_par)
    VALUES (p_table, p_record_id, p_data, p_archive_par)
    RETURNING id INTO v_archive_id;

    RETURN v_archive_id;
END;
$$ LANGUAGE plpgsql;

-- ---------- Restaurer une donnée archivée ----------
CREATE OR REPLACE FUNCTION sp_restaurer_archive(
    p_archive_id INT
) RETURNS JSONB AS $$
DECLARE
    v_data JSONB;
BEGIN
    SELECT donnees_json INTO v_data
    FROM archives WHERE id = p_archive_id;

    IF v_data IS NULL THEN
        RAISE EXCEPTION 'Archive introuvable';
    END IF;

    RETURN v_data;
END;
$$ LANGUAGE plpgsql;

-- ---------- Statistiques générales du tableau de bord ----------
CREATE OR REPLACE FUNCTION sp_dashboard_stats()
RETURNS TABLE (
    total_eleves BIGINT,
    total_profs  BIGINT,
    total_classes BIGINT,
    total_communications BIGINT,
    total_documents BIGINT,
    total_clubs BIGINT,
    absences_mois BIGINT,
    retards_mois  BIGINT
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        (SELECT COUNT(*) FROM eleves WHERE statut = 'actif'),
        (SELECT COUNT(*) FROM profs WHERE statut = 'actif'),
        (SELECT COUNT(*) FROM classes),
        (SELECT COUNT(*) FROM communications WHERE statut = 'publie'),
        (SELECT COUNT(*) FROM documents WHERE statut = 'actif'),
        (SELECT COUNT(*) FROM clubs WHERE statut = 'actif'),
        (SELECT COUNT(*) FROM presences WHERE statut = 'absent' AND date_presence >= date_trunc('month', NOW())),
        (SELECT COUNT(*) FROM presences WHERE statut = 'retard' AND date_presence >= date_trunc('month', NOW()));
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

-- Rôles par défaut
INSERT INTO roles (nom_role, description) VALUES
('admin',     'Administrateur système - accès total'),
('eleve',     'Élève - accès consultation'),
('prof',      'Professeur - gestion notes/présences'),
('personnel', 'Personnel - accès limité')
ON CONFLICT (nom_role) DO NOTHING;

-- Permissions par défaut
INSERT INTO permissions (nom_permission, description) VALUES
('users.view',    'Voir les utilisateurs'),
('users.create',  'Créer des utilisateurs'),
('users.edit',    'Modifier des utilisateurs'),
('users.delete',  'Supprimer des utilisateurs'),
('users.roles',   'Gérer les rôles'),
('users.permissions', 'Gérer les permissions'),
('eleves.view',   'Voir les élèves'),
('eleves.create', 'Inscrire des élèves'),
('eleves.edit',   'Modifier des élèves'),
('eleves.delete', 'Supprimer des élèves'),
('eleves.archive','Archiver des élèves'),
('profs.view',    'Voir les professeurs'),
('profs.create',  'Inscrire des professeurs'),
('profs.edit',    'Modifier des professeurs'),
('profs.delete',  'Supprimer des professeurs'),
('personnel.view','Voir le personnel'),
('personnel.create','Inscrire du personnel'),
('personnel.edit','Modifier le personnel'),
('personnel.delete','Supprimer le personnel'),
('horaires.view', 'Voir les horaires'),
('horaires.create','Créer des horaires'),
('horaires.edit', 'Modifier des horaires'),
('horaires.delete','Supprimer des horaires'),
('horaires.publish','Publier des horaires'),
('notes.view',    'Voir les notes'),
('notes.saisie',  'Saisir les notes'),
('notes.edit',    'Modifier les notes'),
('notes.delete',  'Supprimer les notes'),
('presences.view','Voir les présences'),
('presences.saisie','Marquer les présences'),
('clubs.view',    'Voir les clubs'),
('clubs.create',  'Créer des clubs'),
('clubs.edit',    'Modifier des clubs'),
('clubs.delete',  'Supprimer des clubs'),
('communications.create','Créer des communications'),
('communications.publish','Publier des communications'),
('documents.upload','Téléverser des documents'),
('documents.download','Télécharger des documents'),
('documents.manage','Gérer les documents'),
('reports.view',  'Voir les rapports'),
('reports.pdf',   'Générer des PDF'),
('reports.excel', 'Exporter Excel'),
('stats.view',    'Voir les statistiques'),
('chat.send',     'Envoyer des messages'),
('chat.view',     'Voir le chat'),
('settings.manage','Gérer les paramètres'),
('themes.manage', 'Gérer les thèmes'),
('backup.manage', 'Gérer les sauvegardes'),
('logs.view',     'Voir les journaux')
ON CONFLICT (nom_permission) DO NOTHING;

-- Associer toutes les permissions au rôle admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.nom_role = 'admin'
ON CONFLICT DO NOTHING;

-- Permissions pour les professeurs
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.nom_role = 'prof'
  AND p.nom_permission IN ('horaires.view','notes.view','notes.saisie','notes.edit',
                           'presences.view','presences.saisie','communications.create',
                           'documents.upload','documents.download','chat.send','chat.view',
                           'reports.pdf','notes.saisie')
ON CONFLICT DO NOTHING;

-- Permissions pour les élèves
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.nom_role = 'eleve'
  AND p.nom_permission IN ('horaires.view','notes.view','presences.view',
                           'documents.download','chat.send','chat.view')
ON CONFLICT DO NOTHING;

-- Permissions pour le personnel
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.nom_role = 'personnel'
  AND p.nom_permission IN ('horaires.view','documents.download','chat.send','chat.view')
ON CONFLICT DO NOTHING;

-- Utilisateur admin par défaut
-- Mot de passe par défaut : admin123 (à changer)
INSERT INTO utilisateurs (nom, prenom, email, password_hash, role)
VALUES ('Administrateur', 'Système', 'admin@myschool.edu',
        '$2y$10$/sAsnSU14vn1rPAUeQB/UeJrPNhRpQmYCPP2uIfVcNyJGRPk5ztnC', 'admin')
ON CONFLICT (email) DO NOTHING;

-- Paramètres système par défaut
INSERT INTO parametres_systeme (cle, valeur, description) VALUES
('nom_etablissement', 'Mon École', 'Nom de l''établissement'),
('annee_scolaire', '2025-2026', 'Année scolaire courante'),
('adresse_etablissement', '', 'Adresse de l''établissement'),
('telephone_etablissement', '', 'Téléphone de l''établissement'),
('email_etablissement', '', 'Email de l''établissement'),
('session_timeout', '1800', 'Durée de session avant déconnexion auto (secondes)'),
('max_upload_size', '10485760', 'Taille max des uploads (octets)'),
('sauvegarde_auto', 'true', 'Sauvegarde automatique activée'),
('seuil_absences_alerte', '3', 'Seuil d''absences avant alerte')
ON CONFLICT (cle) DO NOTHING;

-- Thèmes par défaut
INSERT INTO themes (nom_theme, css_path, actif) VALUES
('Thème Par Défaut', 'assets/css/theme-default.css', TRUE),
('Thème Sombre',     'assets/css/theme-dark.css', FALSE),
('Thème Bleu',       'assets/css/theme-blue.css', FALSE),
('Thème Vert',       'assets/css/theme-green.css', FALSE)
ON CONFLICT DO NOTHING;

-- ============================================================
-- TRIGGERS
-- ============================================================

-- Mise à jour automatique de updated_at
CREATE OR REPLACE FUNCTION fn_update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_update ON utilisateurs;
CREATE TRIGGER trg_users_update BEFORE UPDATE ON utilisateurs
FOR EACH ROW EXECUTE FUNCTION fn_update_timestamp();

DROP TRIGGER IF EXISTS trg_eleves_update ON eleves;
CREATE TRIGGER trg_eleves_update BEFORE UPDATE ON eleves
FOR EACH ROW EXECUTE FUNCTION fn_update_timestamp();

DROP TRIGGER IF EXISTS trg_profs_update ON profs;
CREATE TRIGGER trg_profs_update BEFORE UPDATE ON profs
FOR EACH ROW EXECUTE FUNCTION fn_update_timestamp();

DROP TRIGGER IF EXISTS trg_horaires_update ON horaires;
CREATE TRIGGER trg_horaires_update BEFORE UPDATE ON horaires
FOR EACH ROW EXECUTE FUNCTION fn_update_timestamp();

-- Journaliser automatiquement la modification des notes importantes
CREATE OR REPLACE FUNCTION fn_log_note_changes()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO journal_activites (action, details)
        VALUES ('note.ajout', 'Note ajoutée pour élève ' || NEW.eleve_id ||
                ' matière ' || NEW.matiere_id || ' : ' || NEW.note);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO journal_activites (action, details)
        VALUES ('note.modification', 'Note modifiée pour élève ' || NEW.eleve_id ||
                ' : ' || OLD.note || ' -> ' || NEW.note);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_notes_log ON notes;
CREATE TRIGGER trg_notes_log AFTER INSERT OR UPDATE ON notes
FOR EACH ROW EXECUTE FUNCTION fn_log_note_changes();
