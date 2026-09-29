# My_School

Système de Gestion Scolaire — PHP 8.5 + PostgreSQL.

> **Pour démarrer sur Windows :** installe [Docker Desktop](https://www.docker.com/products/docker-desktop/),
> puis suis [la section Windows](#-windows--docker-desktop). C'est la seule chose à installer.

## 🚀 Démarrage rapide avec Docker (recommandé)

Le projet est fourni avec un environnement Docker complet (PHP + Apache + PostgreSQL).

### Prérequis

- [Docker](https://www.docker.com/products/docker-desktop/) (version 20+)
- Docker Compose v2 (inclus avec Docker Desktop)

### Installation en 3 étapes

```bash
# 1. Cloner le projet
git clone https://github.com/itekagaella/My_school.git
cd My_school

# 2. Démarrer les conteneurs (premier lancement : construit l'image et initialise la BDD)
docker compose up -d

# 3. Ouvrir l'application
#    → http://localhost:8081
```

Le premier lancement crée automatiquement la base de données et charge le
schéma (tables + procédures stockées). Les démarrages suivants sont instantanés
et conservent vos données (volume PostgreSQL).

### 🪟 Windows — Docker Desktop

Docker Desktop embarque tout ce qu'il faut (PostgreSQL compris), donc **rien
d'autre à installer** : ni WAMP, ni XAMPP, ni PostgreSQL, ni PHP.

1. Télécharge et installe [Docker Desktop](https://www.docker.com/products/docker-desktop/)
   (l'icône baleine doit apparaître en bas à droite : le moteur Docker tourne).
2. Installe [Git for Windows](https://git-scm.com/download/win) — ou récupère
   l'archive ZIP du dépôt depuis le bouton vert **Code** sur GitHub et
   décompresse-la où tu veux.
3. Ouvre **PowerShell** dans le dossier du projet, puis :

```powershell
# Si tu as cloné avec Git :
git clone https://github.com/itekagaella/My_school.git
cd My_school

# Si tu as décompressé le ZIP, saute simplement les deux lignes ci-dessus

# Démarrer (le premier lancement construit l'image : compte 5 à 10 minutes)
docker compose up -d

# Ouvrir l'application
start http://localhost:8081
```

Une seule dépendance est donc requise : **Docker Desktop**.

<details>
<summary>Si <code>docker</code> n'est pas reconnu dans PowerShell</summary>

Docker Desktop n'est pas démarré ou n'est pas dans le PATH. Ferme puis
rouvre PowerShell après l'installation ; si ça ne suffit pas, ajoute
`C:\Program Files\Docker\Docker\resources\bin` aux variables d'environnement
PATH, ou clique sur l'icône Docker Desktop dans la barre des tâches.
</details>

### Identifiants par défaut

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| **Administrateur** | `admin@myschool.edu` | `admin123` |

Les comptes élèves / professeurs / personnel sont créés depuis l'interface
administrateur (menu **Utilisateurs** et **Élèves / Professeurs / Personnel**).

> Changez ce mot de passe dès la première connexion (menu *Paramètres*).

### Commandes utiles

```bash
docker compose up -d            # Lancer en arrière-plan
docker compose logs -f web      # Voir les logs de l'application
docker compose logs -f db       # Voir les logs PostgreSQL
docker compose down             # Arrêter les conteneurs (données conservées)
docker compose down -v          # Arrêter ET supprimer les données (reset complet)
docker compose ps               # État des conteneurs
```

> **Ports :** web → `8081`, PostgreSQL → `5433` (host) / `5432` (conteneur).
> Le port 5433 côté hôte évite tout conflit avec un PostgreSQL local.


## 🔧 Installation manuelle (sans Docker)

> Si tu as Docker, saute cette section : [plus haut](#-démarrage-rapide-avec-docker-recommandé)
> c'est plus simple. Ce mode est prévu pour un poste où PostgreSQL est déjà
> installé (typiquement un serveur Linux).

### Prérequis

- PHP 8.0+ avec extensions `pdo_pgsql`, `pgsql`, `gd`
- PostgreSQL 12+
- Apache (avec `mod_rewrite`) ou serveur PHP intégré

### Étapes

1. **Base de données :** créer une base nommée `my_school` puis lancer
   `psql -U postgres -d my_school -f config/init.sql`.
2. **Configuration :** copier `.env.example` en `.env` et renseigner les
   identifiants PostgreSQL.

   ```bash
   cp .env.example .env
   # Windows : copy .env.example .env
   ```

   ```
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_NAME=my_school
   DB_USER=postgres
   DB_PASSWORD=votre_mot_de_passe
   ```

3. **Serveur :**
   ```bash
   php -S localhost:8080
   # puis ouvrir http://localhost:8080
   ```

4. Se connecter avec `admin@myschool.edu` / `admin123`.

### Ou alors : laisser l'application s'auto-installer

Si tu ne veux pas toucher à la ligne de commande, sers les fichiers et ouvre
`http://localhost:8080/install.php`. L'assistant vérifie l'environnement, crée
la base, charge le schéma, génère le `.env` et définit ton mot de passe
administrateur. Il se verrouille tout seul une fois l'installation terminée —
tu peux alors supprimer `install.php` du serveur.

> ⚠️ `install.php` ne s'exécute que si la base est vide. Pour réinstaller :
> supprime la base, puis le fichier `config/.installed`.

## 🗂️ Structure du projet

```
admin/        Interface d'administration (gestion complète)
client/       Espace élève / professeur / personnel
api/          Endpoints (chat, notifications, recherche, données)
config/       Configuration + schéma de base de données (init.sql)
includes/     Fonctions, sécurité, layouts
assets/       CSS / JS / uploads
install.php   Installeur web (création de la base, à supprimer après usage)
Dockerfile  docker-compose.yml  .env.example   # Environnement Docker
```

## ✨ Fonctionnalités

- **Authentification sécurisée** : sessions, 2FA par email, hash bcrypt, tokens
  CSRF, verrouillage du compte après échecs répétés, réinitialisation de mot de
  passe.
- **Rôles & permissions** : admin, élève, professeur, personnel, avec contrôle
  d'accès granulaire et journal des activités.
- **Gestion des données** : utilisateurs, élèves, professeurs, personnel,
  classes, matières, horaires (avec détection de conflits), notes, présences.
- **Vie scolaire** : clubs et adhésions, communications/annonces, documents
  (upload, versions, partage), commentaires.
- **Rapports & statistiques** : exports PDF et Excel, graphiques Chart.js.
- **Communication en temps réel** : chat interne et notifications.
- **Administration** : paramètres système, thèmes, sauvegarde de la base.

## ⚠️ Note de sécurité

En production, changez obligatoirement :
- le mot de passe administrateur par défaut,
- supprimez `install.php` du serveur (ou laissez-le se verrouiller tout seul),
- la clé `ENCRYPTION_KEY` dans le fichier `.env`,
- les identifiants PostgreSQL,
- les valeurs de `MAIL_*` dans `config/config.php`.

