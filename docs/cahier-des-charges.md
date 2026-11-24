# Cahier des charges

**Application de mise en relation avec les professionnels de proximité**
Phase 1 : mécaniciens et garages – Lomé, Togo
Version 3.0 – Novembre 2026 · Nom de l’application : **MecaDispo** (nom de code technique : `findsolux`)

> Document de référence. La version 3.0 remplace le modèle d'abonnement des
> versions 2.x par un **portefeuille prépayé** du professionnel, un **catalogue
> d'offres** et un **débit à l'acceptation des demandes** des clients (signal de
> détresse et demande de contact). Les sections concernées sont réécrites
> directement ; la liste des changements est au [§15](#15-amendements-v30).
> Les amendements v2.1 encore valables sont au [§14](#14-amendements-v21).
> Justifications d'origine dans [analyse-et-recommandations.md](analyse-et-recommandations.md).

---

## 1. Présentation du projet

### 1.1 Contexte

Au Togo, trouver un mécanicien fiable repose presque entièrement sur le bouche-à-oreille. Les automobilistes et conducteurs de moto-taxi (zémidjans) ne disposent d'aucun moyen simple de comparer les garages, de connaître leurs services, leurs horaires ou la qualité de leur travail. De leur côté, de nombreux garages et mécaniciens indépendants n'ont aucune visibilité en ligne et dépendent de leur emplacement physique.

Le téléphone mobile est omniprésent, WhatsApp est l'outil de communication dominant et le Mobile Money (TMoney, Flooz) est largement utilisé pour les paiements du quotidien. Ces conditions rendent possible une plateforme de mise en relation simple, légère et adaptée aux usages locaux.

### 1.2 Objectifs

- Mettre en relation gratuitement les particuliers avec des mécaniciens et garages de proximité, y compris en urgence (panne, crevaison) grâce au signal de détresse.
- Offrir aux professionnels une visibilité qualifiée et des clients qui les sollicitent réellement.
- Générer des revenus récurrents grâce aux offres et aux mises en relation payées par les professionnels depuis leur portefeuille.
- Valider le modèle à Lomé avant de l'étendre à d'autres villes puis à d'autres métiers techniques (électriciens, plombiers, frigoristes, menuisiers, etc.).

### 1.3 Périmètre

- **Inclus (phase 1)** : annuaire géolocalisé de mécaniciens et garages à Lomé et environs, fiches professionnelles, signal de détresse, demandes de contact, avis, portefeuille prépayé et offres des professionnels.
- **Exclus (phase 1)** : messagerie intégrée (prévue plus tard), planning de rendez-vous, marketplace de pièces, publicité, paiement des travaux via la plateforme, autres métiers et autres villes.

### 1.4 Principe directeur

L'application est neutre : elle ne prend aucune commission sur les prestations et **ne fait transiter aucun paiement de travaux**. Le client ne paie rien à la plateforme ; il règle les travaux directement avec le professionnel.

La plateforme se rémunère uniquement auprès des professionnels, par débit de leur portefeuille prépayé :
- l'**adhésion**, qui donne le droit d'être visible et de recevoir des demandes ;
- les **packs de services**, qui fixent le nombre de services publiés sur la fiche ;
- l'**acceptation d'une demande** d'un client (signal de détresse ou demande de contact), qui donne accès aux coordonnées du client.

## 2. Marché cible et utilisateurs

### 2.1 Zone de lancement

Lomé et sa périphérie (Agoè, Adidogomé, Baguida, Aflao-Gakli, etc.), avec une concentration initiale sur quelques quartiers à forte densité de garages afin d'éviter une plateforme « vide ». Extension prévue ensuite vers Kara, Sokodé, Atakpamé et Kpalimé.

### 2.2 Profils d'utilisateurs

| Profil | Besoins | Contraintes |
|---|---|---|
| Automobilistes | Trouver un garage proche, connaître les services et les avis, obtenir de l'aide rapidement en cas de panne. | Peu de temps, méfiance envers les prix et la qualité. |
| Conducteurs de moto-taxi | Dépannage rapide sur place, réparation à bas coût, mécanicien proche. | Smartphone d'entrée de gamme, forfait data limité. |
| Mécaniciens indépendants et garages | Recevoir des clients qualifiés, être visibles, se différencier par la réputation. | Peu d'habitude des outils numériques, paiement en Mobile Money. |
| Administrateur (équipe du projet) | Valider les profils, modérer les avis, gérer les offres et les paramètres, suivre les portefeuilles. | Volume croissant de profils à vérifier. |

### 2.3 Contraintes du contexte togolais

- **Langues** : interface en français, vocabulaire simple. Éwé et kabiyè en évolution ultérieure. Usage fort des icônes.
- **Connexion** : réseau parfois instable et data coûteuse. Application légère (< 30 Mo), chargement rapide, utilisable avec une connexion faible.
- **Appareils** : majorité d'Android d'entrée de gamme. iOS secondaire en phase 1.
- **Paiement** : FCFA (XOF). Mobile Money (TMoney, Flooz) pour recharger le portefeuille.
- **Adresses** : peu d'adresses formelles. Localisation GPS et points de repère (« en face de… », « carrefour… ») essentiels.

## 3. Fonctionnalités du MVP (Phase 1)

### 3.1 Côté client (gratuit)

La recherche et la consultation des fiches se font **sans compte**. L'envoi d'un signal de détresse ou d'une demande de contact exige un compte, ouvert avec Google (ADR-017), et le numéro où joindre le client, saisi après la première connexion : ce numéro est transmis au garage qui accepte.

| N° | Fonctionnalité | Description | Priorité |
|---|---|---|---|
| C1 | Recherche géolocalisée | Garages proches sur une carte, avec distance estimée. Recherche aussi par quartier ou par lieu. Seuls les garages dont l'adhésion est active apparaissent. | Haute |
| C2 | Filtres | Type de véhicule (voiture, moto, poids lourd, tricycle), service, note, « ouvert maintenant ». | Haute |
| C3 | Fiche du professionnel | Nom, photos, services publiés et fourchettes de prix indicatives, horaires, adresse avec point de repère, zone d'intervention, badge « Vérifié ». **Le numéro du garage n'est pas affiché** tant que le garage n'a pas accepté de partager son contact avec ce client. | Haute |
| C4 | Demande de contact | Bouton « Contacter » sur la fiche : le garage reçoit une alerte. S'il accepte, son portefeuille est débité du prix d'acceptation, le client obtient le numéro du garage (appel, WhatsApp) et le garage obtient celui du client. Ce débit n'a lieu **qu'une fois par couple client–garage** : le contact est ensuite enregistré chez les deux et les boutons Appeler / WhatsApp deviennent directs. Itinéraire toujours disponible. | Haute |
| C5 | Signal de détresse | Le client décrit son problème (ex. : « crevaison du pneu avant ») avec, s'il le souhaite, le service et le véhicule concernés ; sa position GPS est jointe. Le signal est envoyé à **tous les garages à adhésion active dont la zone d'intervention couvre la position du client**. Le premier garage qui accepte est débité du prix d'acceptation ; il reçoit la position et le numéro du client, et le client reçoit le numéro du garage. Le signal se termine à l'acceptation, à l'annulation par le client ou à la fin du délai d'attente (réglable dans le back-office) ; le client peut alors en envoyer un nouveau. | Haute |
| C6 | Mes garages | Liste des garages avec lesquels le client est déjà en contact (C4, C5), avec appel et WhatsApp directs. Suivi des demandes en cours. | Haute |
| C7 | Avis et notes | Note de 1 à 5 et commentaire, réservés aux clients en contact avec le garage (C6). Un avis par client et par garage. | Haute |
| C8 | Signalement | Signaler un profil ou un avis abusif. | Moyenne |
| C9 | Favoris | Enregistrer des garages pour les retrouver facilement. | Basse |

### 3.2 Côté professionnel

| N° | Fonctionnalité | Description | Priorité |
|---|---|---|---|
| P1 | Inscription et profil | Création avec un compte Google (ADR-017). Nom, localisation sur carte, services, horaires, photos, zone d'intervention (« se déplace » et rayon). La fiche n'est visible qu'avec une adhésion active (P4). | Haute |
| P2 | Vérification | Pièce d'identité et, si disponible, preuve d'activité. Validation manuelle par l'administrateur, puis badge « Vérifié », indépendant des offres achetées (R1). | Haute |
| P3 | Gestion des services | Ajout et modification des services par type de véhicule, avec prix indicatif. Les services sont enregistrés librement, mais **seuls ceux couverts par les packs de services actifs sont publiés** (aucun service publié sans pack). | Haute |
| P4 | Portefeuille et offres | Recharge du portefeuille par Mobile Money (TMoney, Flooz). Achat d'offres du catalogue par débit du portefeuille : adhésion, packs de services. Suivi des échéances ; renouvellement automatique activable ou désactivable par le garagiste, offre par offre ; rappels 7 jours et 1 jour avant l'échéance. Historique des mouvements. | Haute |
| P5 | Demandes reçues | Alertes des signaux de détresse et des demandes de contact. Le garagiste accepte (débit du portefeuille, accès au numéro et, pour un signal de détresse, à la position du client) ou refuse. Si le solde est insuffisant, l'application propose de recharger avant d'accepter. | Haute |
| P6 | Mes clients | Liste des clients en contact (numéro, date, origine : signal de détresse ou demande de contact). | Haute |
| P7 | Notifications | Notifications push (application fermée comprise) : nouveau signal de détresse, nouvelle demande de contact, rappels d'échéance, renouvellement effectué ou impossible, nouvel avis. SMS en complément plus tard. | Haute |
| P8 | Statistiques simples | Vues du profil, demandes reçues et acceptées, par semaine. | Moyenne |
| P9 | Réponse aux avis | Réponse publique à un avis. | Basse |

### 3.3 Côté administrateur (back-office web)

- Tableau de bord : professionnels inscrits, adhésions actives, demandes reçues et acceptées, recharges et débits.
- Catalogue d'offres : création, prix, durée (limitée ou illimitée), quantité, activation.
- Paramètres de la plateforme : délai d'attente d'un signal de détresse, délai de réponse d'une demande de contact.
- Octroi manuel d'une offre à un garage (offre de lancement, geste commercial), sans débit du portefeuille.
- Garages en vedette (mise en avant).
- Validation ou refus des demandes de vérification.
- Modération des avis et traitement des signalements.
- Suivi des portefeuilles : recharges, achats, débits d'acceptation.
- Export des données (CSV) pour le suivi commercial.

### 3.4 Reporté aux phases suivantes

Messagerie intégrée entre client et garage, planning et prise de rendez-vous, marketplace de pièces, fidélité, API ouverte, publicité ciblée, avis par QR code (R5).

## 4. Modèle économique

### 4.1 Principe

Clients gratuits. Le professionnel alimente un **portefeuille prépayé** par Mobile Money ; chaque achat d'offre et chaque acceptation de demande est un **débit** de ce portefeuille. Aucune commission sur les prestations, aucun paiement de travaux via la plateforme.

### 4.2 Catalogue d'offres

Les offres sont configurées dans le back-office (nom, prix, durée, quantité, ordre, activation) et modifiables sans déploiement.

| Type d'offre | Effet | Durée |
|---|---|---|
| Adhésion mécanicien | Sans adhésion active, la fiche est **invisible** (recherche, fiche publique, page web) et le garage **ne reçoit aucune demande**. | Limitée (jours, mois, années) ou illimitée. |
| Pack de services | Donne le droit de publier *N* services. Les packs actifs se cumulent. Sans pack, aucun service n'est publié. | Limitée ou illimitée. |
| Employés et répondeurs | Nombre de membres de l'équipe (employés ou répondeurs) : comptes créés par le garagiste (identifiant et mot de passe), qui reçoivent les demandes et les acceptent au nom du garage, débitées de son portefeuille (ADR-018). Les packs actifs se cumulent ; au-delà, les membres les plus récents sont suspendus. | Limitée ou illimitée. |

En dehors du catalogue, le **prix d'une demande acceptée** est un tarif unique, réglé dans le back-office (« Tarifs et offres ») : il est débité à chaque acceptation d'un signal de détresse, et à la première acceptation d'une demande de contact pour un même client. Il est le même pour tous les garages, quelles que soient leurs offres.

### 4.3 Règles

- **Pas de découvert** : un achat ou une acceptation est refusé si le solde est insuffisant ; le solde n'est jamais négatif.
- **Adhésion** : une nouvelle adhésion achetée pendant une adhésion en cours commence à la fin de celle-ci (prolongation). Une adhésion illimitée ne peut pas être prolongée.
- **Packs de services** : chaque pack commence à l'achat et s'ajoute aux packs actifs.
- **Renouvellement** : chaque offre achetée porte un réglage « renouvellement automatique », choisi par le garagiste (désactivé par défaut) et modifiable à tout moment.
- **Rappels** : notification 7 jours puis 1 jour avant l'échéance de chaque offre limitée, en précisant si elle sera renouvelée et si le solde est suffisant.
- **Échéance** : l'offre est renouvelée si le renouvellement automatique est activé, que l'offre est toujours au catalogue et que le solde suffit (au prix en vigueur) ; sinon elle expire. Une adhésion expirée rend la fiche invisible ; un pack expiré masque les services au-delà du nouveau quota. **Aucune donnée n'est supprimée** : tout redevient visible au rachat.
- **Prix d'acceptation** : tarif unique fixé dans le back-office, indépendant des offres souscrites (24 novembre 2026). À 0, l'acceptation est gratuite.
- **Offre de lancement** : octroi manuel par l'administrateur (ex. : adhésion et pack offerts aux 50 premiers garages du pilote), enregistré comme tel dans l'historique.

### 4.4 Ce qui justifie la dépense du garagiste

- Clients qui sollicitent réellement le garage, souvent en urgence et à proximité (signal de détresse).
- Visibilité auprès de clients qui cherchent activement un mécanicien dans leur quartier.
- Badge « Vérifié », avis clients, statistiques des demandes reçues.
- Plus tard : messagerie, rendez-vous, relation client.

### 4.5 Autres revenus (plus tard)

Mise en avant payante (les garages en vedette sont aujourd'hui choisis par l'administrateur) ; partenariats (assurances, pièces, dépanneuses, stations-service) ; publicité locale ciblée.

## 5. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| Performance | Liste de résultats en < 3 s en 3G. APK Android < 30 Mo. |
| Alertes | Signal de détresse et demande de contact notifiés par push en quelques secondes, application fermée comprise. |
| Faible connexion | Cache des fiches récemment consultées et des favoris. Images compressées. Messages d'erreur clairs hors réseau. |
| Compatibilité | Android 8+ en priorité. iOS 14+ ensuite. Back-office sur navigateurs récents. |
| Simplicité | Français simple, icônes parlantes, signal de détresse en trois touches maximum. Tests d'utilisabilité avec de vrais mécaniciens. |
| Sécurité | Connexion par compte Google (jeton vérifié par le serveur), HTTPS, mots de passe hachés pour l'administration, sauvegardes quotidiennes. Portefeuille : crédit uniquement après confirmation serveur du paiement, mouvements horodatés et non modifiables, opérations concurrentes protégées (verrous). |
| Confidentialité | Numéro et position d'un client transmis uniquement au garage qui accepte sa demande. |
| Disponibilité | 99 % mensuel. |
| Évolutivité | Autres villes et métiers sans refonte (catégories, services, offres et paramètres configurables). |

## 6. Architecture technique

| Composant | Choix | Justification |
|---|---|---|
| Application mobile | Flutter (une application, deux espaces : client et garagiste) | Une base de code, bonnes performances sur appareils modestes (ADR-005, ADR-010). |
| Back-office | Laravel, pages Blade + Tailwind | Écrans d'administration sur mesure (ADR-013). |
| Back-end | API REST Laravel | ADR-002. |
| Base de données | MySQL 8 | Requêtes de proximité par latitude/longitude (ADR-011). |
| Stockage | Compatible S3 | Photos et pièces justificatives (ADR-006). |
| Cartographie | Google Maps | ADR-012. |
| Authentification | Compte Google (Google Sign-In) | ADR-017, remplace ADR-007 (OTP SMS). |
| Paiement | FedaPay (TMoney, Flooz) pour recharger le portefeuille | ADR-014. |
| Notifications | Push Firebase Cloud Messaging ; SMS plus tard | ADR-016. |
| Hébergement | VPS + sauvegardes automatiques | Coût maîtrisé. |

## 7. Portefeuille et paiements

- Recharge par TMoney (Yas Togo) et Flooz (Moov Africa) via l'agrégateur FedaPay ; montant minimal et maximal configurables.
- Le portefeuille n'est crédité qu'après confirmation par le serveur : notification signée de FedaPay (webhook), puis relecture de la transaction auprès de FedaPay (montant, devise, référence).
- Chaque mouvement (recharge, achat d'offre, acceptation de demande, remboursement, ajustement) est enregistré avec le solde après opération.
- Reçu dans l'application (historique) ; reçu par SMS plus tard.
- Pas de retrait du solde par le garagiste en phase 1 ; un remboursement exceptionnel est traité manuellement par l'administrateur (ajustement). Conditions à préciser dans les conditions générales (§8.2).

## 8. Confiance, modération et aspects légaux

### 8.1 Confiance et qualité

- Badge « Vérifié » après contrôle d'identité et d'activité, quelles que soient les offres achetées.
- Avis réservés aux clients en contact avec le garage ; un seul avis par client et par professionnel.
- Signal de détresse : un seul signal en cours par client ; limitation du nombre de signaux par jour pour éviter les abus ; signalement des faux signaux.
- Détection de comportements suspects (nombreux avis depuis un même appareil, avis répétitifs) et modération manuelle.
- Charte d'usage pour les professionnels.

### 8.2 Aspects légaux

- Conformité à la loi togolaise sur la protection des données personnelles, déclaration auprès de l'IPDCP (à confirmer avec un juriste). Le partage du numéro et de la position du client avec le garage qui accepte doit être expliqué et accepté dans les CGU.
- CGU et politique de confidentialité en français, accessibles dans l'application.
- Structure juridique enregistrée (nécessaire pour le compte marchand FedaPay).
- Conditions de vente des offres claires (durée, renouvellement, absence de retrait du solde, remboursement exceptionnel).
- **À vérifier avec le juriste** : statut du solde prépayé au regard de la réglementation BCEAO sur la monnaie électronique (solde utilisable uniquement pour les services de la plateforme).
- La plateforme n'est pas partie à la relation client–professionnel ni responsable de la qualité des travaux.

## 9. Plan de lancement commercial

| Étape | Durée | Actions |
|---|---|---|
| 0. Validation | 4 à 6 semaines | Rencontrer 30 à 50 garages. Mesurer l'intérêt, la disposition à payer l'adhésion et le prix acceptable par demande acceptée. Tester une version simple (page web ou groupe WhatsApp de 20 garages). |
| 1. Développement MVP | 3 à 4 mois | App + back-office. Constitution de la base de garages par démarchage terrain. |
| 2. Pilote | 3 mois | 2 à 3 quartiers, 50 garages inscrits avec adhésion et pack offerts (octroi manuel) et un crédit de démarrage éventuel. Retours, ajustements des prix. |
| 3. Monétisation | Dès le mois 4 du pilote | Fin des offres de lancement, recharges et achats réels. Suivi des renouvellements et des acceptations. |
| 4. Extension | Après validation | Autres quartiers, villes de l'intérieur, nouveaux métiers. |

- **Acquisition pros** : équipe terrain qui accompagne l'inscription sur place ; associations de mécaniciens et transporteurs ; parrainage.
- **Acquisition clients** : Facebook, WhatsApp, TikTok ; stickers dans les garages ; syndicats de zémidjans, auto-écoles, radios, influenceurs ; mise en avant du signal de détresse.

## 10. Indicateurs de succès

| Indicateur | 6 mois (pilote) | 12 mois |
|---|---|---|
| Garages inscrits à Lomé | 50 à 100 | 300 |
| Garages vérifiés | 70 % | 80 % |
| Garages avec adhésion active payée | 10 à 15 % | 20 à 25 % |
| Signaux de détresse acceptés | > 60 % | > 75 % |
| Délai médian d'acceptation d'un signal | < 5 min | < 3 min |
| Utilisateurs actifs / mois | 2 000 | 10 000 |
| Demandes acceptées / mois | 1 000 | 7 000 |
| Satisfaction des pros | > 4/5 | > 4/5 |
| Revenu mensuel récurrent | Premiers achats | Selon seuil de rentabilité |

## 11. Risques et réponses

| Risque | Niveau | Atténuation |
|---|---|---|
| Peu de garages acceptent de payer | Élevé | Offre de lancement, valeur démontrée par les demandes reçues, prix configurables, recharge Mobile Money de petits montants. |
| Pas assez d'utilisateurs clients | Élevé | Concentration géographique, signal de détresse comme argument, réseaux sociaux, partenariats, affichage. |
| Signal de détresse sans réponse (garages hors zone, solde insuffisant) | Élevé | Rappel de recharge à l'alerte, zones d'intervention renseignées dès l'inscription, suivi du taux d'acceptation, délai réglable. |
| Faux signaux, faux avis, faux profils | Moyen | Compte vérifié, un signal en cours par client, limites journalières, badge, modération, signalement. |
| Faible connectivité | Moyen | App légère, cache, contact par appel/WhatsApp. |
| Intégration du paiement | Moyen | FedaPay, crédit après confirmation serveur, ajustement manuel en dernier recours. |
| Statut réglementaire du solde prépayé | Moyen | Avis juridique avant le pilote ; solde non retirable et réservé aux services de la plateforme. |
| Dépassement budget/délai | Moyen | MVP resserré, livraison par étapes. |
| Contournement de la plateforme | Moyen | Assumé : le contact est payé une fois par couple client–garage. La valeur vient des nouveaux clients. |

## 12. Évolutions futures

Messagerie intégrée ; rendez-vous en ligne avec rappels ; autres métiers ; marketplace de pièces ; fidélité des pros ; API partenaires (assurances, dépannage, contrôle technique) ; éwé et kabiyè ; autres pays de l'UEMOA.

## 13. Livrables attendus

| N° | Livrable | Format |
|---|---|---|
| 1 | Application Android (iOS ensuite) | APK/AAB, Google Play |
| 2 | Back-office web d'administration | Application web hébergée |
| 3 | API back-end et base de données | Code source + documentation d'API |
| 4 | Portefeuille, offres et recharge Mobile Money | FedaPay |
| 5 | Notifications push | Firebase Cloud Messaging |
| 6 | Maquettes et charte graphique | Fichiers de design |
| 7 | Documentation technique et guides utilisateurs (FR) | PDF + vidéos courtes |
| 8 | Formation des premiers pros et de l'équipe admin | Sessions pratiques |
| 9 | CGU, politique de confidentialité, conditions de vente des offres | Documents juridiques validés |

### 13.1 Planning prévisionnel

| Phase | Durée |
|---|---|
| Cadrage et design | 3 à 4 semaines |
| Back-end et admin | 6 à 8 semaines |
| Application mobile | 8 à 10 semaines |
| Portefeuille, demandes et notifications | 3 à 4 semaines |
| Tests et correction | 3 à 4 semaines |
| Mise en production | 1 à 2 semaines |

Durées partiellement chevauchantes : 3 à 4 mois au total avec 2 à 3 développeurs et un designer.

## 14. Amendements v2.1

Validés le 15 octobre 2026. Statut revu en v3.0.

| Réf. | Amendement | Statut v3.0 |
|---|---|---|
| R1 | Le badge « Vérifié » est accessible à tous, indépendamment des offres. | En vigueur. |
| R2 | Tri par tranche de distance, puis par mise en avant, puis par note. | En vigueur : s'applique aux garages en vedette. |
| R3 | Compteur mensuel pour tous, détail réservé aux formules payantes. | Remplacé : tout garage visible a une adhésion ; statistiques P8 pour tous. |
| R4 | Page web publique par fiche, partageable et référencée. | Modifié : la page n'affiche plus le numéro ; elle propose l'itinéraire et invite à demander le contact dans l'application (C4). Invisible sans adhésion active. |
| R5 | Avis par QR code en atelier. | Reporté après le pilote. |
| R6 | Pas de rôle « agent terrain » ; inscription express en moins de 5 minutes. | En vigueur. |
| R7 | « Se déplace » avec rayon d'intervention, « dépannage 24 h/24 », tricycle. | En vigueur ; le rayon d'intervention détermine aussi les destinataires du signal de détresse (C5). |
| R13 | Extension aux métiers techniques ; la beauté reste hors périmètre (CHARM). | En vigueur. |
| ADR-004 | Back-office Filament. | Remplacé par ADR-013 (Blade + Tailwind). |
| ADR-008 | Paiement manuel assisté puis agrégateur. | Remplacé par ADR-014 (portefeuille FedaPay). |
| ADR-010 | Une seule application mobile pour les clients et les garagistes. | En vigueur. |
| ADR-011 | MySQL 8 au lieu de PostgreSQL + PostGIS. | En vigueur. |
| ADR-012 | Google Maps, recherche « carte d'abord ». | En vigueur. |

## 15. Amendements v3.0

Validés le 19 novembre 2026.

| Réf. | Sections | Amendement |
|---|---|---|
| A1 | §1.4, §4, §7 | Abonnements Gratuit / Standard / Premium remplacés par un portefeuille prépayé (recharge FedaPay) et un catalogue d'offres configurables débitées du portefeuille (ADR-014, ADR-015). |
| A2 | §4.2, §3.2 P1 | L'adhésion conditionne la visibilité de la fiche et la réception des demandes. Il n'y a plus de formule gratuite visible. |
| A3 | §4.2, §3.2 P3 | Aucun service publié sans pack de services ; les packs actifs se cumulent. |
| A4 | §3.1 C5, §3.2 P5 | Signal de détresse envoyé aux garages à adhésion active dont la zone d'intervention couvre le client ; le premier qui accepte est débité ; délai d'attente réglable dans le back-office. |
| A5 | §3.1 C3–C4 | Le numéro du garage est masqué ; demande de contact débitée une seule fois par couple client–garage ; contact enregistré chez les deux. |
| A6 | §4.3 | Renouvellement automatique réglable par le garagiste, offre par offre ; rappels J-7 et J-1. |
| A7 | §3.2 P7, §6 | Notifications push Firebase (ADR-016). |
| A8 | §3.3, §6 | Back-office Laravel Blade + Tailwind (ADR-013), avec paramètres de la plateforme et octroi manuel d'offres. |
