# Décisions d'architecture (ADR)

Format court : contexte, décision, conséquences. Une décision remise en cause
n'est pas effacée : on ajoute une nouvelle ADR qui la remplace.

## ADR-001 — Monorepo

- **Décision** : un seul dépôt Git avec `backend/` (API + back-office), `mobile/` (application Flutter) et `docs/`.
- **Pourquoi** : équipe réduite, évolutions de l'API et de l'application souvent liées, une seule revue de code.

## ADR-002 — Back-end Laravel

- **Contexte** : le cahier des charges laisse le choix entre Laravel et Django. L'équipe maîtrise Laravel + Sanctum + OTP (projets charm-api, billsplitpro-backend).
- **Décision** : Laravel (dernière version stable), API REST versionnée sous `/api/v1`, authentification par jetons Sanctum.
- **Conventions** : réponses JSON via API Resources, validation par Form Requests, montants en **entiers XOF** (pas de décimales), téléphones au format **E.164** (`+228XXXXXXXX`), dates stockées en UTC (Lomé est en UTC+0 sans heure d'été, fuseau `Africa/Lome`).

## ADR-003 — PostgreSQL + PostGIS

> **Remplacée par ADR-011** (MySQL, choix de l'équipe, 15 octobre 2026).


- **Décision** : PostgreSQL 16 + PostGIS. Positions stockées en `geography(Point, 4326)` avec index GIST ; recherche de proximité par `ST_DWithin` et tri par `ST_Distance`.
- **Tests** : base `findsolux_test` sur PostGIS (SQLite ne gère pas les requêtes géographiques).

## ADR-004 — Back-office Filament, pas d'interface web pro en phase 1

- **Contexte** : le cahier des charges prévoit une interface web pour l'admin et les pros.
- **Décision** : back-office avec Filament (panneau d'administration Laravel), sur une table `admins` distincte des utilisateurs de l'application (mot de passe haché, garde d'authentification séparée). L'espace professionnel est dans l'application mobile.
- **Conséquences** : supprime un front-end complet du périmètre ; tableaux, filtres, exports CSV et tableaux de bord sont fournis par Filament. Une interface web pro pourra être ajoutée plus tard dans Filament (second panneau).

## ADR-005 — Application mobile Flutter

- **Décision** : Flutter, Android en priorité (minSdk 26 = Android 8), iOS ensuite.
- **Socle** : Riverpod (état), go_router (navigation), dio (HTTP), cache local pour les fiches consultées et les favoris, `cached_network_image` pour les photos.
- **Cartographie** : `flutter_map` avec des tuiles OpenStreetMap servies par un fournisseur ou auto-hébergées (PMTiles du Togo) — jamais `tile.openstreetmap.org` en production. Liste par défaut, carte à la demande.
- **Taille** : publication en App Bundle (AAB) et APK par architecture (`--split-per-abi`) pour rester sous 30 Mo.

## ADR-006 — Stockage des fichiers

- **Décision** : disque local en développement ; stockage compatible S3 en production (Cloudflare R2, sans frais de sortie, déjà utilisé sur CHARM — à confirmer).
- **Deux espaces** : `public` pour les photos (compressées et redimensionnées côté serveur) ; `private` pour les pièces justificatives (liens signés à courte durée, accès journalisé, suppression après décision — voir R11).

## ADR-007 — Authentification par OTP

- **Décision** : connexion par numéro de téléphone et code à usage unique. Envoi derrière une interface `OtpSender` avec plusieurs pilotes : `log` (développement), `sms` (fournisseur à choisir), `whatsapp` (plus tard, après vérification Meta).
- **Sécurité** : code haché en base, expiration courte, nombre d'essais limité, limitation par numéro / appareil / IP, numéros +228 uniquement en phase 1, plafond journalier d'envois.

## ADR-008 — Paiement des abonnements

- **Décision** : interface `PaymentGateway` avec un premier pilote `manual` (paiement sur numéro marchand, saisie et validation dans le back-office). Le pilote d'un agrégateur (FedaPay, CinetPay ou PayDunya) sera ajouté après comparaison des frais, de la couverture TMoney/Flooz et des conditions d'ouverture de compte.
- **Règle** : les abonnements ne sont activés que par une confirmation serveur (webhook vérifié ou validation admin), jamais sur la seule déclaration de l'application.

## ADR-009 — Nom de code et identifiants provisoires

- **Décision** : nom de code `findsolux`, identifiant Android `com.findsolux.findsolux` provisoire.
- **À faire avant publication** : choisir le nom commercial et l'identifiant définitif. L'identifiant Android ne peut plus changer après la première publication sur Google Play.

## ADR-010 — Une seule application pour les clients et les garagistes

- **Décision** : une application unique avec deux espaces. L'espace **client** s'ouvre par défaut (Rechercher, Favoris, Compte). L'espace **garagiste** (Accueil et statistiques, Ma fiche, Avis, Abonnement) est accessible depuis l'onglet Compte et depuis une invitation « Vous êtes garagiste ? » sur l'écran de recherche.
- **Rôles** : pas de rôle stocké. Un utilisateur est garagiste dès qu'il possède une fiche ; il peut aussi chercher un autre garage comme client.
- **Pourquoi** : une seule fiche Google Play, une seule base de code, un garagiste installe la même application que ses clients (ce qui facilite l'inscription assistée sur le terrain). La partie garagiste ne pèse que quelques écrans.
- **Melos** : inutile tant qu'il n'y a qu'une application. Le dépôt unique (ADR-001) contient déjà l'API et l'application. Si un second paquet Dart apparaît (par exemple une bibliothèque partagée), on utilisera d'abord les *pub workspaces* natifs de Dart, puis Melos si l'outillage devient nécessaire.

## ADR-011 — MySQL au lieu de PostgreSQL + PostGIS

- **Contexte** : l'équipe préfère MySQL (outillage et hébergement habituels). Remplace ADR-003.
- **Décision** : MySQL 8 en production. En local, le MariaDB de XAMPP ou MySQL 8.4 via `docker compose up -d`. Le code reste compatible avec les deux.
- **Géolocalisation** : `latitude` et `longitude` en `DECIMAL(10,7)` avec un index composite. La recherche de proximité filtre d'abord par un rectangle autour du point (index), puis calcule la distance exacte avec `ST_Distance_Sphere` (disponible sur MySQL 8 et MariaDB 10.4+). Les contours de quartiers (polygones) ne sont pas stockés en phase 1 : la recherche par quartier part du centre du quartier.
- **Évolution** : si le volume l'exige, ajout d'une colonne `POINT SRID 4326` avec index spatial (MySQL 8).
- **Tests** : base `findsolux_test` sur le même moteur que le développement.

