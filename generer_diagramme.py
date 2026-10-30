#!/usr/bin/env python3
from graphviz import Digraph

dot = Digraph('MySchool_Activite', comment='Diagramme d\'activite My_School')
dot.attr(rankdir='TB', splines='ortho')
dot.attr('node', shape='rectangle', style='rounded,filled', fillcolor='#E8F4FD', fontname='Arial')
dot.attr('edge', fontname='Arial')

# Debut/Fin
dot.node('START', 'DEBUT', shape='ellipse', fillcolor='#90EE90')
dot.node('END', 'FIN', shape='ellipse', fillcolor='#FFB6C1')

# Authentification
dot.node('ACC', 'Accéder à la page de connexion')
dot.node('CONN', 'Saisir identifiants')
dot.node('VERIF', 'Vérifier identifiants', shape='diamond')
dot.node('FA2', 'Vérification 2FA activée ?', shape='diamond')
dot.node('CODE', 'Saisir code 2FA')
dot.node('VERIF2', 'Code valide ?', shape='diamond')
dot.node('SESSION', 'Créer session + historique')
dot.node('REDIR', 'Rediriger vers tableau de bord selon rôle')

# Admin
dot.node('ADMIN', 'ADMINISTRATEUR\nGérer utilisateurs, classes, matières, salles,\nemploi du temps, documents, finances,\ndiscipline, communications, rapports,\nparamètres, sauvegarde', fillcolor='#FFF3CD')
# Prof
dot.node('PROF', 'PROFESSEUR\nEmploi du temps, élèves, notes,\nprésences, documents, chat', fillcolor='#D1ECF1')
# Eleve
dot.node('ELEVE', 'ÉLÈVE\nEmploi du temps, notes, bulletins,\nprésences, paiements, discipline,\ndocuments, clubs, chat', fillcolor='#D4EDDA')
# Personnel
dot.node('PERS', 'PERSONNEL\nSurveillant/Finance/Discipline\nselon permissions', fillcolor='#F8D7DA')

# Notifications
dot.node('NOTIF', 'Recevoir notifications')
dot.node('CLIC', 'Cliquer sur notification ?', shape='diamond')
dot.node('LU', 'Marquer lu et rediriger')
dot.node('DECO', 'Déconnexion (manuelle/auto)')

dot.edges([('START','ACC'),('ACC','CONN'),('CONN','VERIF')])
dot.edge('VERIF','FA2', label='Valide')
dot.edge('VERIF','CONN', label='Invalide')
dot.edge('FA2','CODE', label='Oui')
dot.edge('FA2','SESSION', label='Non')
dot.edge('CODE','VERIF2')
dot.edge('VERIF2','SESSION', label='Valide')
dot.edge('VERIF2','END', label='Invalide')
dot.edge('SESSION','REDIR')
dot.edge('REDIR','ADMIN', label='Admin')
dot.edge('REDIR','PROF', label='Prof')
dot.edge('REDIR','ELEVE', label='Élève')
dot.edge('REDIR','PERS', label='Personnel')
dot.edge('ADMIN','NOTIF')
dot.edge('PROF','NOTIF')
dot.edge('ELEVE','NOTIF')
dot.edge('PERS','NOTIF')
dot.edge('NOTIF','CLIC')
dot.edge('CLIC','LU', label='Oui')
dot.edge('CLIC','DECO', label='Non')
dot.edge('LU','DECO')
dot.edge('DECO','END')

dot.render('/home/iragame/My_School/diagramme_activite', format='png', cleanup=True)
dot.render('/home/iragame/My_School/diagramme_activite', format='pdf', cleanup=True)
print('done')
