# Backlog du MVP

Découpage en lots livrables. Les identifiants C/P renvoient au cahier des charges v3.0 (§3).
Mis à jour le 20 novembre 2026 (connexion par compte Google).
Ordre indicatif : chaque lot s'appuie sur les précédents.

## Étape 0 — Validation terrain (en parallèle du lot 0)

- [ ] Grille d'entretien garages : services, clientèle, usage de WhatsApp et Mobile Money, disposition à payer l'adhésion et le prix d'une demande acceptée, zone d'intervention (signal de détresse).
- [ ] 30 à 50 entretiens dans 2 ou 3 quartiers à forte densité de garages.
- [ ] Test simple : annuaire de 20 garages (page web ou groupe WhatsApp), mesure des contacts.
- [x] Choix du nom commercial : **MecaDispo** (23 novembre 2026, ADR-009).
- [ ] Vérifier la disponibilité de MecaDispo (Google Play, marque OAPI) et réserver le domaine et les comptes réseaux sociaux.
- [ ] Slogan en mina (par exemple « Ton mécano, tout de suite »), validé par des locuteurs natifs pendant les entretiens.
- [ ] Création de la structure juridique (préalable au compte marchand).

## Lot 0 — Socle technique

- [x] Dépôt Git, structure monorepo, documentation de cadrage.
- [x] MySQL (XAMPP ou Docker), base de test dédiée (ADR-011).
- [x] Projet Laravel : MySQL, Sanctum, français, point de contrôle `/api/v1/health`.
- [x] Projet Flutter : Android 8+, Riverpod, go_router, dio, français.
- [x] Application unique : espace client et espace garagiste (ADR-010).
- [ ] Intégration continue : tests back-end et `flutter analyze` / `flutter test` à chaque push.
- [ ] Environnement de recette sur VPS.

## Lot 1 — Référentiels

- [x] Tables villes, quartiers, métiers, services, types de véhicules.
- [x] Données initiales : 94 quartiers de Lomé et périphérie (nom + centre, source OpenStreetMap), 13 services de mécanique, 4 types de véhicules.
- [x] API publique `GET /api/v1/reference-data` (≈ 3 Ko compressée, ETag pour ne rien retélécharger si inchangé).
- [x] Mise en cache des référentiels dans l'application (ETag, utilisables hors connexion).

## Lot 2 — Authentification (ADR-017 : compte Google)

