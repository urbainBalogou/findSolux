# Backlog du MVP

Découpage en lots livrables. Les identifiants C/P renvoient au cahier des charges (§3).
Ordre indicatif : chaque lot s'appuie sur les précédents.

## Étape 0 — Validation terrain (en parallèle du lot 0)

- [ ] Grille d'entretien garages : services, clientèle, usage de WhatsApp et Mobile Money, disposition à payer (3 000 / 5 000 / 10 000 FCFA).
- [ ] 30 à 50 entretiens dans 2 ou 3 quartiers à forte densité de garages.
- [ ] Test simple : annuaire de 20 garages (page web ou groupe WhatsApp), mesure des contacts.
- [ ] Choix du nom commercial et réservation du domaine et des comptes réseaux sociaux.
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

## Lot 2 — Authentification OTP

- [x] API : demande de code (`POST /auth/otp`), vérification et jeton Sanctum (`POST /auth/otp/verify`), profil (`GET/PATCH /me`), déconnexion.
- [x] Application : écrans numéro et code (renvoi après 60 s), session conservée dans le stockage sécurisé (connecté même hors ligne), espace garagiste réservé aux connectés, déconnexion.
- [ ] Lecture automatique du SMS sur Android (SMS Retriever).
- [x] Pilote `log` (dev, interdit en production) et `array` (tests).
- [ ] Pilote SMS : reporté (décision du 15 octobre 2026). Le pilote « log » étant interdit en production, un canal d'envoi du code sera nécessaire avant le pilote terrain.
- [x] Limitations anti-abus (ADR-007) : numéros mobiles togolais uniquement, 60 s entre deux envois, 5 codes par numéro et par jour, 5 essais par code, plafond global journalier, limites larges par IP (CGNAT).

## Lot 3 — Fiches professionnelles (P1, P3)

- [x] API inscription express (`POST /my-business`) : nom, numéros d'appel et WhatsApp, position (contrôlée dans les limites du Togo), quartier, véhicules, services. Fiche publiée immédiatement, une fiche par utilisateur.
- [x] API complément de fiche (`PATCH /my-business`) : description, point de repère, se déplace + rayon, dépannage 24 h/24 (R7).
- [x] API services et prix (`PUT /my-business/services`), prix par véhicule possible ; horaires (`PUT /my-business/opening-hours`), plusieurs plages par jour.
- [x] API photos : 8 au maximum, redimensionnées (1 280 px), converties en WebP avec vignette 400 px, métadonnées GPS supprimées ; ordre et suppression.
- [x] Application : inscription express sur un seul écran (nom, WhatsApp prérempli avec le numéro du compte, position GPS sur place avec précision affichée, quartier proposé automatiquement, véhicules, services).
- [x] Application : fiche avec éléments à compléter ; écrans informations, services et prix (fourchette par service), horaires (lun–sam 8 h–18 h proposés), photos (appareil photo ou galerie, réduites avant envoi, photo principale, suppression).
- [ ] Carte pour ajuster la position à la main (avec le fournisseur de tuiles, lot 4).
- [ ] Photos visibles publiquement selon la formule (lot 9) : le cahier des charges réserve les photos aux formules payantes.

## Lot 4 — Recherche (C1, C2)

- [x] API `GET /businesses/search` : garages publiés autour d'un point, du plus proche au plus éloigné, rayon 10 km par défaut (30 au plus), garagistes qui se déplacent visibles dans leur zone d'intervention, filtres véhicule, service et « ouvert maintenant » (horaires de Lomé).
- [x] API `GET /places/search` : quartiers connus (sans accent requis) puis lieux Google (clé serveur, cache 24 h, lieux hors Togo écartés).
- [ ] Application : carte Google Maps, recherche de lieu, « ma position », cartes des garages en bas triées par distance (ADR-012), filtres.
- [ ] Classement par formule à distance comparable (R2) : à rediscuter avec le lot 9.

## Lot 5 — Fiche publique et contact (C3, C4)

- [ ] Fiche détaillée dans l'application.
- [ ] Boutons Appeler, WhatsApp (message pré-rempli), Itinéraire, Partager.
- [ ] Enregistrement des événements (vues, clics).
- [ ] Pages web publiques des fiches, partageables sur WhatsApp (R4).

## Lot 6 — Avis et signalements (C5, C6, P7)

- [ ] Publication d'un avis (compte vérifié, un avis par pro).
- [ ] Signalement de fiche ou d'avis ; réponse du pro.

## Lot 7 — Back-office Filament (§3.3)

- [ ] Comptes admin et modérateur.
- [ ] Validation des vérifications, modération des avis, traitement des signalements.
- [ ] Gestion des fiches et des abonnements (activation manuelle, prolongation, offre de lancement).
- [ ] Tableau de bord et exports CSV.

## Lot 8 — Vérification (P2)

- [ ] Envoi des pièces depuis l'application (espace privé).
- [ ] Circuit de validation et badge, ouvert à toutes les formules (R1) ; purge des pièces après décision (R11).

## Lot 9 — Abonnements (P4)

- [ ] Formules configurables.
- [ ] Paiement manuel assisté (ADR-008).
- [ ] Expiration automatique → formule gratuite ; rappel J-7.
- [ ] Pilote agrégateur Mobile Money (après validation de la disposition à payer).

## Lot 10 — Notifications et statistiques (P5, P6)

- [ ] SMS : nouvel avis, rappel d'échéance, reçu de paiement.
- [ ] Agrégation quotidienne des statistiques.
- [ ] Écran statistiques : compteur mensuel pour tous, détail par semaine et par canal pour les abonnés (R3).

## Lot 11 — Favoris, hors connexion, performance (C7, §5)

- [ ] Favoris locaux sans compte, synchronisés si connecté.
- [ ] Cache des fiches consultées, messages hors réseau.
- [ ] Tests sur Android d'entrée de gamme et réseau 3G simulé ; taille APK < 30 Mo.

## Lot 12 — Légal et publication

- [ ] CGU, politique de confidentialité, charte pro (avec un juriste).
- [ ] Déclaration IPDCP.
- [ ] Fiche Google Play, publication.

## Après le pilote

- [ ] Avis par QR code en atelier, marqués « visite confirmée » (R5).
