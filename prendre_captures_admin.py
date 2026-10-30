#!/usr/bin/env python3
import subprocess, time
from pathlib import Path

CAP = Path('/home/iragame/My_School/guide_captures')
CAP.mkdir(exist_ok=True)

def take(url, name):
    out = CAP / f'{name}.png'
    cmd = ['chromium', '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
           '--window-size=1440,900', f'--screenshot={out}', url]
    subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    time.sleep(4)
    print(name)
    return out

# Admin déconnecté pour voir l'accueil propre
take('http://localhost:8080/', '01_accueil')
# On va essayer avec les pages publiques d'abord
pages = [
    ('02_dashboard', 'admin/index.php'),
    ('03_eleves', 'admin/eleves/index.php'),
    ('04_profs', 'admin/profs/index.php'),
    ('05_personnel', 'admin/personnel/index.php'),
    ('06_classes', 'admin/classes/index.php'),
    ('07_horaires', 'admin/horaires/index.php'),
    ('08_notes', 'admin/notes/index.php'),
    ('09_presences', 'admin/presences/index.php'),
    ('10_discipline', 'admin/discipline/index.php'),
    ('11_finance_dashboard', 'admin/finance/index.php'),
    ('12_finance_frais', 'admin/finance/frais.php'),
    ('13_finance_paiements', 'admin/finance/paiements.php'),
    ('14_communications', 'admin/communications/index.php'),
    ('15_clubs', 'admin/clubs/index.php'),
    ('16_documents', 'admin/documents/index.php'),
    ('17_archivage', 'admin/archivage-electronique/index.php'),
    ('18_recherche', 'admin/recherche-documentaire/index.php'),
    ('19_utilisateurs', 'admin/users/index.php'),
    ('20_parametres', 'admin/parametres/index.php'),
    ('21_sauvegarde', 'admin/parametres/backup.php'),
    ('22_logs', 'admin/logs/index.php'),
    ('23_rapports', 'admin/rapports/index.php'),
]
for name, path in pages:
    take(f'http://localhost:8080/{path}', name)
print('DONE')
