#!/usr/bin/env python3
"""Génère le document Word des diagrammes UML pour My_School."""

from docx import Document
from docx.shared import Inches, Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.section import WD_ORIENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import os

doc = Document()

# ── Styles ──────────────────────────────────────────────
style = doc.styles['Normal']
font = style.font
font.name = 'Calibri'
font.size = Pt(11)

style_h1 = doc.styles['Heading 1']
style_h1.font.size = Pt(18)
style_h1.font.color.rgb = RGBColor(0, 51, 102)
style_h1.font.bold = True

style_h2 = doc.styles['Heading 2']
style_h2.font.size = Pt(14)
style_h2.font.color.rgb = RGBColor(0, 102, 153)
style_h2.font.bold = True

style_h3 = doc.styles['Heading 3']
style_h3.font.size = Pt(12)
style_h3.font.color.rgb = RGBColor(0, 102, 153)
style_h3.font.bold = True


def set_cell_shading(cell, color):
    shading_elm = OxmlElement('w:shd')
    shading_elm.set(qn('w:fill'), color)
    shading_elm.set(qn('w:val'), 'clear')
    cell._tc.get_or_add_tcPr().append(shading_elm)


def add_table(doc, headers, rows, col_widths=None):
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = 'Table Grid'
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    hdr_cells = table.rows[0].cells
    for i, h in enumerate(headers):
        hdr_cells[i].text = h
        for p in hdr_cells[i].paragraphs:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            for r in p.runs:
                r.bold = True
                r.font.size = Pt(10)
                r.font.color.rgb = RGBColor(255, 255, 255)
        set_cell_shading(hdr_cells[i], '003366')
    for ri, row in enumerate(rows):
        row_cells = table.rows[ri + 1].cells
        for ci, val in enumerate(row):
            row_cells[ci].text = str(val)
            for p in row_cells[ci].paragraphs:
                for r in p.runs:
                    r.font.size = Pt(9)
            if ri % 2 == 1:
                set_cell_shading(row_cells[ci], 'E8F0FE')
    if col_widths:
        for ri, row_obj in enumerate(table.rows):
            for ci, w in enumerate(col_widths):
                row_obj.cells[ci].width = Cm(w)
    return table


# ═══════════════════════════════════════════════════════════
# PAGE DE GARDE
# ═══════════════════════════════════════════════════════════
for _ in range(6):
    doc.add_paragraph()

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
run = p.add_run('MY_SCHOOL')
run.font.size = Pt(36)
run.font.bold = True
run.font.color.rgb = RGBColor(0, 51, 102)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
run = p.add_run('Système de Gestion Scolaire')
run.font.size = Pt(20)
run.font.color.rgb = RGBColor(0, 102, 153)

doc.add_paragraph()

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
run = p.add_run('Diagrammes UML')
run.font.size = Pt(24)
run.font.bold = True
run.font.color.rgb = RGBColor(0, 51, 102)

doc.add_paragraph()
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
run = p.add_run('Diagramme de Cas d\'Utilisation\nDiagramme de Classes\nDiagramme d\'Activité')
run.font.size = Pt(14)
run.font.color.rgb = RGBColor(80, 80, 80)

for _ in range(4):
    doc.add_paragraph()

info_lines = [
    'Technologies : PHP | PostgreSQL | HTML/CSS/JS',
    'Année Académique : 2025-2026',
]
for line in info_lines:
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run(line)
    run.font.size = Pt(12)
    run.font.color.rgb = RGBColor(100, 100, 100)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════
# TABLE DES MATIÈRES
# ═══════════════════════════════════════════════════════════
doc.add_heading('Table des Matières', level=1)
toc_items = [
    ('1', 'Introduction', 3),
    ('2', 'Diagramme de Cas d\'Utilisation', 4),
    ('2.1', 'Acteurs du Système', 4),
    ('2.2', 'Cas d\'Utilisation - Authentification', 5),
    ('2.3', 'Cas d\'Utilisation - Gestion des Utilisateurs (Admin)', 5),
    ('2.4', 'Cas d\'Utilisation - Gestion des Données (Admin)', 6),
    ('2.5', 'Cas d\'Utilisation - Gestion Documentaire', 6),
    ('2.6', 'Cas d\'Utilisation - Rapports et Statistiques', 7),
    ('2.7', 'Cas d\'Utilisation - Communication', 7),
    ('2.8', 'Cas d\'Utilisation - Interface Élève', 8),
    ('2.9', 'Cas d\'Utilisation - Interface Professeur', 8),
    ('2.10', 'Cas d\'Utilisation - Interface Personnel', 9),
    ('3', 'Diagramme de Classes', 10),
    ('3.1', 'Classes Principales', 10),
    ('3.2', 'Classes Métier', 11),
    ('3.3', 'Classes de Communication', 12),
    ('3.4', 'Classes de Sécurité', 12),
    ('3.5', 'Relations entre Classes', 13),
    ('4', 'Diagramme d\'Activité', 14),
    ('4.1', 'Authentification', 14),
    ('4.2', 'Inscription d\'un Élève', 15),
    ('4.3', 'Gestion des Notes', 15),
    ('4.4', 'Gestion des Présences', 16),
    ('4.5', 'Publication d\'un Horaire', 16),
    ('4.6', 'Gestion Documentaire', 17),
    ('4.7', 'Chat en Temps Réel', 17),
    ('4.8', 'Génération de Rapport', 18),
    ('5', 'Schéma de la Base de Données', 19),
]
for num, title, page in toc_items:
    p = doc.add_paragraph()
    indent = 0 if '.' not in num else 1
    p.paragraph_format.left_indent = Cm(indent * 1)
    run = p.add_run(f'{num}  {title}')
    run.font.size = Pt(11)
    if '.' not in num:
        run.bold = True

doc.add_page_break()

# ═══════════════════════════════════════════════════════════
# 1. INTRODUCTION
# ═══════════════════════════════════════════════════════════
doc.add_heading('1. Introduction', level=1)
doc.add_paragraph(
    'Ce document présente les diagrammes UML du projet My_School, un système de gestion '
    'scolaire complet développé avec PHP, PostgreSQL, HTML/CSS/JS. Le système offre deux '
    'interfaces principales : une interface d\'administration pour la gestion globale et une '
    'interface client pour les élèves, professeurs et personnel.'
)
doc.add_paragraph(
    'Les diagrammes UML inclus couvrent :'
)
items = [
    'Diagramme de Cas d\'Utilisation : interactions acteurs-système',
    'Diagramme de Classes : structure statique du système',
    'Diagramme d\'Activité : flux des processus métier clés',
]
for item in items:
    p = doc.add_paragraph(item, style='List Bullet')

doc.add_paragraph()
doc.add_heading('Technologies Utilisées', level=2)
add_table(doc,
    ['Composant', 'Technologie', 'Description'],
    [
        ['Frontend', 'HTML5, CSS3, JavaScript', 'Interface utilisateur responsive'],
        ['Backend', 'PHP 8.x', 'Logique applicative, API REST'],
        ['Base de données', 'PostgreSQL 15+', 'Stockage, procédures stockées'],
        ['Rapports', 'TCPDF / DOMPDF', 'Génération de PDF'],
        ['Export', 'PhpSpreadsheet', 'Export Excel/CSV'],
        ['Chat', 'WebSocket / AJAX', 'Communication temps réel'],
        ['Email', 'PHPMailer', 'Notifications par email'],
    ]
)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════
# 2. DIAGRAMME DE CAS D'UTILISATION
# ═══════════════════════════════════════════════════════════
doc.add_heading('2. Diagramme de Cas d\'Utilisation', level=1)
doc.add_paragraph(
    'Le diagramme de cas d\'utilisation décrit les interactions entre les acteurs du système '
    'et les fonctionnalités offertes. Le système identifie 4 acteurs principaux.'
)

