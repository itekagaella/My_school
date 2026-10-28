#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Générateur de 3 diagrammes UML pour My_School (PlantUML + images)
"""

import os
import sys
import subprocess
from pathlib import Path

OUTPUT_DIR = Path("/home/iragame/My_School/diagrams")
OUTPUT_DIR.mkdir(exist_ok=True)

# 1. DIAGRAMME DE CLASSES
diagram_classes = """@startuml My_School_Classes

title Diagramme de Classes - My_School (Système de Gestion Scolaire)

' Packages
package "Authentification" {
  class Utilisateur {
    +id: int
    +nom: string
    +prenom: string
    +email: string
    +password_hash: string
    +role: enum(admin,eleve,prof,personnel,surveillant,finance,discipline)
    +actif: boolean
    +deux_facteurs: boolean
    +derniere_connexion: timestamp
    +se_connecter()
    +se_deconnecter()
  }
  
  class Role {
    +id: int
    +nom_role: string
    +description: string
  }
  
  class Permission {
    +id: int
    +nom_permission: string
    +description: string
  }
}

package "Pédagogie" {
  class Eleve {
    +id: int
    +matricule: string
    +nom: string
    +prenom: string
    +date_naissance: date
    +sexe: enum(M,F)
    +parent_nom: string
    +parent_tel: string
    +statut: enum(actif,inactif,archive)
    +consulter_notes()
    +consulter_horaires()
    +consulter_absences()
  }
  
  class Prof {
    +id: int
    +matricule: string
    +nom: string
    +prenom: string
    +specialite: string
    +tel: string
    +saisir_notes()
    +gerer_presences()
  }
  
  class Classe {
    +id: int
    +nom_classe: string
    +niveau: string
    +section: string
    +annee_scolaire: string
    +capacite: int
    +obtenir_effectif()
  }
  
  class Matiere {
    +id: int
    +code_matiere: string
    +nom_matiere: string
    +coefficient: decimal
    +niveau_cible: string
  }
  
  class Horaire {
    +id: int
    +jour: enum(lundi,mardi,mercredi,jeudi,vendredi,samedi)
    +heure_debut: time
    +heure_fin: time
    +salle: string
    +statut: enum(brouillon,publie,annule)
    +annee_scolaire: string
  }
  
  class Note {
    +id: int
    +note: decimal(4,2)
    +type_evaluation: enum(interrogation,controle,devoir,composition)
    +periode: string
    +coefficient: decimal
    +date_evaluation: date
    +observation: text
  }
  
  class Presence {
    +id: int
    +date_presence: date
    +statut: enum(present,absent,retard,justifie)
    +heure_arrivee: time
    +motif: text
    +justifie: boolean
  }
}

package "Finances" {
  class FraisScolaire {
    +id: int
    +libelle: string
    +montant: decimal(12,2)
    +niveau: string
    +annee_scolaire: string
    +obligatoire: boolean
    +actif: boolean
  }
  
  class Paiement {
    +id: int
    +montant: decimal(12,2)
    +periode: string
    +date_paiement: date
    +mode_paiement: enum(especes)
    +recu_numero: string
    +observation: text
    +enregistrer_paiement()
    +generer_recu()
  }
}

package "Discipline" {
  class IncidentDiscipline {
    +id: int
    +date_incident: date
    +type_incident: string
    +gravite: enum(faible,moyenne,grave)
    +description: text
    +lieu: string
    +statut: enum(ouvert,traite,clos)
    +signaler()
  }
  
  class Sanction {
    +id: int
    +type_sanction: string
    +description: text
    +date_debut: date
    +date_fin: date
    +statut: enum(active,terminee,annulee)
    +prononcer()
  }
  
  class Convocation {
    +id: int
    +date_convocation: datetime
    +lieu: string
    +motif: text
    +statut: enum(envoyee,vue,traitee)
  }
}

