#!/usr/bin/env python3
import subprocess, time
from pathlib import Path

CAP = Path('/home/iragame/My_School/guide_captures')
CAP.mkdir(exist_ok=True)

def take(url, name):
    out = CAP / f'{name}.png'
    cmd = ['chromium', '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
           '--window-size=1440,900', f'--screenshot={out}', url]
    subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30)
    time.sleep(3)
    print(name, out.exists())

pages = [
    ('24_eleve_accueil', 'client/eleve/index.php'),
    ('25_eleve_notes', 'client/eleve/notes.php'),
    ('26_eleve_horaires', 'client/eleve/horaires.php'),
    ('27_eleve_presences', 'client/eleve/presences.php'),
    ('28_eleve_disciplines', 'client/eleve/discipline.php'),
    ('29_eleve_communications', 'client/eleve/communications.php'),
    ('30_eleve_documents', 'client/eleve/documents.php'),
    ('31_eleve_finance', 'client/eleve/finance.php'),
    ('32_eleve_clubs', 'client/eleve/clubs.php'),
]
for name, path in pages:
    take(f'http://localhost:8080/{path}', name)
print('DONE')