# 2.1 Acteurs
doc.add_heading('2.1 Acteurs du Système', level=2)
add_table(doc,
    ['Acteur', 'Type', 'Description', 'Interface'],
    [
        ['Administrateur', 'Principal', 'Gère l\'ensemble du système, les utilisateurs, les données, les paramètres', 'Admin'],
        ['Élève', 'Secondaire', 'Consulte ses informations (horaires, notes, présences, communications)', 'Client'],
        ['Professeur', 'Secondaire', 'Gère les notes, présences, consulte les horaires, communique', 'Client'],
        ['Personnel', 'Secondaire', 'Consulte les horaires, communications, accède aux documents', 'Client'],
    ]
)

doc.add_paragraph()

# Diagramme textuel des acteurs
doc.add_heading('Représentation des Acteurs', level=3)
doc.add_paragraph(
    '┌─────────────────────────────────────────────────────────────────────┐\n'
    '│                    SYSTÈME MY_SCHOOL                               │\n'
    '│                                                                     │\n'
    '│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐             │\n'
    '│  │ Authentifi-  │  │ Gestion des  │  │  Gestion     │             │\n'
    '│  │ cation       │  │ Utilisateurs │  │  Données     │             │\n'
    '│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘             │\n'
    '│         │                 │                  │                      │\n'
    '│  ┌──────┴───────┐  ┌──────┴───────┐  ┌──────┴───────┐             │\n'
    '│  │  Gestion     │  │  Rapports    │  │ Communication│             │\n'
    '│  │ Documentaire │  │ & Stats      │  │ & Chat       │             │\n'
    '│  └──────────────┘  └──────────────┘  └──────────────┘             │\n'
    '└─────────────────────────────────────────────────────────────────────┘\n'
    '        ↑                    ↑                    ↑\n'
    '   ┌────┴────┐         ┌────┴────┐         ┌────┴────┐\n'
    '   │  Admin  │         │  Élève  │         │  Prof   │\n'
    '   └─────────┘         └─────────┘         └─────────┘\n'
    '                                        ┌─────────┐\n'
    '                                        │Personnel│\n'
    '                                        └─────────┘'
)

doc.add_page_break()

# ── 2.2 Cas d'Utilisation Authentification ──
doc.add_heading('2.2 Cas d\'Utilisation — Authentification', level=2)
doc.add_paragraph(
    'Ce cas d\'utilisation regroupe toutes les interactions liées à l\'identification '
    'et à la sécurité des accès.'
)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-01', 'S\'authentifier', 'Tous', 'L\'utilisateur saisit email/mot de passe pour accéder', 'Compte activé', 'Session créée, accès accordé'],
        ['UC-02', 'Se déconnecter', 'Tous', 'Fermeture sécurisée de la session', 'Session active', 'Session détruite'],
        ['UC-03', 'Réinitialiser mot de passe', 'Tous', 'Demande de réinitialisation par email', 'Compte existant', 'Nouveau mot de passe défini'],
        ['UC-04', 'Activer 2FA', 'Tous', 'Activation de l\'authentification à deux facteurs', 'Session active', '2FA activée'],
        ['UC-05', 'Vérifier code 2FA', 'Tous', 'Saisie du code de vérification', '2FA activée', 'Accès autorisé'],
        ['UC-06', 'Déconnexion automatique', 'Système', 'Fermeture après 30min d\'inactivité', 'Session inactive', 'Session détruite'],
        ['UC-07', 'Débloquer compte', 'Admin', 'Déblocage d\'un compte verrouillé', 'Compte verrouillé', 'Compte débloqué'],
    ]
)

doc.add_page_break()

# ── 2.3 Gestion Utilisateurs ──
doc.add_heading('2.3 Cas d\'Utilisation — Gestion des Utilisateurs (Admin)', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-08', 'Créer un utilisateur', 'Admin', 'Ajouter un nouvel utilisateur (élève, prof, personnel)', 'Admin connecté', 'Utilisateur créé'],
        ['UC-09', 'Modifier un utilisateur', 'Admin', 'Modifier les informations d\'un utilisateur', 'Utilisateur existant', 'Données mises à jour'],
        ['UC-10', 'Supprimer un utilisateur', 'Admin', 'Supprimer ou désactiver un compte', 'Utilisateur existant', 'Compte supprimé/désactivé'],
        ['UC-11', 'Consulter la liste des utilisateurs', 'Admin', 'Afficher tous les utilisateurs avec filtres', 'Admin connecté', 'Liste affichée'],
        ['UC-12', 'Gérer les rôles', 'Admin', 'Créer, modifier, supprimer des rôles', 'Admin connecté', 'Rôles gérés'],
        ['UC-13', 'Attribuer des permissions', 'Admin', 'Associer des permissions à un rôle', 'Rôle existant', 'Permissions attribuées'],
        ['UC-14', 'Réinitialiser mot de passe utilisateur', 'Admin', 'Forcer la réinitialisation du mot de passe', 'Utilisateur existant', 'Mot de passe réinitialisé'],
        ['UC-15', 'Consulter historique connexions', 'Admin', 'Voir l\'historique des connexions de tous les utilisateurs', 'Admin connecté', 'Historique affiché'],
        ['UC-16', 'Consulter journal des activités', 'Admin', 'Voir toutes les actions effectuées dans le système', 'Admin connecté', 'Journal affiché'],
        ['UC-17', 'Gérer les profils utilisateurs', 'Tous', 'Modifier son propre profil (photo, infos)', 'Session active', 'Profil mis à jour'],
    ]
)

doc.add_page_break()

# ── 2.4 Gestion Données ──
doc.add_heading('2.4 Cas d\'Utilisation — Gestion des Données (Admin)', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-18', 'Inscrire un élève', 'Admin', 'Enregistrer un nouvel élève (requête préparée)', 'Classe existante', 'Élève inscrit'],
        ['UC-19', 'Inscrire un professeur', 'Admin', 'Enregistrer un nouveau professeur', 'Admin connecté', 'Professeur inscrit'],
        ['UC-20', 'Inscrire du personnel', 'Admin', 'Enregistrer un membre du personnel', 'Admin connecté', 'Personnel inscrit'],
        ['UC-21', 'Modifier une donnée', 'Admin', 'Modifier un enregistrement (requête préparée)', 'Enregistrement existant', 'Donnée modifiée'],
        ['UC-22', 'Supprimer une donnée', 'Admin', 'Supprimer un enregistrement (requête préparée)', 'Enregistrement existant', 'Donnée supprimée'],
        ['UC-23', 'Consulter les données', 'Admin/Prof/Élève', 'Afficher les données selon les droits d\'accès', 'Session active', 'Données affichées'],
        ['UC-24', 'Recherche avancée', 'Admin/Prof/Élève', 'Rechercher avec critères multiples', 'Session active', 'Résultats affichés'],
        ['UC-25', 'Filtrer les données', 'Admin/Prof/Élève', 'Appliquer des filtres sur les listes', 'Session active', 'Données filtrées'],
        ['UC-26', 'Trier les données', 'Admin/Prof/Élève', 'Ordonner les données par colonne', 'Session active', 'Données triées'],
        ['UC-27', 'Archiver des données', 'Admin', 'Archiver des enregistrements obsolètes', 'Données existantes', 'Données archivées'],
        ['UC-28', 'Restaurer des données archivées', 'Admin', 'Restaurer des enregistrements archivés', 'Archives existantes', 'Données restaurées'],
        ['UC-29', 'Gérer les classes', 'Admin', 'CRUD des classes et niveaux', 'Admin connecté', 'Classes gérées'],
        ['UC-30', 'Gérer les matières', 'Admin', 'CRUD des matières par classe', 'Admin connecté', 'Matières gérées'],
    ]
)

