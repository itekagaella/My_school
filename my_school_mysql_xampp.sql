-- ============================================================
-- My_School - Schéma MySQL 5.7/8.0 pour XAMPP
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

DROP DATABASE IF EXISTS my_school;
CREATE DATABASE my_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE my_school;

-- Authentification
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_role VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_permission VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','eleve','prof','personnel','surveillant','finance','discipline') NOT NULL,
    avatar VARCHAR(255) NULL,
    actif TINYINT(1) DEFAULT 1,
    deux_facteurs TINYINT(1) DEFAULT 0,
    dernier_code_otp VARCHAR(255) NULL,
    dernier_code_exp DATETIME NULL,
    derniere_connexion DATETIME NULL,
    tentatives_connexion INT DEFAULT 0,
    compte_verrouille TINYINT(1) DEFAULT 0,
    verrou_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions_utilisateur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historique_connexions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    date_connexion DATETIME DEFAULT CURRENT_TIMESTAMP,
    succes TINYINT(1) NOT NULL,
    raison VARCHAR(255),
    CONSTRAINT fk_hist_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    date_action DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structure scolaire
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_classe VARCHAR(50) NOT NULL,
    niveau VARCHAR(50) NOT NULL,
    section VARCHAR(50),
    annee_scolaire VARCHAR(9) NOT NULL,
    capacite INT DEFAULT 50,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE salles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero INT NULL,
    nom VARCHAR(80) NOT NULL UNIQUE,
    type ENUM('classe','administratif') NOT NULL DEFAULT 'classe',
    classe_id INT NULL UNIQUE,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_salle_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_matiere VARCHAR(20) NOT NULL UNIQUE,
    nom_matiere VARCHAR(100) NOT NULL,
    coefficient DECIMAL(4,2) DEFAULT 1.00,
    classe_id INT NULL,
    niveau_cible VARCHAR(80),
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_matiere_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acteurs
CREATE TABLE eleves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,
    matricule VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE NOT NULL,
    sexe ENUM('M','F') NOT NULL,
    classe_id INT NULL,
    parent_nom VARCHAR(200),
    parent_tel VARCHAR(20),
    parent_email VARCHAR(150),
    adresse TEXT,
    photo VARCHAR(255),
    statut ENUM('actif','inactif','archive') DEFAULT 'actif',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_eleve_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    CONSTRAINT fk_eleve_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL,
    INDEX idx_eleves_classe (classe_id),
    INDEX idx_eleves_matricule (matricule),
    INDEX idx_eleves_nom_prenom (nom, prenom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,
    matricule VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    specialite VARCHAR(100),
    tel VARCHAR(20),
    email VARCHAR(150),
    adresse TEXT,
    photo VARCHAR(255),
    statut ENUM('actif','inactif','archive') DEFAULT 'actif',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prof_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,
    matricule VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    fonction VARCHAR(100),
    tel VARCHAR(20),
    email VARCHAR(150),
    statut ENUM('actif','inactif','archive') DEFAULT 'actif',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pers_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pédagogie
CREATE TABLE horaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classe_id INT NOT NULL,
    matiere_id INT NOT NULL,
    prof_id INT NOT NULL,
    salle_id INT NULL,
    jour ENUM('lundi','mardi','mercredi','jeudi','vendredi','samedi') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    salle VARCHAR(80),
    statut ENUM('brouillon','publie','annule') DEFAULT 'brouillon',
    annee_scolaire VARCHAR(9),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_horaire_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_horaire_matiere FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE,
    CONSTRAINT fk_horaire_prof FOREIGN KEY (prof_id) REFERENCES profs(id) ON DELETE CASCADE,
    CONSTRAINT fk_horaire_salle FOREIGN KEY (salle_id) REFERENCES salles(id) ON DELETE SET NULL,
    INDEX idx_horaires_classe_jour (classe_id, jour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    matiere_id INT NOT NULL,
    prof_id INT NOT NULL,
    note DECIMAL(4,2) NOT NULL,
    type_evaluation ENUM('interrogation','controle','devoir','composition'),
    periode VARCHAR(20),
    coefficient DECIMAL(3,2) DEFAULT 1.00,
    date_evaluation DATE NOT NULL,
    appreciation TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_note_eleve FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    CONSTRAINT fk_note_matiere FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE,
    CONSTRAINT fk_note_prof FOREIGN KEY (prof_id) REFERENCES profs(id) ON DELETE CASCADE,
    INDEX idx_notes_eleve (eleve_id),
    INDEX idx_notes_matiere (matiere_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    horaire_id INT NULL,
    prof_id INT NULL,
    date_presence DATE NOT NULL,
    statut ENUM('present','absent','retard','justifie') NOT NULL,
    heure_arrivee TIME NULL,
    motif TEXT,
    justifie TINYINT(1) DEFAULT 0,
    justifie_par INT NULL,
    date_justification DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_presence_eleve FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    CONSTRAINT fk_presence_horaire FOREIGN KEY (horaire_id) REFERENCES horaires(id) ON DELETE SET NULL,
    CONSTRAINT fk_presence_prof FOREIGN KEY (prof_id) REFERENCES profs(id) ON DELETE SET NULL,
    CONSTRAINT fk_presence_justifie FOREIGN KEY (justifie_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_presences_eleve (eleve_id),
    INDEX idx_presences_date (date_presence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Finances
CREATE TABLE frais_scolaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(150) NOT NULL,
    description TEXT,
    montant DECIMAL(12,2) NOT NULL DEFAULT 0,
    niveau VARCHAR(80),
    annee_scolaire VARCHAR(9) NOT NULL,
    obligatoire TINYINT(1) DEFAULT 1,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    frais_id INT NULL,
    montant DECIMAL(12,2) NOT NULL,
    periode VARCHAR(40),
    date_paiement DATE NOT NULL DEFAULT (CURRENT_DATE),
    mode_paiement ENUM('especes') NOT NULL DEFAULT 'especes',
    reference VARCHAR(100),
    recu_numero VARCHAR(30) NOT NULL UNIQUE,
    observation TEXT,
    enregistre_par INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_paiement_eleve FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    CONSTRAINT fk_paiement_frais FOREIGN KEY (frais_id) REFERENCES frais_scolaires(id) ON DELETE SET NULL,
    CONSTRAINT fk_paiement_user FOREIGN KEY (enregistre_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_paiements_eleve (eleve_id),
    INDEX idx_paiements_date (date_paiement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discipline
CREATE TABLE incidents_discipline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    date_incident DATE NOT NULL DEFAULT (CURRENT_DATE),
    type_incident VARCHAR(80) NOT NULL,
    gravite ENUM('faible','moyenne','grave') NOT NULL DEFAULT 'moyenne',
    description TEXT,
    lieu VARCHAR(120),
    signale_par INT NULL,
    statut ENUM('ouvert','traite','clos') NOT NULL DEFAULT 'ouvert',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_incident_eleve FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    CONSTRAINT fk_incident_signale FOREIGN KEY (signale_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_incidents_eleve (eleve_id),
    INDEX idx_incidents_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sanctions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    incident_id INT NULL,
    type_sanction VARCHAR(80) NOT NULL,
    description TEXT,
    date_debut DATE NOT NULL DEFAULT (CURRENT_DATE),
    date_fin DATE NULL,
    statut ENUM('active','terminee','annulee') DEFAULT 'active',
    prononce_par INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sanction_eleve FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    CONSTRAINT fk_sanction_incident FOREIGN KEY (incident_id) REFERENCES incidents_discipline(id) ON DELETE SET NULL,
    CONSTRAINT fk_sanction_prononce FOREIGN KEY (prononce_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE convocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    date_convocation DATETIME NOT NULL,
    lieu VARCHAR(150),
    motif TEXT NOT NULL,
    objet VARCHAR(200),
    convoque_par INT NULL,
    statut ENUM('envoyee','vue','traitee') DEFAULT 'envoyee',
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_convoc_eleve FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    CONSTRAINT fk_convoc_convoque FOREIGN KEY (convoque_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Communication
CREATE TABLE communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auteur_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    contenu TEXT NOT NULL,
    type ENUM('general','classe','individuel') DEFAULT 'general',
    destinataires JSON,
    classe_id INT NULL,
    priorite ENUM('normale','importante','urgente') DEFAULT 'normale',
    date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_expiration DATETIME NULL,
    actif TINYINT(1) DEFAULT 1,
    CONSTRAINT fk_comm_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_comm_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    fichier VARCHAR(255) NOT NULL,
    nom_original VARCHAR(255),
    type_fichier VARCHAR(50),
    taille INT,
    categorie VARCHAR(50),
    auteur_id INT NULL,
    classe_id INT NULL,
    partage_public TINYINT(1) DEFAULT 0,
    telechargements INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_club VARCHAR(100) NOT NULL,
    description TEXT,
    responsable VARCHAR(150),
    logo VARCHAR(255),
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE club_membres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    eleve_id INT NOT NULL,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    role ENUM('membre','responsable') DEFAULT 'membre',
    UNIQUE KEY unique_membre (club_id, eleve_id),
    CONSTRAINT fk_cm_club FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_eleve FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    lu TINYINT(1) DEFAULT 0,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chat_sender FOREIGN KEY (sender_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_receiver FOREIGN KEY (receiver_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_chat_sender (sender_id),
    INDEX idx_chat_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    titre VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(30),
    lien VARCHAR(255),
    lu TINYINT(1) DEFAULT 0,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_notif_user_lu (user_id, lu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Système
CREATE TABLE parametres_systeme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_theme VARCHAR(50) NOT NULL,
    css_path VARCHAR(100) NOT NULL,
    actif TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sauvegardes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin VARCHAR(500),
    taille BIGINT,
    type ENUM('auto','manuel') DEFAULT 'manuel',
    date_sauvegarde DATETIME DEFAULT CURRENT_TIMESTAMP,
    effectuee_par INT NULL,
    CONSTRAINT fk_sauv_user FOREIGN KEY (effectuee_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE archives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_source VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    donnees_json JSON,
    date_archive DATETIME DEFAULT CURRENT_TIMESTAMP,
    archive_par INT NULL,
    CONSTRAINT fk_archive_user FOREIGN KEY (archive_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales
INSERT INTO roles (nom_role, description) VALUES
('admin','Administrateur système'),
('eleve','Élève'),
('prof','Professeur'),
('personnel','Personnel'),
('surveillant','Surveillant'),
('finance','Gestionnaire financier'),
('discipline','Responsable discipline');

INSERT INTO permissions (nom_permission, description) VALUES
('users.view','Voir les utilisateurs'),
('users.manage','Gérer les utilisateurs'),
('eleves.view','Voir les élèves'),
('eleves.manage','Gérer les élèves'),
('classes.manage','Gérer les classes'),
('matieres.manage','Gérer les matières'),
('horaires.view','Voir les emplois du temps'),
('horaires.manage','Gérer les emplois du temps'),
('notes.view','Voir les notes'),
('notes.saisie','Saisir les notes'),
('presences.view','Voir les présences'),
('presences.saisie','Saisir les présences'),
('finance.view','Voir les finances'),
('finance.manage','Gérer les finances'),
('finance.reports','Rapports financiers'),
('discipline.view','Voir la discipline'),
('discipline.manage','Gérer la discipline'),
('discipline.sanctions','Gérer les sanctions'),
('communications.view','Voir les communications'),
('communications.create','Créer des communications'),
('documents.view','Voir les documents'),
('documents.manage','Gérer les documents'),
('documents.download','Télécharger des documents'),
('chat.view','Voir le chat'),
('chat.send','Envoyer des messages'),
('reports.pdf','Générer rapports PDF'),
('reports.excel','Exporter Excel'),
('parametres.view','Voir les paramètres'),
('parametres.manage','Gérer les paramètres'),
('sauvegarde.manage','Gérer les sauvegardes'),
('logs.view','Voir le journal'),
('clubs.manage','Gérer les clubs'),
('surveillance.view','Voir la surveillance');

-- Admin a toutes les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Compte admin par défaut (mot de passe: admin123)
INSERT INTO utilisateurs (nom, prenom, email, password_hash, role) VALUES
('Administrateur', 'Système', 'admin@myschool.edu', '$2y$10$/sAsnSU14vn1rPAUeQB/UeJrPNhRpQmYCPP2uIfVcNyJGRPk5ztnC', 'admin');

-- Paramètres
INSERT INTO parametres_systeme (cle, valeur, description) VALUES
('nom_etablissement','Mon École','Nom de l''établissement'),
('annee_scolaire','2025-2026','Année scolaire courante'),
('adresse_etablissement','','Adresse'),
('telephone_etablissement','','Téléphone'),
('email_etablissement','','Email'),
('session_timeout','1800','Durée session (secondes)'),
('max_upload_size','10485760','Taille max upload'),
('sauvegarde_auto','true','Sauvegarde auto'),
('seuil_absences_alerte','3','Seuil alertes'),
('devise','FBu','Devise');

-- Thèmes
INSERT INTO themes (nom_theme, css_path, actif) VALUES
('Thème Par Défaut','assets/css/theme-default.css',1),
('Thème Sombre','assets/css/theme-dark.css',0),
('Thème Bleu','assets/css/theme-blue.css',0),
('Thème Vert','assets/css/theme-green.css',0);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- PROCÉDURES STOCKÉES - Modification et Suppression
-- ============================================================

DELIMITER $$

-- Procédure pour modifier un élève
CREATE PROCEDURE sp_modifier_eleve(
    IN p_eleve_id INT,
    IN p_nom VARCHAR(100),
    IN p_prenom VARCHAR(100),
    IN p_classe_id INT,
    IN p_parent_nom VARCHAR(200),
    IN p_parent_tel VARCHAR(20),
    IN p_parent_email VARCHAR(150),
    IN p_adresse TEXT
)
BEGIN
    UPDATE eleves 
    SET nom = p_nom,
        prenom = p_prenom,
        classe_id = p_classe_id,
        parent_nom = p_parent_nom,
        parent_tel = p_parent_tel,
        parent_email = p_parent_email,
        adresse = p_adresse,
        updated_at = NOW()
    WHERE id = p_eleve_id;
    
    SELECT 'Élève modifié avec succès' AS message, ROW_COUNT() AS lignes_modifiees;
END$$

-- Procédure pour supprimer un élève (suppression physique)
CREATE PROCEDURE sp_supprimer_eleve(
    IN p_eleve_id INT
)
BEGIN
    DECLARE v_user_id INT;
    
    SELECT user_id INTO v_user_id FROM eleves WHERE id = p_eleve_id;
    
    DELETE FROM eleves WHERE id = p_eleve_id;
    
    IF v_user_id IS NOT NULL THEN
        DELETE FROM utilisateurs WHERE id = v_user_id;
    END IF;
    
    SELECT 'Élève supprimé avec succès' AS message;
END$$

-- Procédure pour archiver un élève (soft delete)
CREATE PROCEDURE sp_archiver_eleve(
    IN p_eleve_id INT
)
BEGIN
    UPDATE eleves 
    SET statut = 'archive', updated_at = NOW()
    WHERE id = p_eleve_id;
    
    SELECT 'Élève archivé avec succès' AS message, ROW_COUNT() AS lignes_modifiees;
END$$

-- Procédure pour désarchiver un élève
CREATE PROCEDURE sp_desarchiver_eleve(
    IN p_eleve_id INT
)
BEGIN
    UPDATE eleves 
    SET statut = 'actif', updated_at = NOW()
    WHERE id = p_eleve_id;
    
    SELECT 'Élève réactivé avec succès' AS message, ROW_COUNT() AS lignes_modifiees;
END$$

-- Procédure pour modifier un utilisateur
CREATE PROCEDURE sp_modifier_utilisateur(
    IN p_user_id INT,
    IN p_nom VARCHAR(100),
    IN p_prenom VARCHAR(100),
    IN p_email VARCHAR(150),
    IN p_role VARCHAR(20),
    IN p_actif TINYINT(1)
)
BEGIN
    UPDATE utilisateurs 
    SET nom = p_nom,
        prenom = p_prenom,
        email = p_email,
        role = p_role,
        actif = p_actif,
        updated_at = NOW()
    WHERE id = p_user_id;
    
    SELECT 'Utilisateur modifié avec succès' AS message, ROW_COUNT() AS lignes_modifiees;
END$$

-- Procédure pour supprimer un utilisateur
CREATE PROCEDURE sp_supprimer_utilisateur(
    IN p_user_id INT
)
BEGIN
    DELETE FROM utilisateurs WHERE id = p_user_id;
    SELECT 'Utilisateur supprimé avec succès' AS message;
END$$

DELIMITER ;

-- Exemples d'utilisation
-- CALL sp_modifier_eleve(1, 'Dupont', 'Jean', 2, 'Dupont Parent', '12345678', 'parent@email.com', 'Adresse');
-- CALL sp_archiver_eleve(1);
-- CALL sp_supprimer_eleve(1);
-- CALL sp_desarchiver_eleve(1);