package "Communication" {
  class Communication {
    +id: int
    +titre: string
    +contenu: text
    +type: enum(general,classe,individuel)
    +destinataires: json
    +date_envoi: timestamp
    +priorite: enum(normale,importante,urgente)
    +envoyer()
  }
  
  class Document {
    +id: int
    +titre: string
    +fichier: string
    +type_fichier: string
    +taille: int
    +categorie: string
    +partage_public: boolean
    +telecharger()
  }
  
  class Club {
    +id: int
    +nom_club: string
    +description: text
    +responsable: string
    +actif: boolean
    +inscrire()
  }
}

' Relations
Utilisateur "1" -- "0..1" Eleve
Utilisateur "1" -- "0..1" Prof
Role "1" -- "*" Permission : associe
Utilisateur "*" -- "1" Role

Classe "1" -- "*" Eleve
Eleve "*" -- "1" Classe
Prof "*" -- "*" Matiere
Classe "*" -- "*" Matiere
Horaire "*" -- "1" Classe
Horaire "*" -- "1" Matiere
Horaire "*" -- "1" Prof
Horaire "*" -- "1" Salle

Note "*" -- "1" Eleve
Note "*" -- "1" Matiere
Note "*" -- "1" Prof

Presence "*" -- "1" Eleve
Presence "*" -- "1" Horaire
Presence "*" -- "1" Prof

Eleve "1" -- "*" Paiement
FraisScolaire "1" -- "*" Paiement
Utilisateur "1" -- "*" Paiement : enregistre_par

Eleve "1" -- "*" IncidentDiscipline
Utilisateur "1" -- "*" IncidentDiscipline : signale_par
IncidentDiscipline "1" -- "0..1" Sanction
Eleve "1" -- "*" Sanction
Eleve "1" -- "*" Convocation
Utilisateur "1" -- "*" Convocation

Communication "*" -- "1" Utilisateur : expediteur
Eleve "*" -- "*" Club
Document "*" -- "1" Utilisateur : upload_par

class Salle {
  +id: int
  +numero: int
  +nom: string
  +type: enum(classe,administratif)
  +actif: boolean
}

class Personnel {
  +id: int
  +matricule: string
  +nom: string
  +prenom: string
  +fonction: string
  +statut: enum(actif,inactif,archive)
}

Utilisateur "1" -- "0..1" Personnel

@enduml
"""

# 2. DIAGRAMME D'ACTIVITES
diagram_activites = """@startuml My_School_Activites

title Diagramme d'Activités - Processus Gestion Scolaire My_School

|Administrateur|
start
:Se connecter au système;
:Accéder au tableau de bord;

if (Gérer configuration?) then (oui)
  :Gérer paramètres système;
  :Gérer rôles et permissions;
  :Gérer thèmes;
  :Effectuer sauvegarde/restauration;
endif

|Administrateur|
if (Gérer structure?) then (oui)
  :Créer/Modifier classes;
  :Créer/Modifier matières;
  :Créer/Modifier emplois du temps;
  :Gérer utilisateurs;
endif

|Professeur|
if (Activités pédagogiques?) then (oui)
  :Consulter emploi du temps;
  :Faire l'appel / Saisir présences;
  if (Tous présents?) then (non)
    :Marquer absents/retards/justifiés;
  endif
  :Saisir notes par évaluation;
  :Ajouter observations;
  :Publier emploi du temps si brouillon;
endif

|Surveillant/Discipline|
if (Suivi discipline?) then (oui)
  :Signaler incident disciplinaire;
  if (Gravité grave?) then (oui)
    :Notifier administration;
  endif
  :Gérer incidents ouverts;
  :Prononcer sanctions;
  :Créer convocations parents;
endif

|Gestionnaire Financier|
if (Gestion financière?) then (oui)
  :Définir frais scolaires par niveau/année;
  :Consulter situation financière élèves;
  :Enregistrer paiement en espèces;
  :Générer reçu automatique;
  if (Solde négatif/impayé?) then (oui)
    :Suivre relances;
  endif
  :Générer rapports financiers;
