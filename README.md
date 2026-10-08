# My_School

Système de Gestion Scolaire — PHP 8.5 + PostgreSQL

## Prérequis

- [PHP 8.5+](https://windows.php.net/download/) avec extensions : `pdo_pgsql`, `pgsql`, `gd`, `mbstring`, `openssl`, `curl`, `zip`
- [PostgreSQL 14+](https://www.enterprisedb.com/downloads/postgres-postgresql-downloads)
- Serveur web Apache (recommandé avec Laragon/XAMPP) ou serveur PHP intégré

## Installation (Windows)

### Option 1 : Laragon (recommandé)
1. Installer [Laragon](https://laragon.org/download/) (Version Full)
2. Démarrer Laragon
3. Placer ce dossier dans `C:\laragon\www\My_School\`
4. Démarrer Apache et PostgreSQL depuis Laragon
5. Ouvrir [http://localhost/My_School/install.php](http://localhost/My_School/install.php)
6. Suivre l'assistant d'installation
7. **Supprimer `install.php`** après installation réussie
8. Accéder à [http://localhost/My_School/](http://localhost/My_School/)

### Option 2 : XAMPP
1. Installer [XAMPP](https://www.apachefriends.org/fr/index.html) et [PostgreSQL](https://www.enterprisedb.com/downloads/postgres-postgresql-downloads)
2. Placer ce dossier dans `C:\xampp\htdocs\My_School\`
3. Activer les extensions PostgreSQL dans `php.ini` (`pdo_pgsql`, `pgsql`, `gd`)
4. Démarrer Apache et PostgreSQL
5. Ouvrir [http://localhost/My_School/install.php](http://localhost/My_School/install.php)
6. Terminer l'installation puis supprimer `install.php`

### Option 3 : Serveur PHP intégré
1. Créer une base `my_school` dans PostgreSQL
2. Copier `.env.example` vers `.env` et configurer les identifiants PostgreSQL
3. Ouvrir un terminal dans le dossier du projet
4. Lancer : `php -S localhost:8080`
5. Ouvrir [http://localhost:8080/install.php](http://localhost:8080/install.php)
6. Terminer l'installation puis supprimer `install.php`

## Configuration manuelle (.env)

Copier `.env.example` vers `.env` :

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=my_school
DB_USER=postgres
DB_PASSWORD=VOTRE_MOT_DE_PASSE
ENCRYPTION_KEY=GENEREZ_UNE_CLE_UNIQUE
MAIL_ENABLED=false
DEMO_LINKS=false
```

Générer une clé unique :
```cmd
php -r "echo bin2hex(random_bytes(32));"
```

### Emails (2FA et mot de passe oublié)

- `MAIL_ENABLED=true` : envoi réel des emails via `mail()` du serveur.
  Indispensable pour que la 2FA et la réinitialisation de mot de passe
  fonctionnent en production (un serveur SMTP doit être configuré sur la machine).
- `MAIL_ENABLED=false` : les emails sont écrits dans `storage/mail/` et
  consultables dans **Admin > Journal > Boîte email** (mode démo).
- `DEMO_LINKS=true` : affiche les codes OTP / liens directement à l'écran
  lorsqu'aucun SMTP n'est configuré — à activer uniquement pour une démo locale,
  jamais sur un déploiement accessible en réseau.

## Identifiants par défaut

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `admin@myschool.edu` | `admin123` |

**Important :** Changez ce mot de passe dès la première connexion.

## Structure du projet

```
admin/        Interface d'administration
client/       Espace élèves/profs/personnel
api/          API REST
config/       Configuration, schéma SQL (init.sql), migrations
includes/     Fonctions, authentification, layouts
assets/       CSS, JS, uploads
install.php   Assistant d'installation (à supprimer après usage)
```

## Fonctionnalités principales

- Authentification sécurisée (sessions, 2FA, CSRF, verrouillage comptes)
- Gestion utilisateurs, élèves, professeurs, personnel, classes, matières
- Horaires, notes, présences, absences
- Clubs, communications, documents (avec versions/partage)
- Rapports PDF/Excel, statistiques
- Chat interne, notifications
- Journal d'activités, sauvegardes

## Sécurité

En production : changez l'administrateur par défaut, supprimez `install.php`, modifiez `ENCRYPTION_KEY` et les identifiants PostgreSQL.
