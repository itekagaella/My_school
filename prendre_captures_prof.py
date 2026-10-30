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
    ('33_prof_accueil', 'client/prof/index.php'),
    ('34_prof_notes', 'client/prof/notes.php'),
    ('35_prof_presences', 'client/prof/presences.php'),
    ('36_prof_horaires', 'client/prof/horaires.php'),
    ('37_prof_communications', 'client/prof/communications.php'),
    ('38_prof_documents', 'client/prof/documents.php'),
]
for name, path in pages:
    take(f'http://localhost:8080/{path}', name)
print('DONE')
