# My_School - Système de Gestion Scolaire

My_School est une application web de gestion scolaire complète développée en PHP 8.5 avec PostgreSQL. Elle permet de gérer les élèves, professeurs, notes, présences, emplois du temps, communications, finances, documents et bien plus encore.

## Fonctionnalités principales

- **Gestion des utilisateurs** - Élèves, Professeurs, Personnel, Administrateurs avec système de rôles et permissions
- **Scolarité** - Inscriptions, classes, matières, emplois du temps
- **Évaluations** - Gestion des notes, bulletins, appréciations
- **Présences & Discipline** - Suivi des présences, absences, sanctions et incidents
- **Communications** - Notifications, messages, communications à destination des parents/élèves
- **Finance** - Frais scolaires, paiements, factures, rapports financiers
- **Documents** - Gestion documentaire avec versioning et partage
- **Archivage électronique (GED)** - Archivage des documents officiels avec métadonnées, référence et recherche
- **Recherche documentaire** - Recherche unifiée dans les documents et l'archivage électronique
- **Authentification sécurisée** - Connexion avec 2FA par OTP, verrouillage de compte, sessions sécurisées
- **Notifications par email** - Système d'envoi via SMTP (Brevo) avec file d'attente automatique
- **Rapports & Statistiques** - Tableaux de bord et exports PDF/Excel
- **Sauvegarde** - Outil de sauvegarde/restauration intégré

## Technologies utilisées

- **Backend** : PHP 8.5
- **Base de données** : PostgreSQL 14+
- **Frontend** : HTML5, CSS3, JavaScript, Bootstrap 5
- **Bibliothèques** : Chart.js, DataTables, FPDF, PHPMailer-like custom SMTP client

## Prérequis

- PHP 8.5 ou supérieur
- Extensions PHP : `pdo_pgsql`, `pgsql`, `gd`, `mbstring`, `openssl`, `curl`, `zip`, `fileinfo`
- PostgreSQL 14 ou supérieur
- Serveur web Apache/Nginx ou PHP intégré

## Installation

### 1. Cloner le dépôt
```bash
git clone https://github.com/itekagaella/My_school.git
cd My_school
```

### 2. Configurer l'environnement
Copiez le fichier d'exemple et adaptez-le à votre configuration :
```bash
cp .env.example .env
```

Éditez `.env` avec vos paramètres :

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=my_school
DB_USER=postgres
DB_PASSWORD=votre_mot_de_passe

ENCRYPTION_KEY=GENEREZ_UNE_CLE_UNIQUE

# Email (Brevo SMTP ou autre)
MAIL_TRANSPORT=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=votre_login_smtp
MAIL_PASSWORD=votre_cle_api_smtp
MAIL_FROM=votre_email_valide
MAIL_FROM_NAME=Myschool
MAIL_ENCRYPTION=tls

# Sécurité
CRON_TOKEN=votre_token_cron
```

Générez une clé de chiffrement sécurisée :
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### 3. Créer la base de données
Créez une base PostgreSQL nommée `my_school` (ou le nom défini dans `.env`).

### 4. Lancer l'application

#### Avec serveur PHP intégré (développement)
```bash
php -S localhost:8080
```
Accédez à [http://localhost:8080](http://localhost:8080)

#### Avec Apache/Nginx
Placez le projet dans votre racine web et configurez le virtual host vers le dossier du projet.

### 5. Appliquer les migrations
Connectez-vous en tant qu'administrateur et accédez à `migrate.php` pour appliquer les migrations SQL, ou exécutez-les directement avec `psql`.

```bash
psql -U postgres -d my_school -f config/init.sql
```

## Configuration de production

- **HTTPS** : Activez HTTPS et les cookies sécurisés se configurent automatiquement
- **Fichier `.env`** : Protégez-le, ne le commitez jamais
- **Permissions** : Assurez-vous que `assets/uploads/`, `storage/`, `logs/` sont accessibles en écriture
- **Tâches cron** : Pour le traitement automatique des emails, configurez un cron/systemd ou utilisez le déclenchement asynchrone intégré
- **Headers de sécurité** : Déjà configurés dans `.htaccess`

## Structure du projet

```
My_School/
├── admin/              # Interface administrateur
├── api/                # Endpoints API REST
├── assets/             # CSS, JS, images, uploads
├── bin/                # Scripts CLI (envoi emails)
├── client/             # Interfaces élèves/profs/personnel
├── config/             # Configuration, BDD, migrations
├── includes/           # Fonctions, auth, layouts, mailer
├── storage/            # Fichiers temporaires, sauvegardes
└── index.php           # Page de connexion
```

## Sécurité

- Requêtes SQL préparées avec PDO
- Protection CSRF sur tous les formulaires
- Échappement XSS avec helper `e()`
- Authentification à double facteur (2FA)
- Hachage des mots de passe avec `password_hash()`
- Verrouillage après tentatives échouées
- Sessions sécurisées avec régénération d'ID
- Headers de sécurité (CSP, X-Frame-Options, X-Content-Type-Options)
- Protection des répertoires sensibles

## Auteur

Développé par **itekagaella**

## Licence

Ce projet est à usage éducatif et institutionnel.