doc.add_page_break()

# ── 2.5 Gestion Documentaire ──
doc.add_heading('2.5 Cas d\'Utilisation — Gestion Documentaire', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-31', 'Téléverser un fichier', 'Admin/Prof', 'Upload d\'un document (PDF, DOC, image)', 'Session active', 'Document stocké'],
        ['UC-32', 'Télécharger un fichier', 'Tous', 'Télécharger un document autorisé', 'Document accessible', 'Fichier téléchargé'],
        ['UC-33', 'Gérer les versions', 'Admin/Prof', 'Ajouter une nouvelle version d\'un document', 'Document existant', 'Version créée'],
        ['UC-34', 'Partager un document', 'Admin/Prof', 'Partager un document avec des utilisateurs', 'Document existant', 'Document partagé'],
        ['UC-35', 'Rechercher un document', 'Tous', 'Rechercher par nom, type, auteur', 'Session active', 'Résultats affichés'],
        ['UC-36', 'Archiver un document', 'Admin', 'Archiver un document obsolète', 'Document existant', 'Document archivé'],
    ]
)

doc.add_page_break()

# ── 2.6 Rapports et Statistiques ──
doc.add_heading('2.6 Cas d\'Utilisation — Rapports et Statistiques', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-37', 'Générer un rapport PDF', 'Admin/Prof', 'Créer un rapport au format PDF', 'Données disponibles', 'PDF généré'],
        ['UC-38', 'Exporter en Excel', 'Admin/Prof', 'Exporter des données en fichier Excel', 'Données disponibles', 'Fichier Excel créé'],
        ['UC-39', 'Consulter le tableau de bord', 'Admin/Prof/Élève', 'Afficher les statistiques et KPIs', 'Session active', 'Dashboard affiché'],
        ['UC-40', 'Générer rapport périodique', 'Admin', 'Créer un rapport pour une période donnée', 'Données de la période', 'Rapport généré'],
        ['UC-41', 'Créer rapport personnalisé', 'Admin', 'Définir des critères de rapport custom', 'Session active', 'Rapport personnalisé'],
        ['UC-42', 'Analyse comparative', 'Admin', 'Comparer des données entre périodes/classes', 'Données historiques', 'Analyse affichée'],
        ['UC-43', 'Prévisions statistiques', 'Admin', 'Projeter les tendances futures', 'Données historiques', 'Prévisions affichées'],
    ]
)

doc.add_page_break()

# ── 2.7 Communication ──
doc.add_heading('2.7 Cas d\'Utilisation — Communication', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-44', 'Envoyer notification email', 'Admin/Système', 'Envoyer un email de notification', 'Email configuré', 'Email envoyé'],
        ['UC-45', 'Recevoir notification interne', 'Tous', 'Recevoir une notification dans l\'application', 'Session active', 'Notification reçue'],
        ['UC-46', 'Publier une communication', 'Admin', 'Créer et publier un message ciblé', 'Admin connecté', 'Communication publiée'],
        ['UC-47', 'Consulter les communications', 'Tous', 'Lire les communications destinées à son rôle', 'Session active', 'Communications affichées'],
        ['UC-48', 'Participer au chat', 'Tous', 'Envoyer et recevoir des messages en temps réel', 'Session active', 'Message envoyé/reçu'],
        ['UC-49', 'Envoyer un document', 'Admin/Prof', 'Joindre un document à un message', 'Document existant', 'Document envoyé'],
        ['UC-50', 'Recevoir alerte automatique', 'Tous', 'Recevoir une alerte système (ex: note publiée)', 'Conditions remplies', 'Alerte reçue'],
        ['UC-51', 'Ajouter un commentaire', 'Admin/Prof/Élève', 'Commenter un document ou une communication', 'Document accessible', 'Commentaire ajouté'],
    ]
)

doc.add_page_break()

# ── 2.8 Interface Élève ──
doc.add_heading('2.8 Cas d\'Utilisation — Interface Élève', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-52', 'Consulter emploi du temps', 'Élève', 'Voir l\'horaire de sa classe', 'Élève connecté', 'Horaire affiché'],
        ['UC-53', 'Consulter ses notes', 'Élève', 'Voir ses notes et moyennes', 'Élève connecté', 'Notes affichées'],
        ['UC-54', 'Consulter ses présences', 'Élève', 'Voir l\'historique de ses présences', 'Élève connecté', 'Présences affichées'],
        ['UC-55', 'Rejoindre un club', 'Élève', 'S\'inscrire à un club scolaire', 'Élève connecté', 'Inscription confirmée'],
        ['UC-56', 'Consulter les communications', 'Élève', 'Lire les annonces et messages', 'Élève connecté', 'Communications lues'],
        ['UC-57', 'Télécharger un document', 'Élève', 'Télécharger un document partagé', 'Document accessible', 'Fichier téléchargé'],
        ['UC-58', 'Modifier son profil', 'Élève', 'Mettre à jour ses informations personnelles', 'Élève connecté', 'Profil mis à jour'],
    ]
)

doc.add_page_break()

# ── 2.9 Interface Professeur ──
doc.add_heading('2.9 Cas d\'Utilisation — Interface Professeur', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-59', 'Consulter emploi du temps', 'Prof', 'Voir son horaire personnel', 'Prof connecté', 'Horaire affiché'],
        ['UC-60', 'Saisir les notes', 'Prof', 'Enregistrer les notes de ses élèves (requête préparée)', 'Prof connecté', 'Notes enregistrées'],
        ['UC-61', 'Modifier les notes', 'Prof', 'Corriger/modifier une note existante', 'Note existante', 'Note modifiée'],
        ['UC-62', 'Gérer les présences', 'Prof', 'Marquer les présences/absences de ses classes', 'Prof connecté', 'Présences enregistrées'],
        ['UC-63', 'Consulter les notes saisies', 'Prof', 'Voir les notes qu\'il a saisies', 'Prof connecté', 'Notes affichées'],
        ['UC-64', 'Publier une communication', 'Prof', 'Envoyer un message à ses classes', 'Prof connecté', 'Communication publiée'],
        ['UC-65', 'Envoyer des alertes', 'Prof', 'Alerter les élèves/parents', 'Prof connecté', 'Alerte envoyée'],
    ]
)

doc.add_page_break()

# ── 2.10 Interface Personnel ──
doc.add_heading('2.10 Cas d\'Utilisation — Interface Personnel', level=2)
add_table(doc,
    ['ID', 'Cas d\'Utilisation', 'Acteur(s)', 'Description', 'Préconditions', 'Postconditions'],
    [
        ['UC-66', 'Consulter emploi du temps', 'Personnel', 'Voir les horaires de l\'établissement', 'Personnel connecté', 'Horaire affiché'],
        ['UC-67', 'Consulter les communications', 'Personnel', 'Lire les annonces', 'Personnel connecté', 'Communications lues'],
        ['UC-68', 'Participer au chat', 'Personnel', 'Communiquer avec les autres membres', 'Session active', 'Messages échangés'],
        ['UC-69', 'Consulter les documents', 'Personnel', 'Accéder aux documents partagés', 'Document accessible', 'Document consulté'],
    ]
)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════
# 3. DIAGRAMME DE CLASSES
# ═══════════════════════════════════════════════════════════
doc.add_heading('3. Diagramme de Classes', level=1)
doc.add_paragraph(
    'Le diagramme de classes modélise la structure statique du système. Il identifie les '
    'classes, leurs attributs, méthodes et les relations entre elles.'
)

