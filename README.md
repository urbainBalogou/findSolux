# findSolux (nom de code)

Application de mise en relation avec les mécaniciens et garages de proximité à Lomé (phase 1).

## Structure

| Dossier | Contenu |
|---|---|
| `backend/` | API REST Laravel (`/api/v1`) et back-office |
| `mobile/` | Application Flutter (Android en priorité) |
| `docs/` | Cahier des charges, analyse, décisions, modèle de données, backlog |
| `docker/` | Initialisation de la base MySQL (option Docker) |

## Documentation

- [Cahier des charges v2.1](docs/cahier-des-charges.md)
- [Analyse et recommandations](docs/analyse-et-recommandations.md) — justification des amendements
- [Décisions d'architecture](docs/decisions.md)
- [Modèle de données](docs/modele-donnees.md)
- [Backlog du MVP](docs/backlog-mvp.md)

## Démarrage local

Prérequis : PHP 8.3+, Composer, Flutter, et un serveur MySQL : celui de XAMPP (MariaDB), ou MySQL 8.4 via Docker Desktop.
Extensions PHP : `gd` (photos) et `exif` (orientation des photos prises au téléphone ; à activer dans `php.ini`).

```sh
# Base de données : démarrer MySQL dans XAMPP et créer les bases findsolux et
# findsolux_test (utf8mb4_unicode_ci), ou bien : docker compose up -d

# API
cd backend
composer install
cp .env.example .env   # première fois
php artisan key:generate
php artisan migrate --seed   # référentiels : Lomé, quartiers, services
php artisan storage:link      # première fois : accès public aux photos
php artisan serve      # http://localhost:8000/api/v1/health
php artisan test
# Connexion en local : le code SMS est écrit dans storage/logs/laravel.log
# Émulateur Android : mettre APP_URL=http://10.0.2.2:8000 dans backend/.env pour que
# les adresses des photos soient joignables depuis l'émulateur.

# Application mobile (émulateur Android : l'API locale est vue en http://10.0.2.2:8000)
cd mobile
flutter pub get
flutter run
# Autre API : flutter run --dart-define=API_BASE_URL=https://.../api/v1
```

## Clés d'API (hors Git)

| Fichier | Clé | Usage | Restriction à configurer dans Google Cloud |
|---|---|---|---|
| `mobile/android/local.properties` | `MAPS_API_KEY` | Carte Google dans l'application Android | Maps SDK for Android + application `com.findsolux.findsolux` et son empreinte SHA-1 |
| `mobile/ios/Flutter/Secrets.xcconfig` | `MAPS_API_KEY` | Carte Google sur iOS | Maps SDK for iOS + identifiant de l'application |
| `backend/.env` | `GOOGLE_MAPS_SERVER_KEY` | Recherche de lieux, appelée par le serveur | Places API (New) + adresse IP du serveur |

Modèles : `mobile/android/local.properties.example`, `mobile/ios/Flutter/Secrets.xcconfig.example`, `backend/.env.example`.
Empreinte SHA-1 de debug : `cd mobile/android && ./gradlew signingReport`.
Sans clé, la carte reste vide et la recherche de lieux ne cherche que parmi les quartiers connus.
