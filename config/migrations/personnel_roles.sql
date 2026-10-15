-- ============================================================
-- My_School - Migration : nouveaux rôles du personnel
--   - Surveillant / Finance / Discipline
--   - Modules Finance (frais, paiements) et Discipline (incidents, sanctions, convocations)
-- Application : psql -U postgres -d my_school -f config/migrations/personnel_roles.sql
-- Idempotent : peut être relancé sans risque.
-- ============================================================

-- ---------- Contrainte de rôle : ajout des nouveaux rôles ----------
ALTER TABLE utilisateurs DROP CONSTRAINT IF EXISTS utilisateurs_role_check;
ALTER TABLE utilisateurs ADD CONSTRAINT utilisateurs_role_check
    CHECK (role IN ('admin','eleve','prof','personnel','surveillant','finance','discipline'));

-- ---------- GESTION FINANCIÈRE ----------
CREATE TABLE IF NOT EXISTS frais_scolaires (
    id             SERIAL PRIMARY KEY,
    libelle        VARCHAR(150) NOT NULL,
    description    TEXT,
    montant        DECIMAL(12,2) NOT NULL DEFAULT 0,
    niveau         VARCHAR(80),
    annee_scolaire VARCHAR(9) NOT NULL,
    obligatoire    BOOLEAN DEFAULT TRUE,
    actif          BOOLEAN DEFAULT TRUE,
    created_at     TIMESTAMP DEFAULT NOW(),
    updated_at     TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS paiements (
    id             SERIAL PRIMARY KEY,
    eleve_id       INT NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    frais_id       INT REFERENCES frais_scolaires(id) ON DELETE SET NULL,
    montant        DECIMAL(12,2) NOT NULL,
    periode        VARCHAR(40),
    date_paiement  DATE NOT NULL DEFAULT CURRENT_DATE,
    mode_paiement  VARCHAR(20) NOT NULL DEFAULT 'especes'
                   CHECK (mode_paiement IN ('especes')),
    reference      VARCHAR(100),
    recu_numero    VARCHAR(30) NOT NULL UNIQUE,
    observation    TEXT,
    enregistre_par INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    created_at     TIMESTAMP DEFAULT NOW()
);

-- ---------- DISCIPLINE ----------
CREATE TABLE IF NOT EXISTS incidents_discipline (
    id            SERIAL PRIMARY KEY,
    eleve_id      INT NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    date_incident DATE NOT NULL DEFAULT CURRENT_DATE,
    type_incident VARCHAR(80) NOT NULL,
    gravite       VARCHAR(20) NOT NULL DEFAULT 'moyenne'
                  CHECK (gravite IN ('faible','moyenne','grave')),
    description   TEXT,
    lieu          VARCHAR(120),
    signale_par  INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    statut        VARCHAR(20) NOT NULL DEFAULT 'ouvert'
                  CHECK (statut IN ('ouvert','traite','clos')),
    created_at    TIMESTAMP DEFAULT NOW(),
    updated_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS sanctions (
    id             SERIAL PRIMARY KEY,
    eleve_id       INT NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    incident_id    INT REFERENCES incidents_discipline(id) ON DELETE SET NULL,
    type_sanction  VARCHAR(80) NOT NULL,
    description    TEXT,
    date_debut     DATE NOT NULL DEFAULT CURRENT_DATE,
    date_fin       DATE,
    duree_jours    INT,
    prononcee_par  INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    statut         VARCHAR(20) NOT NULL DEFAULT 'en_cours'
                   CHECK (statut IN ('en_cours','terminee','annulee')),
    created_at     TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS convocations (
    id               SERIAL PRIMARY KEY,
    eleve_id         INT NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    incident_id      INT REFERENCES incidents_discipline(id) ON DELETE SET NULL,
    date_convocation DATE NOT NULL,
    heure            TIME,
    motif            TEXT NOT NULL,
    statut           VARCHAR(20) NOT NULL DEFAULT 'prevue'
                     CHECK (statut IN ('prevue','effectuee','manquee')),
    cree_par         INT REFERENCES utilisateurs(id) ON DELETE SET NULL,
    created_at       TIMESTAMP DEFAULT NOW()
);

-- ---------- Rôles ----------
INSERT INTO roles (nom_role, description) VALUES
('surveillant', 'Surveillant - suivi des élèves et de la discipline'),
('finance',   'Gestionnaire financier - frais et paiements (sans droits admin)'),
('discipline','Responsable discipline - incidents et sanctions (sans droits admin)')
ON CONFLICT (nom_role) DO NOTHING;

-- ---------- Permissions ----------
INSERT INTO permissions (nom_permission, description) VALUES
('finance.view',  'Voir la gestion financière'),
('finance.manage','Gérer les frais et les paiements'),
('finance.reports','Consulter les rapports financiers'),
('discipline.view','Voir la discipline'),
('discipline.manage','Gérer les incidents disciplinaires'),
('discipline.sanctions','Prononcer et gérer les sanctions'),
('surveillance.view','Voir les présences des élèves'),
('surveillance.manage','Gérer les présences des élèves')
ON CONFLICT (nom_permission) DO NOTHING;

-- ---------- Attribution des permissions ----------
-- L'administrateur conserve l'accès total (nouvelles permissions incluses)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.nom_role = 'admin'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.nom_role = 'surveillant'
  AND p.nom_permission IN ('eleves.view','horaires.view','presences.view','presences.saisie',
                           'surveillance.view','surveillance.manage','discipline.view',
                           'communications.create','documents.download','chat.send','chat.view')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.nom_role = 'finance'
  AND p.nom_permission IN ('eleves.view','horaires.view','finance.view','finance.manage',
                           'finance.reports','reports.pdf','reports.excel',
                           'documents.download','communications.create','chat.send','chat.view')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.nom_role = 'discipline'
  AND p.nom_permission IN ('eleves.view','horaires.view','presences.view','presences.saisie',
                           'surveillance.view','discipline.view','discipline.manage',
                           'discipline.sanctions','documents.download','communications.create',
                           'chat.send','chat.view')
ON CONFLICT DO NOTHING;

-- ---------- Index ----------
CREATE INDEX IF NOT EXISTS idx_paiements_eleve ON paiements(eleve_id);
CREATE INDEX IF NOT EXISTS idx_paiements_date ON paiements(date_paiement);
CREATE INDEX IF NOT EXISTS idx_paiements_frais ON paiements(frais_id);
CREATE INDEX IF NOT EXISTS idx_incidents_eleve ON incidents_discipline(eleve_id);
CREATE INDEX IF NOT EXISTS idx_incidents_date ON incidents_discipline(date_incident);
CREATE INDEX IF NOT EXISTS idx_incidents_statut ON incidents_discipline(statut);
CREATE INDEX IF NOT EXISTS idx_sanctions_eleve ON sanctions(eleve_id);
CREATE INDEX IF NOT EXISTS idx_sanctions_statut ON sanctions(statut);
CREATE INDEX IF NOT EXISTS idx_convocations_eleve ON convocations(eleve_id);

-- ---------- Migration des comptes personnel existants ----------
-- Le rôle générique « personnel » devient « surveillant » par défaut.
UPDATE utilisateurs SET role = 'surveillant' WHERE role = 'personnel';