# 3.1 Classes Principales (Entités métier)
doc.add_heading('3.1 Classes Principales — Entités Métier', level=2)

doc.add_heading('Classe Utilisateur', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['nom', 'VARCHAR(100)', 'NOT NULL', 'Nom de famille'],
        ['prenom', 'VARCHAR(100)', 'NOT NULL', 'Prénom'],
        ['email', 'VARCHAR(150)', 'UNIQUE, NOT NULL', 'Email (identifiant de connexion)'],
        ['password_hash', 'VARCHAR(255)', 'NOT NULL', 'Mot de passe hashé (bcrypt)'],
        ['role', 'ENUM', 'NOT NULL', 'admin, eleve, prof, personnel'],
        ['avatar', 'VARCHAR(255)', 'NULL', 'Chemin de la photo de profil'],
        ['actif', 'BOOLEAN', 'DEFAULT TRUE', 'Compte actif ou non'],
        ['deux_facteurs', 'BOOLEAN', 'DEFAULT FALSE', '2FA activée'],
        ['derniere_connexion', 'TIMESTAMP', 'NULL', 'Date de dernière connexion'],
        ['tentatives_connexion', 'INT', 'DEFAULT 0', 'Nombre de tentatives échouées'],
        ['compte_verrouille', 'BOOLEAN', 'DEFAULT FALSE', 'Compte verrouillé'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
        ['updated_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de mise à jour'],
    ]
)
doc.add_paragraph()
p = doc.add_paragraph()
run = p.add_run('Méthodes : ')
run.bold = True
p.add_run('login(), logout(), resetPassword(), enable2FA(), disable2FA(), '
          'lockAccount(), unlockAccount(), updateProfile(), getHistory()')

doc.add_paragraph()