- [x] ~~Connexion par code SMS (ADR-007)~~ : remplacée le 20 novembre 2026 par la connexion Google.
- [x] API : connexion Google (`POST /auth/google`, jeton d'identité vérifié avec les certificats de Google, jeton Sanctum), profil (`GET/PATCH /me` : nom, numéro), déconnexion.
- [x] Application : bouton « Continuer avec Google », session conservée dans le stockage sécurisé (connecté même hors ligne), espace garagiste réservé aux connectés, déconnexion (le compte Google est aussi oublié).
- [x] Numéro où joindre le client : demandé après la première connexion (accès bloqué tant qu'il manque), modifiable depuis le compte ; signal de détresse et demande de contact refusés sans numéro. Mobile togolais, non vérifié.
- [ ] Projet Google Cloud : clients OAuth « Application Web » (`GOOGLE_SIGN_IN_CLIENT_IDS`, `GOOGLE_SERVER_CLIENT_ID`) et « Android » (identifiant de l'application + empreintes SHA-1).
- [ ] Connexion Google sur iOS : `GIDClientID` et schéma d'URL dans `Info.plist`, `GOOGLE_IOS_CLIENT_ID`.
- [ ] Faux numéros de clients : suivre pendant le pilote, décider d'un signalement avec remboursement ou d'une vérification par SMS.

## Lot 3 — Fiches professionnelles (P1, P3)

- [x] API inscription express (`POST /my-business`) : nom, numéros d'appel et WhatsApp, position (contrôlée dans les limites du Togo), quartier, véhicules, services. Fiche publiée immédiatement, une fiche par utilisateur.
- [x] API complément de fiche (`PATCH /my-business`) : description, point de repère, se déplace + rayon, dépannage 24 h/24 (R7).
- [x] API services et prix (`PUT /my-business/services`), prix par véhicule possible ; horaires (`PUT /my-business/opening-hours`), plusieurs plages par jour.
- [x] API photos : 8 au maximum, redimensionnées (1 280 px), converties en WebP avec vignette 400 px, métadonnées GPS supprimées ; ordre et suppression.
- [x] Application : inscription express sur un seul écran (nom, WhatsApp prérempli avec le numéro du compte, position GPS sur place avec précision affichée, quartier proposé automatiquement, véhicules, services).
- [x] Application : fiche avec éléments à compléter ; écrans informations, services et prix (fourchette par service), horaires (lun–sam 8 h–18 h proposés), photos (appareil photo ou galerie, réduites avant envoi, photo principale, suppression).
- [x] Carte Google pour ajuster la position du garage à la main (repère fixe, vue satellite), depuis l'inscription et la modification de la fiche.
- [x] ~~Photos selon la formule~~ : sans objet en v3.0 (toute fiche visible a une adhésion ; photos visibles pour toutes).

## Lot 4 — Recherche (C1, C2)

- [x] API `GET /businesses/search` : garages publiés autour d'un point, du plus proche au plus éloigné, rayon 10 km par défaut (30 au plus), garagistes qui se déplacent visibles dans leur zone d'intervention, filtres véhicule, service et « ouvert maintenant » (horaires de Lomé).
- [x] API `GET /places/search` : quartiers connus (sans accent requis) puis lieux Google (clé serveur, cache 24 h, lieux hors Togo écartés).
- [x] Application : carte Google Maps plein écran, recherche de lieu (via l'API), bouton « ma position », « Rechercher dans cette zone » après un déplacement de la carte, cartes des garages en bas triées par distance avec la distance tout en bas, repères synchronisés avec les cartes, filtres « ouvert maintenant » et véhicule, rayon élargi si aucun résultat (ADR-012).
- [x] Clés Google hors Git : `mobile/android/local.properties` (Android), `ios/Flutter/Secrets.xcconfig` (iOS), `backend/.env` (serveur).
- [x] Classement R2 : tranche de distance, puis garages en vedette, puis distance exacte.

## Lot 5 — Fiche publique et contact (C3, C4)

- [x] API fiche publique `GET /businesses/{slug}` (sans compte), lien de partage.
- [x] API événements `POST /businesses/{slug}/events` (vue, appel, WhatsApp, itinéraire, partage) : un appareil compte une fois par jour et par action ; ni le propriétaire ni les robots ne sont comptés.
- [x] Pages web publiques `/garages/{slug}` (R4) : boutons Appeler, WhatsApp (message pré-rempli), Itinéraire ; aperçu WhatsApp (Open Graph) ; vues et clics comptés.
- [x] Application : fiche détaillée ouverte depuis les cartes de résultats (photos, état, distance, services et prix, horaires de la semaine, véhicules, zone d'intervention) ; boutons Appeler, WhatsApp (message pré-rempli), Itinéraire, Partager (lien de la page web) ; vues et clics envoyés sans faire attendre le client (identifiant d'appareil anonyme).

## Lot 6 — Avis et signalements (C5, C6, P7)

- [ ] Publication d'un avis (compte vérifié, un avis par pro).
- [ ] Signalement de fiche ou d'avis ; réponse du pro.

## Lot 7 — Back-office (§3.3, ADR-013 : Blade + Tailwind)

- [x] Connexion administrateur (`php artisan admin:create`), garde séparée.
- [x] Catalogue d'offres (prix, durée limitée ou illimitée, quantité, activation).
- [x] Garages en vedette (rang, période).
- [x] Garages et adhésions : état de l'adhésion, quota de services, solde ; octroi manuel d'une offre (offre de lancement).
- [x] Paramètres : délai d'attente d'un signal de détresse, délai de réponse d'une demande de contact.
- [x] Tableau de bord : garages visibles, signaux et demandes acceptés sur 7 jours.
- [ ] Rôle modérateur.
- [ ] Validation des vérifications, modération des avis, traitement des signalements.
- [ ] Suivi des portefeuilles (recharges, débits), ajustement manuel ; exports CSV.

## Lot 8 — Vérification (P2)

- [ ] Envoi des pièces depuis l'application (espace privé).
- [ ] Circuit de validation et badge, ouvert à tous (R1) ; purge des pièces après décision (R11).

## Lot 9 — Portefeuille et offres (P4, ADR-014, ADR-015)

- [x] Portefeuille : recharge TMoney / Flooz par FedaPay, crédit après confirmation serveur (webhook signé), historique.
- [x] Débit du portefeuille sans découvert, mouvements à référence unique.
- [x] Achat d'offres depuis le portefeuille : adhésion (prolongation à la suite de l'adhésion en cours), packs de services (cumulables).
- [x] Droits : fiche invisible sans adhésion ; services publiés dans la limite des packs.
- [x] Renouvellement automatique réglable par le garagiste, offre par offre ; rappels J-7 et J-1 ; expiration sans perte de données (`subscriptions:process`, toutes les 5 minutes).
- [x] Application : écran Offres (droits actuels, offres en cours, achat, renouvellement), solde insuffisant → recharge.
- [x] Packs « employés et répondeurs » (ADR-018) : le garagiste crée les comptes de son équipe (identifiant, mot de passe) dans la limite de ses packs ; connexion « membre d'une équipe » ; les membres reçoivent les alertes et acceptent au nom du garage ; « Accepté par Kodjo » visible du garagiste.
- [ ] Avis juridique sur le solde prépayé (BCEAO) avant le pilote.

## Lot 9 bis — Demandes des clients (C4–C6, P5–P6)

- [x] Signal de détresse : envoyé aux garages à adhésion active dont la zone d'intervention couvre le client ; premier qui accepte débité ; délai réglable ; un signal ouvert par client, 5 par jour.
- [x] Demande de contact depuis la fiche : numéro du garage masqué (recherche, fiche, page web) ; acceptation débitée une fois par couple client–garage ; contact enregistré chez les deux.
- [x] Fin des demandes sans réponse (`requests:expire`, chaque minute).
- [x] Application client : bouton « En panne ? » et écran du signal (attente, garage qui accepte, nouvel essai) ; « Demander le contact » sur la fiche ; onglet « Mes garages ».
- [x] Application garagiste : onglet « Demandes » (détresse, contact, mes clients), acceptation avec rappel du prix, recharge proposée.
- [ ] Signalement des faux signaux de détresse.
- [ ] Messagerie dans l'application (après le pilote).

## Lot 10 — Notifications et statistiques (P7, P8)

- [x] Notifications push Firebase (ADR-016) : demandes reçues, réponses, rappels et échéances d'offres ; jeton enregistré à la connexion, retiré à la déconnexion.
- [ ] Projet Firebase : `google-services.json` (application) et compte de service (`FIREBASE_CREDENTIALS`, serveur).
- [ ] SMS : nouvel avis, rappel d'échéance, reçu de recharge.
- [ ] Agrégation quotidienne des statistiques.
- [ ] Écran statistiques : vues, demandes reçues et acceptées, par semaine.

## Lot 11 — Favoris, hors connexion, performance (C9, §5)

- [ ] Favoris locaux sans compte, synchronisés si connecté.
- [ ] Cache des fiches consultées, messages hors réseau.
- [ ] Tests sur Android d'entrée de gamme et réseau 3G simulé ; taille APK < 30 Mo.

## Lot 12 — Légal et publication

- [ ] CGU, politique de confidentialité, charte pro (avec un juriste).
- [ ] Déclaration IPDCP.
- [ ] Fiche Google Play, publication.

## Après le pilote

- [ ] Avis par QR code en atelier, marqués « visite confirmée » (R5).
