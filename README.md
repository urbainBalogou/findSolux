# MecaDispo

Application de mise en relation avec les mécaniciens et garages de proximité à Lomé (phase 1). Identifiant de l’application : `com.mecadispo.app`. Le nom de code `findsolux` reste dans les noms techniques (paquet Dart, bases de données, fichier `config/findsolux.php`, conteneurs Docker).

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

### Avec Docker (sans PHP ni MySQL sur le poste)

Prérequis : Docker Desktop et Flutter. [docker-compose.yml](docker-compose.yml) lance MySQL 8.4, l'API et le back-office (PHP 8.3 + Apache, http://localhost:8000), la file d'attente et les tâches planifiées. Le dossier `backend/` est partagé avec les conteneurs : une modification du code est prise en compte sans reconstruire l'image.

```sh
cp backend/.env.example backend/.env    # première fois
docker compose up -d --build
docker compose exec api composer install                 # première fois
docker compose exec api php artisan key:generate         # première fois
docker compose exec api php artisan migrate --seed
docker compose exec api php artisan storage:link         # première fois
docker compose exec api php artisan admin:create         # première fois
docker compose exec api php artisan test
cd backend && npm install && npm run build               # styles du back-office (Node sur le poste)
```

- `DB_HOST` et `DB_PORT` de `backend/.env` sont remplacés par ceux du conteneur MySQL. Arrêter le MySQL de XAMPP, qui utilise aussi le port 3306, ou changer `FORWARD_DB_PORT`.
- **Disque externe** : si le projet est sur un disque branché après le démarrage de Docker Desktop, les conteneurs voient `backend/` vide (erreur 404 ou « vendor/autoload.php introuvable »). Redémarrer Docker Desktop, disque branché, ou lancer `wsl --shutdown` puis relancer Docker Desktop.
- Image de production (code, dépendances et styles compris, sans `.env` ni secrets) : `docker build -t findsolux-api backend`. La configuration (`APP_KEY`, `DB_*`, `APP_LOCALE=fr`…) se passe alors en variables d'environnement.

### Sans Docker

Prérequis : PHP 8.3+, Composer, Flutter, et un serveur MySQL : celui de XAMPP (MariaDB), ou MySQL 8.4 via Docker Desktop.
Extensions PHP : `gd` (photos) et `exif` (orientation des photos prises au téléphone ; à activer dans `php.ini`).

```sh
# Base de données : démarrer MySQL dans XAMPP et créer les bases findsolux et
# findsolux_test (utf8mb4_unicode_ci), ou bien : docker compose up -d db

# API
cd backend
composer install
cp .env.example .env   # première fois
php artisan key:generate
php artisan migrate --seed   # référentiels : Lomé, quartiers, services
php artisan storage:link      # première fois : accès public aux photos
php artisan serve      # http://localhost:8000/api/v1/health
php artisan queue:work # notifications push (file d'attente « database »)
php artisan schedule:work  # offres (renouvellement, rappels, échéances) et demandes expirées
php artisan admin:create   # première fois : compte du back-office (http://localhost:8000/admin)
npm install && npm run build  # styles du back-office
php artisan test
# Connexion : par compte Google, voir « Connexion Google » plus bas.
# Notifications push écrites dans storage/logs/laravel.log tant que PUSH_DRIVER=log.
# Sans adhésion active, un garage est invisible : en local, accordez une offre
# depuis le back-office (Garages et adhésions).
# Émulateur Android : mettre APP_URL=http://10.0.2.2:8000 dans backend/.env pour que
# les adresses des photos soient joignables depuis l'émulateur.

# Application mobile (émulateur Android : l'API locale est vue en http://10.0.2.2:8000)
cd mobile
flutter pub get
flutter run
# Autre API : flutter run --dart-define=API_BASE_URL=https://.../api/v1
# Connexion Google : ajouter --dart-define=GOOGLE_SERVER_CLIENT_ID=....apps.googleusercontent.com
```

## Clés d'API (hors Git)

| Fichier | Clé | Usage | Restriction à configurer dans Google Cloud |
|---|---|---|---|
| `mobile/android/local.properties` | `MAPS_API_KEY` | Carte Google dans l'application Android | Maps SDK for Android + application `com.mecadispo.app` et son empreinte SHA-1 |
| `mobile/ios/Flutter/Secrets.xcconfig` | `MAPS_API_KEY` | Carte Google sur iOS | Maps SDK for iOS + identifiant de l'application |
| `backend/.env` | `GOOGLE_MAPS_SERVER_KEY` | Recherche de lieux, appelée par le serveur | Places API (New) + adresse IP du serveur |

Modèles : `mobile/android/local.properties.example`, `mobile/ios/Flutter/Secrets.xcconfig.example`, `backend/.env.example`.
Empreinte SHA-1 de debug : `cd mobile/android && ./gradlew signingReport`.
Sans clé, la carte reste vide et la recherche de lieux ne cherche que parmi les quartiers connus.

### Connexion Google (ADR-017)

Dans Google Cloud (de préférence le projet Firebase), « API et services » → « Identifiants » :

1. Configurer l'écran de consentement OAuth (nom de l'application, adresse de contact).
2. Créer un client OAuth de type **Application Web**. Son identifiant (`….apps.googleusercontent.com`) va :
   - dans `backend/.env` : `GOOGLE_SIGN_IN_CLIENT_IDS=` cet identifiant ;
   - dans l'application : `flutter run --dart-define=GOOGLE_SERVER_CLIENT_ID=` cet identifiant. Facultatif si `google-services.json` est présent et contient ce client.
3. Créer un client OAuth de type **Android** : paquet `com.mecadispo.app` et empreinte SHA-1 (`cd mobile/android && ./gradlew signingReport`). Il en faut un par clé de signature : debug, publication, et clé de Google Play une fois l'application publiée. Aucun fichier à télécharger : Google reconnaît l'application à son empreinte.

Sans `GOOGLE_SIGN_IN_CLIENT_IDS`, l'API répond `503` à la connexion. Sans client Android correspondant à l'empreinte, Google refuse la connexion sur le téléphone.
iOS (plus tard) : client OAuth « iOS », `--dart-define=GOOGLE_IOS_CLIENT_ID=…`, et `GIDClientID` + schéma d'URL inversé dans `ios/Runner/Info.plist`.

### Notifications push (Firebase, ADR-016)

1. Créer un projet Firebase et y ajouter l'application Android `com.mecadispo.app`.
2. Télécharger `google-services.json` dans `mobile/android/app/` (ignoré par Git). Le plugin Gradle n'est appliqué que si ce fichier existe.
3. Dans Firebase, « Paramètres du projet » → « Comptes de service » → générer une clé privée (JSON). La placer sur le serveur hors du dépôt (par exemple `backend/storage/firebase/credentials.json`, ignoré par Git).
4. Dans `backend/.env` : `PUSH_DRIVER=fcm` et `FIREBASE_CREDENTIALS=` le chemin absolu de ce fichier.

Sans Firebase, l'application fonctionne : les écrans « Demandes » et « Signal de détresse » se rafraîchissent d'eux-mêmes, mais rien n'arrive application fermée.
