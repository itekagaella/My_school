#!/usr/bin/env python3
from graphviz import Digraph

dot = Digraph('Cas_Utilisation_My_School', comment='Diagramme cas utilisation')
dot.attr(rankdir='LR', splines='ortho')
dot.attr('graph', pad='0.5')
dot.attr('node', fontname='Arial', fontsize='9')
dot.attr('edge', fontname='Arial', fontsize='8')

# Acteurs
actors = ['Administrateur', 'Professeur', 'Élève', 'Surveillant', 'Gestionnaire Financier', 'Responsable Discipline']
for a in actors:
    dot.node(a, a, shape='actor')

# Système
with dot.subgraph(name='cluster_systeme') as s:
    s.attr(label='Système My_School', style='rounded', color='#2F9C5D')
    
    # Auth
    s.node('UC_Auth', "S'authentifier", shape='ellipse')
    s.node('UC_2FA', "S'authentifier avec 2FA", shape='ellipse')
    
    # Admin
    s.node('UC_GestUsers', 'Gérer les utilisateurs', shape='ellipse')
    s.node('UC_GestClasses', 'Gérer classes/matières', shape='ellipse')
    s.node('UC_GestEDT', "Gérer l'emploi du temps", shape='ellipse')
    s.node('UC_Param', 'Gérer les paramètres', shape='ellipse')
    s.node('UC_GestDocs', 'Gérer les documents', shape='ellipse')
    s.node('UC_Rapports', 'Générer des rapports', shape='ellipse')
    s.node('UC_Sauvegarde', 'Gérer la sauvegarde', shape='ellipse')
    
    # Commun
    s.node('UC_ConsEDT', "Consulter emploi du temps", shape='ellipse')
    s.node('UC_Chat', 'Envoyer/Répondre messages', shape='ellipse')
    s.node('UC_Notif', 'Consulter notifications', shape='ellipse')
    s.node('UC_Profil', 'Gérer son profil', shape='ellipse')
    
    # Prof
    s.node('UC_SaisirNotes', 'Saisir les notes', shape='ellipse')
    s.node('UC_GestPres', 'Gérer les présences', shape='ellipse')
    
    # Eleve
    s.node('UC_ConsNotes', 'Consulter mes notes', shape='ellipse')
    s.node('UC_Bulletins', 'Consulter mes bulletins', shape='ellipse')
    s.node('UC_ConsPaiements', 'Consulter mes paiements', shape='ellipse')
    s.node('UC_ConsDisc', 'Consulter ma discipline', shape='ellipse')
    
    # Finance
    s.node('UC_GestFrais', 'Gérer les frais scolaires', shape='ellipse')
    s.node('UC_EnrPaiement', 'Enregistrer les paiements', shape='ellipse')
    s.node('UC_Recus', 'Générer les reçus', shape='ellipse')
    s.node('UC_Soldes', 'Suivre les soldes', shape='ellipse')
    
    # Discipline
    s.node('UC_Incidents', 'Gérer les incidents', shape='ellipse')
    s.node('UC_Sanctions', 'Gérer les sanctions', shape='ellipse')
    s.node('UC_Convocations', 'Gérer les convocations', shape='ellipse')
    
    # Surveillant
    s.node('UC_PresSurv', 'Enregistrer les présences', shape='ellipse')

# Auth
for a in actors:
    dot.edge(a, 'UC_Auth')
dot.edge('UC_Auth', 'UC_2FA', style='dashed', label='<<extend>>')

# Admin
for uc in ['UC_GestUsers','UC_GestClasses','UC_GestEDT','UC_Param','UC_GestDocs','UC_Rapports','UC_Sauvegarde','UC_Chat','UC_Notif','UC_Profil']:
    dot.edge('Administrateur', uc)

# Prof
for uc in ['UC_ConsEDT','UC_SaisirNotes','UC_GestPres','UC_Chat','UC_Notif','UC_Profil']:
    dot.edge('Professeur', uc)

# Eleve
for uc in ['UC_ConsEDT','UC_ConsNotes','UC_Bulletins','UC_ConsPaiements','UC_ConsDisc','UC_Chat','UC_Notif','UC_Profil']:
    dot.edge('Élève', uc)

# Surveillant
for uc in ['UC_ConsEDT','UC_PresSurv','UC_Incidents','UC_Chat','UC_Notif','UC_Profil']:
    dot.edge('Surveillant', uc)

# Finance
for uc in ['UC_GestFrais','UC_EnrPaiement','UC_Recus','UC_Soldes','UC_Chat','UC_Notif','UC_Profil']:
    dot.edge('Gestionnaire Financier', uc)

# Discipline
for uc in ['UC_Incidents','UC_Sanctions','UC_Convocations','UC_Chat','UC_Notif','UC_Profil']:
    dot.edge('Responsable Discipline', uc)

dot.render('/home/iragame/My_School/diagramme_cas_utilisation_principal', format='png', cleanup=True)
dot.render('/home/iragame/My_School/diagramme_cas_utilisation_principal', format='pdf', cleanup=True)
print('done')
