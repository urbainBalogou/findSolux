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

> **Remplacée par ADR-013** pour le choix de Filament (back-office Blade + Tailwind). L'absence d'interface web pour les garagistes reste en vigueur.


- **Contexte** : le cahier des charges prévoit une interface web pour l'admin et les pros.
- **Décision** : back-office avec Filament (panneau d'administration Laravel), sur une table `admins` distincte des utilisateurs de l'application (mot de passe haché, garde d'authentification séparée). L'espace professionnel est dans l'application mobile.
- **Conséquences** : supprime un front-end complet du périmètre ; tableaux, filtres, exports CSV et tableaux de bord sont fournis par Filament. Une interface web pro pourra être ajoutée plus tard dans Filament (second panneau).

## ADR-005 — Application mobile Flutter

- **Décision** : Flutter, Android en priorité (minSdk 26 = Android 8), iOS ensuite.
- **Socle** : Riverpod (état), go_router (navigation), dio (HTTP), cache local pour les fiches consultées et les favoris, `cached_network_image` pour les photos.
- **Cartographie** : ~~`flutter_map` et tuiles OpenStreetMap, liste par défaut~~ — remplacé par ADR-012 (Google Maps, carte d'abord).
- **Taille** : publication en App Bundle (AAB) et APK par architecture (`--split-per-abi`) pour rester sous 30 Mo.

## ADR-006 — Stockage des fichiers

- **Décision** : disque local en développement ; stockage compatible S3 en production (Cloudflare R2, sans frais de sortie, déjà utilisé sur CHARM — à confirmer).
- **Deux espaces** : `public` pour les photos (compressées et redimensionnées côté serveur) ; `private` pour les pièces justificatives (liens signés à courte durée, accès journalisé, suppression après décision — voir R11).

## ADR-007 — Authentification par OTP

> **Remplacée par ADR-017** (connexion par compte Google, 20 novembre 2026).

- **Décision** : connexion par numéro de téléphone et code à usage unique. Envoi derrière une interface `OtpSender` avec plusieurs pilotes : `log` (développement), `sms` (fournisseur à choisir), `whatsapp` (plus tard, après vérification Meta).
- **Sécurité** : code haché en base, expiration courte, nombre d'essais limité, limitation par numéro / appareil / IP, numéros +228 uniquement en phase 1, plafond journalier d'envois.

## ADR-008 — Paiement des abonnements

> **Remplacée par ADR-014** (portefeuille prépayé rechargé par FedaPay, 19 novembre 2026).


- **Décision** : interface `PaymentGateway` avec un premier pilote `manual` (paiement sur numéro marchand, saisie et validation dans le back-office). Le pilote d'un agrégateur (FedaPay, CinetPay ou PayDunya) sera ajouté après comparaison des frais, de la couverture TMoney/Flooz et des conditions d'ouverture de compte.
- **Règle** : les abonnements ne sont activés que par une confirmation serveur (webhook vérifié ou validation admin), jamais sur la seule déclaration de l'application.

## ADR-009 — Nom de l'application et identifiants

- **Décision initiale** : nom de code `findsolux`, identifiant Android `com.findsolux.findsolux` provisoire.
- **Décision du 23 novembre 2026** : nom commercial **MecaDispo** (sans accent, pour le domaine et les identifiants), identifiant `com.mecadispo.app` pour Android et iOS. Il ne pourra plus changer après la première publication sur Google Play.
- **Ce qui garde le nom de code** : les noms techniques que les utilisateurs ne voient pas (paquet Dart `findsolux`, bases `findsolux` et `findsolux_test`, `config/findsolux.php`, conteneurs Docker, dossier du dépôt).
- **Conséquences du changement d'identifiant** : ajouter l'application `com.mecadispo.app` dans le projet Firebase et télécharger le nouveau `google-services.json` ; mettre à jour la restriction de la clé Google Maps Android ; créer le client OAuth Android avec ce nouvel identifiant (ADR-017).
- **À faire** : vérifier la disponibilité du nom (Google Play, marque OAPI), réserver le domaine et les comptes de réseaux sociaux.

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

## ADR-012 — Google Maps et recherche « carte d'abord »

- **Contexte** : choix de l'équipe (18 octobre 2026). Remplace la cartographie d'ADR-005 et la recommandation R9.
- **Décision** : carte Google Maps (`google_maps_flutter`) en plein écran sur l'écran Rechercher, avec :
  - une barre de recherche de lieu en haut ; la requête part vers l'API (`GET /places/search`), qui cherche d'abord dans les quartiers connus puis interroge l'API Places de Google ;
  - un bouton « ma position » ;
  - en bas, en surimpression, des cartes de garages triées du plus proche au plus éloigné, la distance affichée en bas de chaque carte (`GET /businesses/search`).
- **Clés** : deux clés distinctes.
  - Android : `MAPS_API_KEY` dans `mobile/android/local.properties` (non suivi par Git), restreinte dans la console Google Cloud à l'application Android (nom de paquet + empreinte SHA-1) et au « Maps SDK for Android ».
  - Serveur : `GOOGLE_MAPS_SERVER_KEY` dans `backend/.env`, restreinte à l'« API Places (New) » et à l'adresse IP du serveur. Elle n'est jamais envoyée au téléphone.
- **Coûts** : l'affichage de la carte dans l'application mobile n'est pas facturé ; les recherches de lieux le sont au-delà du quota gratuit. Le serveur les met en cache 24 h, limite leur fréquence par adresse IP et répond d'abord avec les quartiers connus, sans appel à Google.
- **Classement** : strictement par distance pour l'instant. Quand les formules existeront (lot 9), R2 prévoit de remonter les abonnés à distance comparable : à rediscuter à ce moment-là.


## ADR-013 — Back-office Laravel Blade + Tailwind

- **Contexte** : remplace le choix de Filament d'ADR-004. Le back-office est construit ainsi depuis le 12 novembre 2026.
- **Décision** : back-office sous `/admin`, pages Blade + Tailwind, contrôleurs dans `App\Http\Controllers\Admin`, garde `admin` sur la table `admins` (inchangé). Création d'un compte : `php artisan admin:create`.
- **Conséquences** : écrans sur mesure, sans dépendance supplémentaire. Les tableaux, filtres et exports CSV sont à écrire à la main.

## ADR-014 — Portefeuille prépayé rechargé par FedaPay

- **Contexte** : remplace ADR-008. Le modèle économique v3.0 débite le professionnel à chaque achat d'offre et à chaque acceptation de demande. Ce sont des montants faibles et fréquents, incompatibles avec un paiement Mobile Money par opération.
- **Décision** : un portefeuille par utilisateur (`wallets`), crédité par des recharges FedaPay (TMoney, Flooz) et débité par la plateforme. Chaque mouvement est une ligne `wallet_entries` (type, sens, montant, solde après, référence unique). Le solde est ainsi toujours justifiable, et une même opération ne peut pas être enregistrée deux fois.
- **Règles** :
  - crédit uniquement après confirmation du serveur (webhook signé, puis relecture de la transaction chez FedaPay), jamais sur la seule déclaration de l'application ;
  - pas de découvert : un débit est refusé si le solde est insuffisant ;
  - tout débit verrouille la ligne du portefeuille (`lockForUpdate`) dans une transaction ;
  - pas de retrait en phase 1 ; un remboursement exceptionnel passe par un ajustement de l'administrateur.
- **À vérifier avec le juriste** : statut réglementaire du solde prépayé (BCEAO).

## ADR-015 — Offres, droits et demandes des clients

- **Contexte** : cahier des charges v3.0 (§3, §4).
- **Décision** :
  - **Offres achetées** (`business_subscriptions`) : copie de l'offre au moment de l'achat (type, nom, quantité, prix, période), source (`wallet`, `renewal` ou `admin`), renouvellement automatique choisi par le garagiste, dates des rappels envoyés. Le catalogue (`commercial_offers`) peut changer sans modifier les achats passés.
  - **Droits** : recalculés par un service unique (`BusinessEntitlements`) après chaque achat, octroi, expiration ou modification des services. Ils sont recopiés sur la fiche (`businesses.membership_ends_at`, `businesses.has_unlimited_membership`, `businesses.services_quota`) et sur les services (`business_services.is_published`), pour que la recherche reste une simple requête indexée.
  - **Tâches planifiées** : `subscriptions:process` toutes les 5 minutes (renouvellement tenté 15 minutes avant l'échéance, expiration, rappels J-7 et J-1) ; `requests:expire` chaque minute (fin des signaux de détresse et des demandes de contact échus).
  - **Signal de détresse** (`distress_signals`, `distress_signal_recipients`) : destinataires figés à la création (adhésion active, `is_mobile`, distance ≤ `service_radius_km`). La première acceptation l'emporte : le signal est verrouillé dans la même transaction que le débit.
  - **Demande de contact** (`contact_requests`) et **contacts enregistrés** (`business_contacts`, unique par couple garage–client) : un contact existant rend le numéro du garage visible pour ce client, sans nouveau débit.
  - **Paramètres** (`platform_settings`, clé/valeur) modifiables dans le back-office : délai d'attente d'un signal, délai de réponse d'une demande de contact.
- **Conséquence** : le numéro du garage n'est plus renvoyé par la recherche ni par la fiche publique, sauf au client déjà en contact.
- **Classement (R2)** : la recherche trie par tranche de distance (0–1, 1–3, 3–7 km, au-delà ; `config/findsolux.php`), puis met les garages en vedette en tête de leur tranche, puis par distance exacte.

## ADR-016 — Notifications push Firebase Cloud Messaging

- **Décision** : notifications Laravel en file d'attente, envoyées par un canal `push` derrière une interface `PushSender`, avec trois pilotes :
  - `log` (développement) ;
  - `array` (tests) ;
  - `fcm` (API HTTP v1 de Firebase, jeton signé côté serveur avec le compte de service, sans dépendance supplémentaire).
- Les jetons d'appareil sont enregistrés par l'application (`device_tokens`).
- **Clés hors Git** : `mobile/android/app/google-services.json` (application) et le fichier JSON du compte de service (`FIREBASE_CREDENTIALS`, serveur).
- **Sans configuration Firebase**, l'application fonctionne et rafraîchit les demandes à l'ouverture des écrans. Seules les alertes application fermée manquent.

## ADR-017 — Connexion par compte Google

Remplace ADR-007 (20 novembre 2026).

- **Décision** : connexion et inscription avec le compte Google du téléphone (Google Sign-In, `google_sign_in` 7). L'application envoie le jeton d'identité à `POST /api/v1/auth/google`, qui renvoie un jeton Sanctum comme avant. Plus de code SMS : les routes `/auth/otp` et la table `otp_codes` sont supprimées.
- **Vérification serveur** : signature RS256 contrôlée avec les certificats publics de Google (mis en cache selon `Cache-Control`, rechargés si une clé inconnue apparaît), émetteur Google, destinataire parmi `GOOGLE_SIGN_IN_CLIENT_IDS`, jeton non expiré, adresse e-mail vérifiée. Sans dépendance supplémentaire, comme FCM (ADR-016).
- **Compte** : identifié par l'identifiant Google (`google_id`, le « sub » du jeton), stable même si l'adresse change. L'adresse et le nom Google sont enregistrés à la première connexion ; un nom choisi ensuite n'est pas écrasé.
- **Numéro de téléphone** : il n'identifie plus le compte, mais reste indispensable, car le garage qui accepte une demande paie pour joindre le client. L'application le demande juste après la première connexion et bloque l'accès tant qu'il manque. Le serveur refuse un signal de détresse ou une demande de contact sans numéro (`422`, code `phone_required`). Mobile togolais uniquement, **non vérifié**.
- **Conséquences** :
  - plus de fournisseur SMS nécessaire pour se connecter ;
  - un client peut saisir un faux numéro, et le garage paie alors pour rien. À surveiller pendant le pilote (signalement et remboursement, ou vérification par SMS si un fournisseur est retenu) ;
  - il faut un compte Google sur le téléphone ;
  - les comptes créés avec l'ancien code SMS n'ont pas d'identifiant Google : une connexion Google crée un nouveau compte.
- **Configuration** (Google Cloud, même projet que Firebase si possible) :
  - un client OAuth « Application Web », dont l'identifiant va dans `GOOGLE_SIGN_IN_CLIENT_IDS` (serveur) et dans `--dart-define=GOOGLE_SERVER_CLIENT_ID=…` (application) ;
  - un client OAuth « Android » avec l'identifiant `com.mecadispo.app` et l'empreinte SHA-1 de chaque clé de signature (debug, publication, Google Play).

## ADR-018 — Comptes d'équipe : employés et répondeurs

Décision du 24 novembre 2026, prise avec le porteur du projet.

- **Besoin** : un mécanicien a souvent les mains dans un moteur. Un signal de détresse va au premier garage qui accepte. Un employé ou un répondeur (un seul rôle) doit pouvoir répondre à la place du garagiste.
- **Création des comptes** : pas d'invitation par e-mail. Le garagiste crée lui-même, depuis « Mon équipe », un nom, un identifiant (unique dans toute l'application) et un mot de passe (8 caractères au moins), qu'il transmet à son employé. Un nouveau mot de passe déconnecte le membre de ses téléphones.
- **Connexion** : sur l'écran de connexion, « Je suis employé ou répondeur d'un garage », puis identifiant et mot de passe (`POST /api/v1/auth/team-member`, essais limités par identifiant). Un compte d'équipe est un `users` avec `team_business_id`, sans compte Google ni numéro.
- **Droits** : le membre reçoit les alertes du garage (push) et accepte ou refuse **librement** les signaux de détresse et les demandes de contact. L'acceptation est débitée du portefeuille du garagiste, et le garagiste voit « Accepté par Kodjo ». Le membre voit aussi les clients du garage. Il n'a accès ni à la fiche, ni aux offres, ni au portefeuille, ni à l'espace client (`403 team_member_account`). Il n'apparaît pas sur la fiche publique.
- **Quota** : chaque pack « Employés et répondeurs » donne N places. Les packs actifs se cumulent (`businesses.team_quota`). Sans pack, le garagiste répond seul. Quand un pack expire, les membres au-delà du quota, les plus récents, sont suspendus (`403 team_member_suspended`) sans être supprimés.
