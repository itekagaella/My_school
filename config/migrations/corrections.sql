-- ============================================================
-- My_School - Migration de corrections (bugs critiques)
-- Application : psql -U postgres -d my_school -f migrations/corrections.sql
-- ============================================================

-- ---------- Fix: sp_inscrire_eleve (matricule + validation classe) ----------
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

-- ---------- Fix: sp_publier_horaire (conflit salle + horaire inexistant) ----------
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