endif

|Élève/Parent|
if (Consultation?) then (oui)
  :Consulter notes;
  :Consulter emploi du temps;
  :Consulter présences/absences;
  :Consulter incidents/sanctions;
  :Consulter situation financière;
  :Consulter documents partagés;
  :Consulter communications;
  :Participer aux clubs;
  :Utiliser chat interne;
endif

|Administrateur|
:Générer rapports (PDF/Excel);
:Consulter journal d'activités;
:Vérifier état sauvegardes;
stop

@enduml
"""

# 3. DIAGRAMME DE CAS D'UTILISATION
diagram_cas_utilisation = """@startuml My_School_Use_Cases

title Diagramme de Cas d'Utilisation - My_School

left to right direction

actor Administrateur as Admin
actor Professeur as Prof
actor Surveillant as Surv
actor "Gestionnaire Financier" as Finance
actor "Responsable Discipline" as Disc
actor Élève as Eleve
actor Personnel as Pers

rectangle "Système My_School" {
  ' Authentification
  package "Authentification & Sécurité" {
    usecase "S'authentifier" as UC_Auth
    usecase "Activer/Désactiver 2FA" as UC_2FA
    usecase "Réinitialiser mot de passe" as UC_Reset
    usecase "Gérer sessions" as UC_Sessions
  }
  
  ' Administration
  package "Administration" {
    usecase "Gérer utilisateurs" as UC_Users
    usecase "Gérer rôles/permissions" as UC_Roles
    usecase "Gérer classes" as UC_Classes
    usecase "Gérer matières" as UC_Matieres
    usecase "Gérer paramètres" as UC_Params
    usecase "Gérer thèmes" as UC_Themes
    usecase "Sauvegarder/Restaurer" as UC_Backup
    usecase "Consulter journal activités" as UC_Log
  }
  
  ' Pédagogie
  package "Pédagogie" {
    usecase "Gérer emplois du temps" as UC_Horaires
    usecase "Saisir notes" as UC_Notes
    usecase "Consulter notes" as UC_VoirNotes
    usecase "Saisir présences" as UC_Presences
    usecase "Consulter présences" as UC_VoirPres
    usecase "Gérer élèves" as UC_Eleves
    usecase "Gérer professeurs" as UC_Profs
  }
  
  ' Finances
  package "Finances" {
    usecase "Définir frais scolaires" as UC_Frais
    usecase "Enregistrer paiements" as UC_Paiements
    usecase "Générer reçus" as UC_Recu
    usecase "Consulter soldes" as UC_Soldes
    usecase "Générer rapports financiers" as UC_RapportFin
  }
  
  ' Discipline
  package "Discipline" {
    usecase "Signaler incident" as UC_Incident
    usecase "Gérer incidents" as UC_GererInc
    usecase "Prononcer sanctions" as UC_Sanction
    usecase "Gérer convocations" as UC_Convoc
  }
  
  ' Communication & Autres
  package "Communication" {
    usecase "Envoyer communications" as UC_Com
    usecase "Gérer documents" as UC_Docs
    usecase "Partager documents" as UC_Partage
    usecase "Télécharger documents" as UC_Telecharger
    usecase "Gérer clubs" as UC_Clubs
    usecase "Utiliser chat interne" as UC_Chat
    usecase "Gérer notifications" as UC_Notif
  }
  
  ' Rapports
  package "Rapports & Exports" {
    usecase "Exporter PDF" as UC_PDF
    usecase "Exporter Excel/XLSX" as UC_XLSX
    usecase "Statistiques tableau de bord" as UC_Stats
  }
}