doc.add_heading('Classe Élève', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['user_id', 'INT', 'FK → Utilisateur, UNIQUE', 'Lien vers compte utilisateur'],
        ['matricule', 'VARCHAR(20)', 'UNIQUE, NOT NULL', 'Matricule de l\'élève'],
        ['nom', 'VARCHAR(100)', 'NOT NULL', 'Nom'],
        ['prenom', 'VARCHAR(100)', 'NOT NULL', 'Prénom'],
        ['date_naissance', 'DATE', 'NOT NULL', 'Date de naissance'],
        ['sexe', 'ENUM', 'NOT NULL', 'M, F'],
        ['classe_id', 'INT', 'FK → Classe', 'Classe assignée'],
        ['parent_nom', 'VARCHAR(200)', 'NOT NULL', 'Nom du parent/tuteur'],
        ['parent_tel', 'VARCHAR(20)', 'NOT NULL', 'Téléphone du parent'],
        ['adresse', 'TEXT', 'NULL', 'Adresse complète'],
        ['photo', 'VARCHAR(255)', 'NULL', 'Chemin de la photo'],
        ['statut', 'ENUM', 'DEFAULT actif', 'actif, inactif, archive'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)
doc.add_paragraph()
p = doc.add_paragraph()
run = p.add_run('Méthodes : ')
run.bold = True
p.add_run('getNotes(), getPresences(), getHoraires(), getSalaire(), '
          'getClasse(), getClubMembre()')

doc.add_paragraph()

doc.add_heading('Classe Professeur', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['user_id', 'INT', 'FK → Utilisateur, UNIQUE', 'Lien vers compte utilisateur'],
        ['matricule', 'VARCHAR(20)', 'UNIQUE, NOT NULL', 'Matricule du professeur'],
        ['nom', 'VARCHAR(100)', 'NOT NULL', 'Nom'],
        ['prenom', 'VARCHAR(100)', 'NOT NULL', 'Prénom'],
        ['specialite', 'VARCHAR(100)', 'NOT NULL', 'Domaine de spécialité'],
        ['tel', 'VARCHAR(20)', 'NOT NULL', 'Numéro de téléphone'],
        ['email', 'VARCHAR(150)', 'UNIQUE, NOT NULL', 'Email professionnel'],
        ['adresse', 'TEXT', 'NULL', 'Adresse'],
        ['photo', 'VARCHAR(255)', 'NULL', 'Chemin de la photo'],
        ['statut', 'ENUM', 'DEFAULT actif', 'actif, inactif, archive'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)
doc.add_paragraph()
p = doc.add_paragraph()
run = p.add_run('Méthodes : ')
run.bold = True
p.add_run('getMatieres(), getHoraires(), saisirNotes(), '
          'marquerPresences(), getClasses()')

doc.add_paragraph()

doc.add_heading('Classe Personnel', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['user_id', 'INT', 'FK → Utilisateur, UNIQUE', 'Lien vers compte utilisateur'],
        ['matricule', 'VARCHAR(20)', 'UNIQUE, NOT NULL', 'Matricule'],
        ['nom', 'VARCHAR(100)', 'NOT NULL', 'Nom'],
        ['prenom', 'VARCHAR(100)', 'NOT NULL', 'Prénom'],
        ['fonction', 'VARCHAR(100)', 'NOT NULL', 'Fonction occupée'],
        ['tel', 'VARCHAR(20)', 'NOT NULL', 'Téléphone'],
        ['email', 'VARCHAR(150)', 'UNIQUE, NOT NULL', 'Email'],
        ['statut', 'ENUM', 'DEFAULT actif', 'actif, inactif, archive'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)

doc.add_page_break()

# 3.2 Classes Métier
doc.add_heading('3.2 Classes Métier', level=2)

doc.add_heading('Classe Classe', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['nom_classe', 'VARCHAR(50)', 'NOT NULL', 'Nom de la classe (ex: 6ème A)'],
        ['niveau', 'VARCHAR(50)', 'NOT NULL', 'Niveau d\'étude'],
        ['section', 'VARCHAR(50)', 'NULL', 'Section (A, B, C...)'],
        ['annee_scolaire', 'VARCHAR(9)', 'NOT NULL', 'Année scolaire (2025-2026)'],
        ['capacite', 'INT', 'DEFAULT 50', 'Capacité maximale'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)
doc.add_paragraph()
p = doc.add_paragraph()
run = p.add_run('Méthodes : ')
run.bold = True
p.add_run('getEleves(), getHoraires(), getMatieres(), getEffectif()')

doc.add_paragraph()

doc.add_heading('Classe Matière', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['nom_matiere', 'VARCHAR(100)', 'NOT NULL', 'Nom de la matière'],
        ['code', 'VARCHAR(10)', 'UNIQUE, NOT NULL', 'Code matière (MATH, FR, etc.)'],
        ['coefficient', 'DECIMAL(3,1)', 'NOT NULL', 'Coefficient de la matière'],
        ['classe_id', 'INT', 'FK → Classe', 'Classe concernée'],
        ['prof_id', 'INT', 'FK → Professeur', 'Professeur assigné'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe Horaire', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['classe_id', 'INT', 'FK → Classe', 'Classe concernée'],
        ['matiere_id', 'INT', 'FK → Matière', 'Matière enseignée'],
        ['prof_id', 'INT', 'FK → Professeur', 'Professeur en charge'],
        ['jour', 'VARCHAR(10)', 'NOT NULL', 'Jour de la semaine'],
        ['heure_debut', 'TIME', 'NOT NULL', 'Heure de début'],
        ['heure_fin', 'TIME', 'NOT NULL', 'Heure de fin'],
        ['salle', 'VARCHAR(20)', 'NOT NULL', 'Numéro de salle'],
        ['statut', 'ENUM', 'DEFAULT brouillon', 'brouillon, publié, annulé'],
        ['annee_scolaire', 'VARCHAR(9)', 'NOT NULL', 'Année scolaire'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)
doc.add_paragraph()
p = doc.add_paragraph()
run = p.add_run('Méthodes : ')
run.bold = True
p.add_run('publier(), annuler(), getConflits(), getProfDispo()')

doc.add_paragraph()

doc.add_heading('Classe Note', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['eleve_id', 'INT', 'FK → Élève', 'Élève concerné'],
        ['matiere_id', 'INT', 'FK → Matière', 'Matière'],
        ['prof_id', 'INT', 'FK → Professeur', 'Professeur ayant saisie la note'],
        ['note', 'DECIMAL(4,2)', 'NOT NULL', 'Note obtenue (sur 20)'],
        ['type_evaluation', 'ENUM', 'NOT NULL', 'devoir, composition, interrogation'],
        ['date_evaluation', 'DATE', 'NOT NULL', 'Date de l\'évaluation'],
        ['appreciation', 'TEXT', 'NULL', 'Commentaire du professeur'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de saisie'],
    ]
)
doc.add_paragraph()
p = doc.add_paragraph()
run = p.add_run('Méthodes : ')
run.bold = True
p.add_run('getMoyenne(), calculerMoyenneClasse(), getMatiere()')

doc.add_paragraph()

doc.add_heading('Classe Présence', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['eleve_id', 'INT', 'FK → Élève', 'Élève concerné'],
        ['prof_id', 'INT', 'FK → Professeur', 'Professeur ayant marqué'],
        ['date_presence', 'DATE', 'NOT NULL', 'Date de la présence'],
        ['heure_arrivee', 'TIME', 'NULL', 'Heure d\'arrivée'],
        ['statut', 'ENUM', 'NOT NULL', 'present, absent, retard, excusé'],
        ['motif', 'TEXT', 'NULL', 'Motif en cas d\'absence/retard'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date d\'enregistrement'],
    ]
)

doc.add_page_break()

# 3.3 Classes de Communication
doc.add_heading('3.3 Classes de Communication et Documents', level=2)

doc.add_heading('Classe Communication', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['titre', 'VARCHAR(200)', 'NOT NULL', 'Titre de la communication'],
        ['contenu', 'TEXT', 'NOT NULL', 'Contenu du message'],
        ['auteur_id', 'INT', 'FK → Utilisateur', 'Auteur'],
        ['destinataires', 'TEXT', 'NOT NULL', 'Rôles/classes destinataires'],
        ['type', 'ENUM', 'NOT NULL', 'annonce, alerte, info'],
        ['date_publication', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de publication'],
        ['statut', 'ENUM', 'NOT NULL', 'brouillon, publié, archivé'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe Document', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['nom_fichier', 'VARCHAR(255)', 'NOT NULL', 'Nom du fichier'],
        ['fichier_path', 'VARCHAR(500)', 'NOT NULL', 'Chemin de stockage'],
        ['type_fichier', 'VARCHAR(50)', 'NOT NULL', 'Type MIME'],
        ['taille', 'INT', 'NOT NULL', 'Taille en octets'],
        ['auteur_id', 'INT', 'FK → Utilisateur', 'Auteur du document'],
        ['description', 'TEXT', 'NULL', 'Description du document'],
        ['statut', 'ENUM', 'DEFAULT actif', 'actif, archivé'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe MessageChat', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['sender_id', 'INT', 'FK → Utilisateur', 'Expéditeur'],
        ['receiver_id', 'INT', 'FK → Utilisateur, NULL', 'Destinataire (NULL = global)'],
        ['message', 'TEXT', 'NOT NULL', 'Contenu du message'],
        ['lu', 'BOOLEAN', 'DEFAULT FALSE', 'Message lu ou non'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date d\'envoi'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe Notification', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['user_id', 'INT', 'FK → Utilisateur', 'Destinataire'],
        ['titre', 'VARCHAR(200)', 'NOT NULL', 'Titre de la notification'],
        ['message', 'TEXT', 'NOT NULL', 'Contenu'],
        ['type', 'ENUM', 'NOT NULL', 'info, alerte, succes, erreur'],
        ['lu', 'BOOLEAN', 'DEFAULT FALSE', 'Notification lue'],
        ['lien', 'VARCHAR(500)', 'NULL', 'Lien de redirection'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe Club', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['nom_club', 'VARCHAR(100)', 'NOT NULL', 'Nom du club'],
        ['description', 'TEXT', 'NULL', 'Description'],
        ['prof_responsable_id', 'INT', 'FK → Professeur', 'Professeur responsable'],
        ['logo', 'VARCHAR(255)', 'NULL', 'Logo du club'],
        ['date_creation', 'DATE', 'NOT NULL', 'Date de création du club'],
        ['statut', 'ENUM', 'DEFAULT actif', 'actif, inactif'],
    ]
)

doc.add_page_break()

# 3.4 Classes de Sécurité
doc.add_heading('3.4 Classes de Sécurité et Administration', level=2)

doc.add_heading('Classe Session', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['user_id', 'INT', 'FK → Utilisateur', 'Utilisateur'],
        ['token', 'VARCHAR(255)', 'UNIQUE, NOT NULL', 'Token de session'],
        ['ip_address', 'VARCHAR(45)', 'NOT NULL', 'Adresse IP'],
        ['user_agent', 'TEXT', 'NOT NULL', 'Navigateur/utilisateur'],
        ['expires_at', 'TIMESTAMP', 'NOT NULL', 'Date d\'expiration'],
        ['created_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de création'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe HistoriqueConnexion', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['user_id', 'INT', 'FK → Utilisateur', 'Utilisateur'],
        ['ip_address', 'VARCHAR(45)', 'NOT NULL', 'Adresse IP'],
        ['user_agent', 'TEXT', 'NOT NULL', 'Navigateur'],
        ['date_connexion', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de connexion'],
        ['succes', 'BOOLEAN', 'NOT NULL', 'Connexion réussie ou échouée'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe JournalActivite', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['user_id', 'INT', 'FK → Utilisateur', 'Utilisateur'],
        ['action', 'VARCHAR(100)', 'NOT NULL', 'Action effectuée'],
        ['details', 'TEXT', 'NULL', 'Détails de l\'action'],
        ['ip_address', 'VARCHAR(45)', 'NOT NULL', 'Adresse IP'],
        ['date_action', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de l\'action'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe Archive', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['table_source', 'VARCHAR(50)', 'NOT NULL', 'Table d\'origine'],
        ['record_id', 'INT', 'NOT NULL', 'ID de l\'enregistrement'],
        ['donnees_json', 'JSONB', 'NOT NULL', 'Données archivées en JSON'],
        ['date_archive', 'TIMESTAMP', 'DEFAULT NOW()', 'Date d\'archivage'],
        ['archive_par', 'INT', 'FK → Utilisateur', 'Qui a archivé'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe Sauvegarde', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['nom_fichier', 'VARCHAR(255)', 'NOT NULL', 'Nom du fichier sauvegarde'],
        ['chemin', 'VARCHAR(500)', 'NOT NULL', 'Chemin de stockage'],
        ['taille', 'INT', 'NOT NULL', 'Taille en octets'],
        ['type', 'ENUM', 'NOT NULL', 'auto, manuelle'],
        ['date_sauvegarde', 'TIMESTAMP', 'DEFAULT NOW()', 'Date de sauvegarde'],
        ['effectuee_par', 'INT', 'FK → Utilisateur, NULL', 'Qui a lancé la sauvegarde'],
    ]
)

doc.add_paragraph()

doc.add_heading('Classe ParametreSysteme', level=3)
add_table(doc,
    ['Attribut', 'Type', 'Contrainte', 'Description'],
    [
        ['id', 'INT', 'PK, AUTO_INCREMENT', 'Identifiant unique'],
        ['cle', 'VARCHAR(100)', 'UNIQUE, NOT NULL', 'Clé du paramètre'],
        ['valeur', 'TEXT', 'NOT NULL', 'Valeur du paramètre'],
        ['description', 'TEXT', 'NULL', 'Description du paramètre'],
        ['updated_at', 'TIMESTAMP', 'DEFAULT NOW()', 'Dernière mise à jour'],
    ]
)

doc.add_page_break()

# 3.5 Relations
doc.add_heading('3.5 Relations entre Classes', level=2)
doc.add_paragraph(
    'Le diagramme de classes définit les relations suivantes :'
)

add_table(doc,
    ['Relation', 'Cardinalité', 'Description'],
    [
        ['Utilisateur ↔ Élève', '1..1', 'Un utilisateur (rôle élève) a un seul profil élève'],
        ['Utilisateur ↔ Professeur', '1..1', 'Un utilisateur (rôle prof) a un seul profil professeur'],
        ['Utilisateur ↔ Personnel', '1..1', 'Un utilisateur (rôle personnel) a un seul profil personnel'],
        ['Classe ↔ Élève', '1..N', 'Une classe contient plusieurs élèves'],
        ['Classe ↔ Matière', '1..N', 'Une classe a plusieurs matières'],
        ['Professeur ↔ Matière', '1..N', 'Un prof enseigne plusieurs matières'],
        ['Classe ↔ Horaire', '1..N', 'Une classe a plusieurs créneaux horaires'],
        ['Matière ↔ Horaire', '1..N', 'Une matière a plusieurs créneaux'],
        ['Professeur ↔ Horaire', '1..N', 'Un prof a plusieurs créneaux'],
        ['Élève ↔ Note', '1..N', 'Un élève a plusieurs notes'],
        ['Matière ↔ Note', '1..N', 'Une matière a plusieurs notes'],
        ['Professeur ↔ Note', '1..N', 'Un prof saisit plusieurs notes'],
        ['Élève ↔ Présence', '1..N', 'Un élève a plusieurs présences'],
        ['Professeur ↔ Présence', '1..N', 'Un prof marque plusieurs présences'],
        ['Professeur ↔ Club', '1..N', 'Un prof est responsable d\'un ou plusieurs clubs'],
        ['Club ↔ Élève', 'M..N', 'Via table associative club_membres'],
        ['Utilisateur ↔ Communication', '1..N', 'Un utilisateur publie plusieurs communications'],
        ['Utilisateur ↔ Document', '1..N', 'Un utilisateur upload plusieurs documents'],
        ['Document ↔ DocumentVersion', '1..N', 'Un document a plusieurs versions'],
        ['Document ↔ Utilisateur (partage)', 'M..N', 'Via table document_partages'],
        ['Utilisateur ↔ Notification', '1..N', 'Un utilisateur reçoit plusieurs notifications'],
        ['Utilisateur ↔ MessageChat', '1..N', 'Un utilisateur envoie plusieurs messages'],
        ['Utilisateur ↔ Session', '1..N', 'Un utilisateur a plusieurs sessions'],
        ['Utilisateur ↔ HistoriqueConnexion', '1..N', 'Un utilisateur a plusieurs connexions'],
        ['Utilisateur ↔ JournalActivite', '1..N', 'Un utilisateur a plusieurs actions loguées'],
        ['Utilisateur ↔ Archive', '1..N', 'Un utilisateur archive plusieurs enregistrements'],
    ]
)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════
# 4. DIAGRAMME D'ACTIVITÉ
# ═══════════════════════════════════════════════════════════
doc.add_heading('4. Diagramme d\'Activité', level=1)
doc.add_paragraph(
    'Les diagrammes d\'activité décrivent le flux des processus métier clés du système.'
)

# 4.1 Authentification
doc.add_heading('4.1 Authentification', level=2)
doc.add_paragraph(
    'Ce diagramme illustre le processus complet d\'authentification d\'un utilisateur.'
)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────────┐\n'
    '│ Afficher page de login  │\n'
    '└────────────┬────────────┘\n'
    '             │\n'
    '             ▼\n'
    '┌─────────────────────────┐\n'
    '│ Saisir email/mot de    │\n'
    '│ passe                   │\n'
    '└────────────┬────────────┘\n'
    '             │\n'
    '             ▼\n'
    '┌─────────────────────────┐\n'
    '│ [Vérifier] Compte       │\n'
    '│ existe ?                │\n'
    '└────┬───────────────┬────┘\n'
    '     │ Oui           │ Non\n'
    '     ▼               ▼\n'
    '┌──────────┐  ┌──────────────┐\n'
    '│ Compte   │  │ Afficher     │\n'
    '│ verrouillé│  │ erreur       │──→ Fin\n'
    '│ ?        │  └──────────────┘\n'
    '└──┬───┬───┘\n'
    '   │Oui│Non\n'
    '   ▼   ▼\n'
    '┌───────┐ ┌──────────────────┐\n'
    '│Afficher│ │ Vérifier mot de  │\n'
    '│erreur │ │ passe (bcrypt)   │\n'
    '│compte  │ └───┬─────────┬───┘\n'
    '│verrouillé│   │ Valide   │ Invalide\n'
    '└───────┘   │         ▼\n'
    '            ▼    ┌──────────┐\n'
    '┌──────────┐     │Incrémenter│\n'
    '│Vérifier  │     │tentatives │\n'
    '│2FA activée│    │≥ 5 ?      │\n'
    '└──┬────┬──┘     └──┬────┬───┘\n'
    '   │Oui │Non        │Oui │Non\n'
    '   ▼    ▼           ▼    ▼\n'
    '┌──────┐ ┌─────┐ ┌──────┐\n'
    '│Demande│ │Créer│ │Verrou-│\n'
    '│code   │ │session││iller  │\n'
    '│2FA    │ └──┬──┘ │compte │\n'
    '└──┬───┘    │    └──┬───┘\n'
    '   ▼        ▼       ▼\n'
    '┌──────────┐  ┌──────────┐\n'
    '│Vérifier  │  │ Journal  │\n'
    '│code OTP  │  │ connexion│\n'
    '└──┬────┬──┘  └──────────┘\n'
    '   │OK  │Erreur\n'
    '   ▼    ▼\n'
    '┌──────┐┌─────────┐\n'
    '│Créer ││Afficher │\n'
    '│session││erreur   │──→ Fin\n'
    '└──┬───┘└─────────┘\n'
    '   │\n'
    '   ▼\n'
    '┌──────────────────┐\n'
    '│ Rediriger selon   │\n'
    '│ le rôle (Admin/   │\n'
    '│ Élève/Prof/       │\n'
    '│ Personnel)        │\n'
    '└────────┬─────────┘\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# 4.2 Inscription Élève
doc.add_heading('4.2 Inscription d\'un Élève', level=2)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────┐\n'
    '│ Admin accède à      │\n'
    '│ "Nouvel élève"      │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Remplir formulaire  │\n'
    '│ (nom, prénom, DOB,  │\n'
    '│ sexe, classe,       │\n'
    '│ parent, adresse)    │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ [Valider] Formulaire│\n'
    '│ côté serveur        │\n'
    '└────┬────────────┬───┘\n'
    '     │ Valide     │ Invalide\n'
    '     ▼            ▼\n'
    '┌──────────┐  ┌──────────┐\n'
    '│ Générer  │  │ Afficher │\n'
    '│ matricule│  │ erreurs  │──→ Fin\n'
    '└────┬─────┘  └──────────┘\n'
    '     │\n'
    '     ▼\n'
    '┌─────────────────────┐\n'
    '│ Créer compte        │\n'
    '│ utilisateur (email  │\n'
    '│ temp + mdp temp)    │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ INSERT eleve        │\n'
    '│ (requête préparée)  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ INSERT historique   │\n'
    '│ activité            │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Envoyer email de    │\n'
    '│ bienvenue           │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Afficher message    │\n'
    '│ succès              │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# 4.3 Gestion des Notes
doc.add_heading('4.3 Gestion des Notes (Professeur)', level=2)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────┐\n'
    '│ Prof accède à       │\n'
    '│ "Saisie des notes"  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Sélectionner classe │\n'
    '│ et matière          │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Afficher liste des  │\n'
    '│ élèves de la classe │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Choisir type        │\n'
    '│ d\'évaluation        │\n'
    '│ (devoir/composition/│\n'
    '│ interrogation)      │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Saisir les notes    │\n'
    '│ pour chaque élève   │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ [Valider] Notes     │\n'
    '│ côté serveur        │\n'
    '└────┬────────────┬───┘\n'
    '     │ Valide     │ Invalide\n'
    '     ▼            ▼\n'
    '┌──────────┐  ┌──────────┐\n'
    '│ Procédure│  │ Afficher │\n'
    '│ stockée: │  │ erreurs  │──→ Fin\n'
    '│ INSERT   │  └──────────┘\n'
    '│ notes    │\n'
    '│ (requête │\n'
    '│ préparée)│\n'
    '└────┬─────┘\n'
    '     │\n'
    '     ▼\n'
    '┌─────────────────────┐\n'
    '│ Enregistrer dans    │\n'
    '│ journal d\'activité  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Notifier les élèves │\n'
    '│ (notification interne│\n'
    '│ + email optionnel)  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Afficher succès     │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# 4.4 Gestion des Présences
doc.add_heading('4.4 Gestion des Présences', level=2)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────┐\n'
    '│ Prof accède à       │\n'
    '│ "Gestion présences" │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Sélectionner classe │\n'
    '│ et date             │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Afficher liste des  │\n'
    '│ élèves              │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Pour chaque élève : │\n'
    '│ Marquer Présent /   │\n'
    '│ Absent / Retard /   │\n'
    '│ Excusé              │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ [Enregistrer]       │\n'
    '└────┬────────────┬───┘\n'
    '     │ Succès     │ Erreur\n'
    '     ▼            ▼\n'
    '┌──────────┐  ┌──────────┐\n'
    '│ INSERT   │  │ Afficher │\n'
    '│ presences│  │ erreur   │──→ Fin\n'
    '│ (requête │  └──────────┘\n'
    '│ préparée)│\n'
    '└────┬─────┘\n'
    '     │\n'
    '     ▼\n'
    '┌─────────────────────┐\n'
    '│ Procédure stockée : │\n'
    '│ Calculer taux de    │\n'
    '│ présence par élève  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Si absences ≥ seuil │\n'
    '│ → Alerte automatique│\n'
    '│ (parent + admin)    │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# 4.5 Publication Horaire
doc.add_heading('4.5 Publication d\'un Horaire', level=2)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────┐\n'
    '│ Admin accède à      │\n'
    '│ "Gestion horaires"  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Créer/Modifier un   │\n'
    '│ créneau horaire     │\n'
    '│ (classe, matière,   │\n'
    '│ prof, jour, heure,  │\n'
    '│ salle)              │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Vérifier conflits   │\n'
    '│ (même prof/salle/   │\n'
    '│ classe au même      │\n'
    '│ créneau)            │\n'
    '└────┬────────────┬───┘\n'
    '     │ Aucun      │ Conflit\n'
    '     │ conflit    │ détecté\n'
    '     ▼            ▼\n'
    '┌──────────┐  ┌──────────┐\n'
    '│ INSERT/  │  │ Afficher │\n'
    '│ UPDATE   │  │ conflit  │──→ Fin\n'
    '│ horaire  │  │ proposer │\n'
    '│ (requête │  │ solution │\n'
    '│ préparée)│  └──────────┘\n'
    '└────┬─────┘\n'
    '     │\n'
    '     ▼\n'
    '┌─────────────────────┐\n'
    '│ [Publier] l\'horaire │\n'
    '│ statut = publié     │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Notifier tous les   │\n'
    '│ concernés (élèves,  │\n'
    '│ profs, personnel)   │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# 4.6 Gestion Documentaire
doc.add_heading('4.6 Gestion Documentaire', level=2)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────┐\n'
    '│ Utilisateur clique  │\n'
    '│ "Téléverser"        │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Sélectionner fichier│\n'
    '│ (type, taille)      │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Valider : type      │\n'
    '│ autorisé ? taille ≤ │\n'
    '│ max ?               │\n'
    '└────┬────────────┬───┘\n'
    '     │ OK         │ Non\n'
    '     ▼            ▼\n'
    '┌──────────┐  ┌──────────┐\n'
    '│ Renommer │  │ Afficher │\n'
    '│ fichier  │  │ erreur   │──→ Fin\n'
    '│ (UUID)   │  └──────────┘\n'
    '└────┬─────┘\n'
    '     │\n'
    '     ▼\n'
    '┌─────────────────────┐\n'
    '│ Déplacer vers       │\n'
    '│ dossier uploads     │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ INSERT document     │\n'
    '│ (requête préparée)  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Si nouvelle version:│\n'
    '│ INSERT version +    │\n'
    '│ UPDATE document     │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Journal d\'activité  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# 4.7 Chat
doc.add_heading('4.7 Chat en Temps Réel', level=2)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────┐\n'
    '│ Utilisateur ouvre   │\n'
    '│ le chat             │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Connexion WebSocket │\n'
    '│ / Initier polling   │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Attendre nouveau    │←──────────┐\n'
    '│ message             │           │\n'
    '└────┬────────────┬───┘           │\n'
    '     │ Message    │ Aucun         │\n'
    '     │ reçu       │ message       │\n'
    '     ▼            │              │\n'
    '┌──────────┐      │              │\n'
    '│ Afficher │      │              │\n'
    '│ message  │      │              │\n'
    '└────┬─────┘      │              │\n'
    '     │            │              │\n'
    '     ▼            │              │\n'
    '┌─────────────────────┐          │\n'
    '│ Utilisateur envoie  │          │\n'
    '│ un message          │          │\n'
    '└────────┬────────────┘          │\n'
    '         │                        │\n'
    '         ▼                        │\n'
    '┌─────────────────────┐          │\n'
    '│ INSERT message_chat │          │\n'
    '│ (requête préparée)  │          │\n'
    '└────────┬────────────┘          │\n'
    '         │                        │\n'
    '         ▼                        │\n'
    '┌─────────────────────┐          │\n'
    '│ Diffuser au destin- │          │\n'
    '│ aire (WebSocket)    │──────────┘\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '     [Déconnexion]\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# 4.8 Génération Rapport
doc.add_heading('4.8 Génération de Rapport', level=2)
doc.add_paragraph(
    'Début\n'
    '  │\n'
    '  ▼\n'
    '┌─────────────────────┐\n'
    '│ Admin sélectionne   │\n'
    '│ type de rapport     │\n'
    '│ (PDF/Excel/Personn.)│\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Définir les critères│\n'
    '│ (période, classe,   │\n'
    '│ matière, etc.)      │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Requête SQL avec    │\n'
    '│ filtres dynamiques  │\n'
    '│ (procédure stockée) │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Traiter les données │\n'
    '│ (calculs, moyennes, │\n'
    '│ statistiques)       │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Générer le fichier  │\n'
    '│ (TCPDF/DOMPDF pour  │\n'
    '│ PDF, PhpSpreadsheet │\n'
    '│ pour Excel)         │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ [Proposer]          │\n'
    '│ téléchargement      │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '┌─────────────────────┐\n'
    '│ Journal d\'activité  │\n'
    '└────────┬────────────┘\n'
    '         │\n'
    '         ▼\n'
    '        Fin'
)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════
# 5. SCHÉMA BASE DE DONNÉES
# ═══════════════════════════════════════════════════════════
doc.add_heading('5. Schéma de la Base de Données PostgreSQL', level=1)
doc.add_paragraph(
    'Voici le schéma complet de la base de données avec les types de données, '
    'contraintes et relations.'
)

add_table(doc,
    ['Table', 'Colonnes Principales', 'Clés', 'Taille Estimée'],
    [
        ['utilisateurs', 'id, nom, prenom, email, password_hash, role, actif, 2fa, tentatives, verrouille', 'PK: id, UNIQUE: email', '1000+ lignes'],
        ['roles', 'id, nom_role, description', 'PK: id', '4 lignes'],
        ['permissions', 'id, nom_permission, description', 'PK: id', '30+ lignes'],
        ['role_permissions', 'role_id, permission_id', 'PK: composite, FK: roles, permissions', '120+ lignes'],
        ['sessions_utilisateur', 'id, user_id, token, ip, user_agent, expires_at', 'PK: id, FK: utilisateurs', 'Variable'],
        ['historique_connexions', 'id, user_id, ip, user_agent, date, succes', 'PK: id, FK: utilisateurs', '10000+ lignes'],
        ['journal_activites', 'id, user_id, action, details, ip, date', 'PK: id, FK: utilisateurs', '50000+ lignes'],
        ['eleves', 'id, user_id, matricule, nom, prenom, DOB, sexe, classe_id, parent_*, adresse', 'PK: id, FK: utilisateurs, classes', '500+ lignes'],
        ['profs', 'id, user_id, matricule, nom, prenom, specialite, tel, email', 'PK: id, FK: utilisateurs', '50+ lignes'],
        ['personnel', 'id, user_id, matricule, nom, prenom, fonction, tel, email', 'PK: id, FK: utilisateurs', '20+ lignes'],
        ['classes', 'id, nom_classe, niveau, section, annee_scolaire, capacite', 'PK: id', '20+ lignes'],
        ['matieres', 'id, nom_matiere, code, coefficient, classe_id, prof_id', 'PK: id, FK: classes, profs', '100+ lignes'],
        ['horaires', 'id, classe_id, matiere_id, prof_id, jour, heure_debut/fin, salle, statut', 'PK: id, FK: classes, matieres, profs', '500+ lignes'],
        ['notes', 'id, eleve_id, matiere_id, prof_id, note, type_eval, date_eval, appreciation', 'PK: id, FK: eleves, matieres, profs', '50000+ lignes'],
        ['presences', 'id, eleve_id, prof_id, date_presence, statut, motif', 'PK: id, FK: eleves, profs', '50000+ lignes'],
        ['clubs', 'id, nom_club, description, prof_resp_id, logo, statut', 'PK: id, FK: profs', '10+ lignes'],
        ['club_membres', 'id, club_id, eleve_id, date_inscription', 'PK: id, FK: clubs, eleves', '200+ lignes'],
        ['communications', 'id, titre, contenu, auteur_id, destinataires, type, statut', 'PK: id, FK: utilisateurs', '500+ lignes'],
        ['documents', 'id, nom_fichier, chemin, type, taille, auteur_id, statut', 'PK: id, FK: utilisateurs', '500+ lignes'],
        ['document_versions', 'id, document_id, version, chemin, date, modifie_par', 'PK: id, FK: documents, utilisateurs', '1000+ lignes'],
        ['document_partages', 'id, document_id, user_id, date_partage', 'PK: id, FK: documents, utilisateurs', '500+ lignes'],
        ['notifications', 'id, user_id, titre, message, type, lu, lien', 'PK: id, FK: utilisateurs', '10000+ lignes'],
        ['messages_chat', 'id, sender_id, receiver_id, message, lu, created_at', 'PK: id, FK: utilisateurs', '50000+ lignes'],
        ['commentaires', 'id, document_id, user_id, contenu, created_at', 'PK: id, FK: documents, utilisateurs', '1000+ lignes'],
        ['archives', 'id, table_source, record_id, donnees_json, date, archive_par', 'PK: id, FK: utilisateurs', 'Variable'],
        ['sauvegardes', 'id, nom_fichier, chemin, taille, type, date, effectuee_par', 'PK: id, FK: utilisateurs', 'Variable'],
        ['parametres_systeme', 'id, cle, valeur, description', 'PK: id, UNIQUE: cle', '20+ lignes'],
        ['themes', 'id, nom_theme, css_path, actif', 'PK: id', '5+ lignes'],
    ]
)

doc.add_paragraph()
doc.add_heading('Procédures Stockées Principales', level=2)

add_table(doc,
    ['Procédure', 'Paramètres', 'Description'],
    [
        ['sp_inscrire_eleve(p_nom, p_prenom, ...)', 'Données élève + classe', 'Inscription complète avec génération matricule'],
        ['sp_inscrire_prof(p_nom, p_prenom, ...)', 'Données prof', 'Inscription professeur'],
        ['sp_saisir_notes(p_eleve_id, p_matiere_id, p_note, ...)', 'IDs + note', 'Insertion note avec validation'],
        ['sp_calculer_moyenne(p_eleve_id, p_classe_id, p_periode)', 'IDs + période', 'Calcul moyenne par matière/global'],
        ['sp_marquer_presence(p_eleve_id, p_prof_id, p_statut, ...)', 'IDs + statut', 'Enregistrement présence'],
        ['sp_archiver_donnees(p_table, p_record_id)', 'Table + ID', 'Archivage JSONB'],
        ['sp_restaurer_archive(p_archive_id)', 'ID archive', 'Restauration depuis archive'],
        ['sp_sauvegarde_auto()', 'Aucun', 'Sauvegarde automatique BDD'],
        ['sp_generer_rapport(p_type, p_periode, p_filtres)', 'Type + filtres', 'Génération données rapport'],
        ['sp_statistiques(p_table, p_periode, p_filtres)', 'Table + filtres', 'Calcul statistiques'],
    ]
)

doc.add_paragraph()
doc.add_heading('Index Recommandés', level=2)
add_table(doc,
    ['Table', 'Index', 'Justification'],
    [
        ['utilisateurs', 'idx_users_email, idx_users_role', 'Recherche par email et rôle fréquente'],
        ['eleves', 'idx_eleves_classe, idx_eleves_matricule', 'Filtrage par classe, recherche par matricule'],
        ['notes', 'idx_notes_eleve, idx_notes_matiere, idx_notes_date', 'Consultation notes par élève/matière'],
        ['presences', 'idx_presences_eleve, idx_presences_date', 'Historique présences'],
        ['horaires', 'idx_horaires_classe_jour, idx_horaires_prof', 'Consultation horaire par classe/prof'],
        ['historique_connexions', 'idx_histo_user_date', 'Recherche historique'],
        ['journal_activites', 'idx_journal_user_date, idx_journal_action', 'Recherche activité'],
        ['notifications', 'idx_notif_user_lu', 'Notifications non lues'],
        ['messages_chat', 'idx_chat_sender_receiver', 'Conversation entre deux utilisateurs'],
    ]
)

# ═══════════════════════════════════════════════════════════
# SAUVEGARDE
# ═══════════════════════════════════════════════════════════
output_path = '/home/iragame/My_School/My_School_Diagrammes_UML.docx'
doc.save(output_path)
print(f'Document généré : {output_path}')
