# Certus — Système de gestion de licences pour Experto

API REST de gestion des licences du logiciel **Experto** par ExpertoSoft.

- **Base URL** : `https://certus.expertosoft.com/api/v1`
- **Stack** : Laravel 11 · PHP 8.3+ · MariaDB 10.11
- **Auth** : JWT (tymon/jwt-auth) + Sanctum

---

## Prérequis

| Outil | Version minimale |
|-------|-----------------|
| PHP | 8.3 |
| Composer | 2.x |
| MariaDB | 10.11 |
| Node.js | 18+ (optionnel, pour les assets) |

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/expertosoft/certus.git
cd certus
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Éditez `.env` et renseignez au minimum :

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=certus_db
DB_USERNAME=votre_utilisateur
DB_PASSWORD=votre_mot_de_passe
```

### 4. Créer la base de données MariaDB

```sql
CREATE DATABASE certus_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Générer la clé JWT

```bash
php artisan jwt:secret
```

### 6. Exécuter les migrations

```bash
php artisan migrate
```

### 7. (Optionnel) Publier les configurations des packages

```bash
# Sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Spatie Permissions
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# JWT Auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

---

## Vérification

### Tester la connexion à la base de données

```bash
php artisan db:monitor
```

### Lister les routes API

```bash
php artisan route:list --path=api
```

### Tester la route de statut

```
GET https://certus.expertosoft.com/api/v1/status
```

Réponse attendue :

```json
{
  "status": "ok",
  "app": "Certus",
  "version": "v1",
  "time": "2026-06-07T08:00:00+00:00"
}
```

---

## Structure des routes

| Préfixe | Fichier | Description |
|---------|---------|-------------|
| `/api/v1` | `routes/api.php` | API REST versionnée |
| `/` | `routes/web.php` | Routes web (non utilisées) |

---

## Packages installés

| Package | Version | Usage |
|---------|---------|-------|
| `laravel/sanctum` | ^4.3 | Auth API par tokens Sanctum |
| `spatie/laravel-permission` | ^8.0 | Rôles (ADMIN, CLIENT) et permissions |
| `tymon/jwt-auth` | ^2.3 | Tokens JWT pour l'authentification locale |
| `bacon/bacon-qr-code` | ^3.1 | Génération de QR codes (usage futur) |

---

## Rôles

| Rôle | Description |
|------|-------------|
| `ADMIN` | Accès complet — gestion des licences, clients, rapports |
| `CLIENT` | Accès restreint — consultation de ses propres licences |

---

## Déploiement en production

1. Mettre `APP_ENV=production` et `APP_DEBUG=false` dans `.env`
2. Optimiser l'application :
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   composer install --optimize-autoloader --no-dev
   ```
3. Configurer le serveur web (Nginx/Apache) pour pointer vers `/public`
4. Configurer HTTPS (certificat SSL requis)

---

## Licence

Propriétaire — ExpertoSoft © 2026. Tous droits réservés.