' Relations Admin
Admin --> UC_Auth
Admin --> UC_Users
Admin --> UC_Roles
Admin --> UC_Classes
Admin --> UC_Matieres
Admin --> UC_Horaires
Admin --> UC_Eleves
Admin --> UC_Profs
Admin --> UC_Params
Admin --> UC_Themes
Admin --> UC_Backup
Admin --> UC_Log
Admin --> UC_Com
Admin --> UC_Docs
Admin --> UC_Stats
Admin --> UC_PDF
Admin --> UC_XLSX
Admin --> UC_Frais
Admin --> UC_Paiements
Admin --> UC_RapportFin
Admin --> UC_GererInc
Admin --> UC_Sanction
Admin --> UC_Convoc

' Relations Prof
Prof --> UC_Auth
Prof --> UC_Notes
Prof --> UC_VoirNotes
Prof --> UC_Presences
Prof --> UC_VoirPres
Prof --> UC_Horaires
Prof --> UC_Com
Prof --> UC_Docs
Prof --> UC_Chat

' Relations Surveillant
Surv --> UC_Auth
Surv --> UC_Presences
Surv --> UC_VoirPres
Surv --> UC_Incident
Surv --> UC_VoirPres
Surv --> UC_Horaires
Surv --> UC_Eleves

' Relations Finance
Finance --> UC_Auth
Finance --> UC_Frais
Finance --> UC_Paiements
Finance --> UC_Recu
Finance --> UC_Soldes
Finance --> UC_RapportFin
Finance --> UC_Eleves
Finance --> UC_PDF
Finance --> UC_XLSX

' Relations Discipline
Disc --> UC_Auth
Disc --> UC_Incident
Disc --> UC_GererInc
Disc --> UC_Sanction
Disc --> UC_Convoc
Disc --> UC_Eleves
Disc --> UC_Com

' Relations Eleve
Eleve --> UC_Auth
Eleve --> UC_VoirNotes
Eleve --> UC_VoirPres
Eleve --> UC_Horaires
Eleve --> UC_Soldes
Eleve --> UC_Docs
Eleve --> UC_Telecharger
Eleve --> UC_Com
Eleve --> UC_Clubs
Eleve --> UC_Chat
Eleve --> UC_Notif

' Relations Personnel
Pers --> UC_Auth
Pers --> UC_Com
Pers --> UC_Docs
Pers --> UC_Chat

' Includes/Extends
UC_Paiements ..> UC_Recu : <<include>>
UC_Notes ..> UC_Auth : <<include>>
UC_SaisirNotes ..> UC_Notes
UC_GererInc ..> UC_Incident
UC_Sanction ..> UC_GererInc : <<extend>>

@enduml
"""

# Écrire les fichiers
classes_file = OUTPUT_DIR / "diagramme_classes.puml"
activites_file = OUTPUT_DIR / "diagramme_activites.puml"
cas_file = OUTPUT_DIR / "diagramme_cas_utilisation.puml"

classes_file.write_text(diagram_classes, encoding='utf-8')
activites_file.write_text(diagram_activites, encoding='utf-8')
cas_file.write_text(diagram_cas_utilisation, encoding='utf-8')

print(f"✓ Fichiers PlantUML créés dans {OUTPUT_DIR}")
for f in [classes_file, activites_file, cas_file]:
    print(f"  - {f.name} ({f.stat().st_size} bytes)")

# Essayer de générer PNG avec plantuml si disponible
try:
    result = subprocess.run(['plantuml', '-version'], capture_output=True, timeout=5)
    if result.returncode == 0:
        print("\n✓ PlantUML trouvé, génération des images...")
        for puml in [classes_file, activites_file, cas_file]:
            subprocess.run(['plantuml', '-tpng', str(puml)], cwd=str(OUTPUT_DIR), timeout=60)
        print("✓ Images PNG générées")
    else:
        print("\n⚠ PlantUML non trouvé. Installation avec: apt install plantuml ou java -jar plantuml.jar")
except:
    print("\n⚠ PlantUML non installé. Images PNG non générées.")
    print("Pour générer les images :")
    print("  1. Installer PlantUML: sudo apt install plantuml")
    print("  2. Ou utiliser http://www.plantuml.com/plantuml/uml/ en copiant le contenu des .puml")

