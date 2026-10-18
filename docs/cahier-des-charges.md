# Cahier des charges

**Application de mise en relation avec les professionnels de proximité**
Phase 1 : mécaniciens et garages – Lomé, Togo
Version 2.1 – MVP resserré · Nom du projet : à définir (nom de code : `findsolux`) · Octobre 2026

> Document de référence. Le texte des sections 1 à 13 est celui de la version 2.0 ;
> les amendements validés de la version 2.1 sont listés au [§14](#14-amendements-validés-v21)
> et priment sur le texte d'origine. Justifications dans
> [analyse-et-recommandations.md](analyse-et-recommandations.md).

---

## 1. Présentation du projet

### 1.1 Contexte

Au Togo, trouver un mécanicien fiable repose presque entièrement sur le bouche-à-oreille. Les automobilistes et conducteurs de moto-taxi (zémidjans) ne disposent d'aucun moyen simple de comparer les garages, de connaître leurs services, leurs horaires ou la qualité de leur travail. De leur côté, de nombreux garages et mécaniciens indépendants n'ont aucune visibilité en ligne et dépendent de leur emplacement physique.

Le téléphone mobile est omniprésent, WhatsApp est l'outil de communication dominant et le Mobile Money (TMoney, Flooz) est largement utilisé pour les paiements du quotidien. Ces conditions rendent possible une plateforme de mise en relation simple, légère et adaptée aux usages locaux.

### 1.2 Objectifs

- Mettre en relation gratuitement les particuliers avec des mécaniciens et garages de proximité.
- Offrir aux professionnels une visibilité qualifiée et des outils simples pour recevoir des clients.
- Générer des revenus récurrents grâce à un abonnement payé par les professionnels.
- Valider le modèle à Lomé avant de l'étendre à d'autres villes puis à d'autres métiers (coiffeurs, tailleurs, plombiers, électriciens, etc.).

### 1.3 Périmètre

- **Inclus (phase 1)** : annuaire géolocalisé de mécaniciens et garages à Lomé et environs, fiches professionnelles, avis, contact direct, abonnement des professionnels.
- **Exclus (phase 1)** : chat intégré, planning de rendez-vous, marketplace de pièces, publicité, autres métiers et autres villes.

### 1.4 Principe directeur

L'application est neutre : elle ne prend aucune commission sur les prestations. Les clients contactent directement les professionnels et règlent les travaux avec eux. La plateforme se rémunère uniquement par l'abonnement des professionnels.

## 2. Marché cible et utilisateurs

### 2.1 Zone de lancement

Lomé et sa périphérie (Agoè, Adidogomé, Baguida, Aflao-Gakli, etc.), avec une concentration initiale sur quelques quartiers à forte densité de garages afin d'éviter une plateforme « vide ». Extension prévue ensuite vers Kara, Sokodé, Atakpamé et Kpalimé.

### 2.2 Profils d'utilisateurs

| Profil | Besoins | Contraintes |
|---|---|---|
| Automobilistes | Trouver un garage proche, connaître les services et les avis, contacter rapidement. | Peu de temps, méfiance envers les prix et la qualité. |
| Conducteurs de moto-taxi | Dépannage rapide, réparation à bas coût, mécanicien proche. | Smartphone d'entrée de gamme, forfait data limité. |
| Mécaniciens indépendants et garages | Gagner de nouveaux clients, être visibles, se différencier par la réputation. | Peu d'habitude des outils numériques, paiement en Mobile Money. |
| Administrateur (équipe du projet) | Valider les profils, modérer les avis, suivre les abonnements. | Volume croissant de profils à vérifier. |

### 2.3 Contraintes du contexte togolais

- **Langues** : interface en français, vocabulaire simple. Éwé et kabiyè en évolution ultérieure. Usage fort des icônes.
- **Connexion** : réseau parfois instable et data coûteuse. Application légère (< 30 Mo), chargement rapide, utilisable avec une connexion faible.
- **Appareils** : majorité d'Android d'entrée de gamme. iOS secondaire en phase 1.
- **Paiement** : FCFA (XOF). Mobile Money en priorité, carte bancaire en option.
- **Adresses** : peu d'adresses formelles. Localisation GPS et points de repère (« en face de… », « carrefour… ») essentiels.

## 3. Fonctionnalités du MVP (Phase 1)

### 3.1 Côté client (gratuit, sans obligation de compte)

| N° | Fonctionnalité | Description | Priorité |
|---|---|---|---|
| C1 | Recherche géolocalisée | Garages proches sur une liste et sur une carte, avec distance estimée. Recherche aussi par quartier. | Haute |
| C2 | Filtres | Type de véhicule (voiture, moto, poids lourd), service (vidange, freins, électricité, pneus, dépannage), note, « ouvert maintenant ». | Haute |
| C3 | Fiche du professionnel | Nom, photos, services, fourchettes de prix indicatives, horaires, adresse avec point de repère, badge « Vérifié ». | Haute |
| C4 | Contact direct | Boutons « Appeler » et « WhatsApp » (message pré-rempli). Itinéraire vers le garage. | Haute |
| C5 | Avis et notes | Note de 1 à 5 et commentaire, après un contact avec le professionnel. Compte (numéro vérifié par SMS) requis pour publier. | Haute |
| C6 | Signalement | Signaler un profil ou un avis abusif. | Moyenne |
| C7 | Favoris | Enregistrer des garages pour les retrouver facilement. | Basse |

### 3.2 Côté professionnel

| N° | Fonctionnalité | Description | Priorité |
|---|---|---|---|
| P1 | Inscription et profil | Création par numéro de téléphone (code SMS/WhatsApp). Nom, localisation sur carte, services, horaires, photos. | Haute |
| P2 | Vérification | Pièce d'identité et, si disponible, preuve d'activité (registre, photo de l'atelier). Validation manuelle par l'administrateur, puis badge « Vérifié ». | Haute |
| P3 | Gestion des services | Ajout/modification de services par type de véhicule, avec description et prix indicatif. | Haute |
| P4 | Abonnement | Choix d'une formule, paiement Mobile Money ou carte, suivi de l'expiration, rappels avant échéance. | Haute |
| P5 | Notifications | Alerte SMS ou WhatsApp lors d'un nouvel avis ou à l'approche de l'expiration. | Moyenne |
| P6 | Statistiques simples | Vues du profil, clics « Appeler » et « WhatsApp », par semaine. | Moyenne |
| P7 | Réponse aux avis | Réponse publique à un avis. | Basse |

### 3.3 Côté administrateur (interface web)

- Tableau de bord : professionnels inscrits, actifs, abonnés, vues et contacts générés.
- Validation ou refus des demandes de vérification.
- Modération des avis et traitement des signalements.
- Gestion des abonnements : activation manuelle, prolongation, offre gratuite de lancement, historique des paiements.
- Export des données (CSV) pour le suivi commercial.

### 3.4 Reporté aux phases suivantes

Chat intégré, planning et prise de rendez-vous, suivi en temps réel des demandes, mise en avant payante, marketplace de pièces, fidélité, API ouverte, publicité ciblée.

## 4. Modèle économique

### 4.1 Principe

Clients gratuits. Les professionnels paient un abonnement mensuel ou annuel pour un profil complet et visible. Aucune commission sur les prestations.

### 4.2 Formules (hypothèses à valider par test terrain auprès d'une vingtaine de garages)

| Formule | Prix indicatif | Contenu | Cible |
|---|---|---|---|
| Gratuite (Découverte) | 0 FCFA | Fiche minimale (nom, téléphone, quartier). Pas de photos, pas de badge, affichage en bas de liste. | Remplir l'annuaire. |
| Standard | 3 000 à 5 000 FCFA / mois | Fiche complète, photos, services détaillés, toutes catégories de véhicules, badge « Vérifié », statistiques simples. | Petits garages, indépendants. |
| Premium | 10 000 à 15 000 FCFA / mois | Standard + mise en avant dans son quartier, position prioritaire, bannière sur sa fiche. | Grands garages, concessionnaires, poids lourds. |

Réduction annuelle (ex. 2 mois offerts). Offre de lancement : 3 mois de Standard gratuits pour les 50 premiers garages.

### 4.3 Ce qui justifie l'abonnement

- Visibilité auprès de clients qui cherchent activement un mécanicien dans leur quartier.
- Badge « Vérifié » et avis clients.
- Statistiques montrant le nombre de contacts reçus grâce à l'application.
- Plus tard : planning, rendez-vous, relation client.

### 4.4 Autres revenus (plus tard)

Mise en avant ponctuelle ; partenariats (assurances, pièces, dépanneuses, stations-service) ; publicité locale ciblée.

## 5. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| Performance | Liste de résultats en < 3 s en 3G. APK Android < 30 Mo. |
| Faible connexion | Cache des fiches récemment consultées et des favoris. Images compressées. Messages d'erreur clairs hors réseau. |
| Compatibilité | Android 8+ en priorité. iOS 14+ ensuite. Admin web sur navigateurs récents. |
| Simplicité | Français simple, icônes parlantes, contact en deux touches maximum. Tests d'utilisabilité avec de vrais mécaniciens. |
| Sécurité | Auth par OTP téléphone, HTTPS, mots de passe hachés pour l'administration, sauvegardes quotidiennes. |
| Disponibilité | 99 % mensuel. |
| Évolutivité | Autres villes et métiers sans refonte (catégories et services configurables). |

## 6. Architecture technique recommandée

| Composant | Choix recommandé | Justification |
|---|---|---|
| Application mobile | Flutter | Une base de code, bonnes performances sur appareils modestes. |
| Interface web (admin et pros) | Application web légère | Gestion des profils et abonnements depuis un ordinateur. |
| Back-end | API REST Laravel ou Django | Écosystèmes matures, hébergement économique. |
| Base de données | PostgreSQL + PostGIS | Requêtes de proximité. |
| Stockage | Compatible S3 | Photos et pièces justificatives. |
| Cartographie | OpenStreetMap (ou Google Maps) | Coûts maîtrisés. |
| Authentification | OTP SMS / WhatsApp | Adapté au contexte. |
| Paiement | Agrégateur Mobile Money + carte | Un seul prestataire d'intégration. |
| Notifications | SMS, WhatsApp Business, push | SMS/WhatsApp touchent plus sûrement les pros. |
| Hébergement | VPS + sauvegardes automatiques | Coût maîtrisé. |

## 7. Paiement des abonnements

- TMoney (Togocom) et Flooz (Moov Africa) en priorité, carte en complément.
- Agrégateur présent au Togo (FedaPay, CinetPay, PayDunya…) : frais, ouverture de compte et couverture à vérifier.
- Paiement manuel assisté en début de pilote : paiement sur numéro marchand, activation par l'administrateur.
- Reçu par SMS ou WhatsApp, rappel automatique 7 jours avant l'échéance.
- Non-paiement : retour automatique à la formule gratuite, sans suppression des données.

## 8. Confiance, modération et aspects légaux

### 8.1 Confiance et qualité

- Badge « Vérifié » après contrôle d'identité et d'activité.
- Avis limités aux numéros vérifiés ; un seul avis par client et par professionnel.
- Détection de comportements suspects (nombreux avis depuis un même appareil, avis répétitifs) et modération manuelle.
- Charte d'usage pour les professionnels.

### 8.2 Aspects légaux

- Conformité à la loi togolaise sur la protection des données personnelles, déclaration auprès de l'IPDCP (à confirmer avec un juriste).
- CGU et politique de confidentialité en français, accessibles dans l'application.
- Structure juridique enregistrée (nécessaire pour un compte marchand).
- Conditions d'abonnement claires (durée, renouvellement, résiliation, remboursement).
- La plateforme n'est pas partie à la relation client–professionnel ni responsable de la qualité des travaux.

## 9. Plan de lancement commercial

| Étape | Durée | Actions |
|---|---|---|
| 0. Validation | 4 à 6 semaines | Rencontrer 30 à 50 garages. Mesurer l'intérêt et la disposition à payer. Tester une version simple (page web ou groupe WhatsApp de 20 garages). |
| 1. Développement MVP | 3 à 4 mois | App + admin. Constitution de la base de garages par démarchage terrain. |
| 2. Pilote | 3 mois | 2 à 3 quartiers, 50 garages inscrits gratuitement. Retours, ajustements. |
| 3. Monétisation | Dès le mois 4 du pilote | Passage progressif à l'abonnement. Suivi de la conversion. |
| 4. Extension | Après validation | Autres quartiers, villes de l'intérieur, nouveaux métiers. |

- **Acquisition pros** : équipe terrain qui inscrit les garages sur place ; associations de mécaniciens et transporteurs ; parrainage (1 mois offert).
- **Acquisition clients** : Facebook, WhatsApp, TikTok ; stickers dans les garages ; syndicats de zémidjans, auto-écoles, radios, influenceurs ; conseils d'entretien en contenu court.

## 10. Indicateurs de succès

| Indicateur | 6 mois (pilote) | 12 mois |
|---|---|---|
| Garages inscrits à Lomé | 50 à 100 | 300 |
| Garages vérifiés | 70 % | 80 % |
| Conversion gratuit → payant | 10 à 15 % | 20 à 25 % |
| Utilisateurs actifs / mois | 2 000 | 10 000 |
| Contacts générés / mois | 1 500 | 10 000 |
| Satisfaction des pros | > 4/5 | > 4/5 |
| Revenu mensuel récurrent | Premiers abonnements | Selon seuil de rentabilité |

## 11. Risques et réponses

| Risque | Niveau | Atténuation |
|---|---|---|
| Peu de garages acceptent de payer | Élevé | Offre de lancement, valeur démontrée par les statistiques, paliers accessibles, Mobile Money mensuel. |
| Pas assez d'utilisateurs clients | Élevé | Concentration géographique, réseaux sociaux, partenariats, affichage. |
| Faux avis et faux profils | Moyen | Vérification des numéros, badge, modération, signalement. |
| Faible connectivité | Moyen | App légère, cache, contact par appel/WhatsApp. |
| Intégration du paiement | Moyen | Paiement manuel assisté puis agrégateur. |
| Dépassement budget/délai | Moyen | MVP resserré, livraison par étapes. |
| Contournement de la plateforme | Faible | Valeur du badge, de la réputation et de la visibilité. |

## 12. Évolutions futures

Rendez-vous en ligne avec rappels ; chat ; autres métiers ; marketplace de pièces ; fidélité des pros ; API partenaires (assurances, dépannage, contrôle technique) ; éwé et kabiyè ; autres pays de l'UEMOA.

## 13. Livrables attendus

| N° | Livrable | Format |
|---|---|---|
| 1 | Application Android (iOS ensuite) | APK/AAB, Google Play |
| 2 | Interface web d'administration et de gestion des profils | Application web hébergée |
| 3 | API back-end et base de données | Code source + documentation d'API |
| 4 | Module de paiement des abonnements | Agrégateur Mobile Money + carte |
| 5 | Maquettes et charte graphique | Fichiers de design |
| 6 | Documentation technique et guides utilisateurs (FR) | PDF + vidéos courtes |
| 7 | Formation des premiers pros et de l'équipe admin | Sessions pratiques |
| 8 | CGU et politique de confidentialité | Documents juridiques validés |

### 13.1 Planning prévisionnel

| Phase | Durée |
|---|---|
| Cadrage et design | 3 à 4 semaines |
| Back-end et admin | 6 à 8 semaines |
| Application mobile | 8 à 10 semaines |
| Intégration du paiement | 2 à 3 semaines |
| Tests et correction | 3 à 4 semaines |
| Mise en production | 1 à 2 semaines |

Durées partiellement chevauchantes : 3 à 4 mois au total avec 2 à 3 développeurs et un designer.

## 14. Amendements validés (v2.1)

Validés le 15 octobre 2026.

| Réf. | Section modifiée | Amendement |
|---|---|---|
| R1 | §3.2 P2, §4.2 | Le badge « Vérifié » est accessible à toutes les formules, y compris gratuite. L'abonnement achète la visibilité (photos, services détaillés, position, statistiques détaillées), pas la confiance. |
| R2 | §4.2 | « Affichage en bas de liste » est remplacé par : tri par tranche de distance, puis par formule à l'intérieur de chaque tranche, puis par note. |
| R3 | §3.2 P6, §4.2 | Toutes les formules voient un compteur mensuel de vues et de contacts ; le détail par semaine et par canal est réservé aux formules payantes. |
| R4 | §3.1 | Chaque fiche publiée dispose d'une page web publique légère (Appeler, WhatsApp, Itinéraire), partageable et référencée. |
| R5 | §3.1 C5 | Reporté après le pilote : avis par QR code en atelier. |
| R6 | §9.1 | Pas de rôle « agent terrain » : l'équipe terrain accompagne le garagiste qui s'inscrit lui-même sur son téléphone. L'inscription express doit tenir en moins de 5 minutes. |
| R7 | §3.1 C2, §3.2 P1 | Attributs « se déplace » (avec rayon d'intervention) et « dépannage 24 h/24 », filtre « dépannage sur place », type de véhicule « tricycle ». |
| ADR-004 | §3.3, §6 | Back-office Filament ; pas d'interface web pour les garagistes en phase 1. |
| ADR-011 | §6 | Base de données MySQL 8 au lieu de PostgreSQL + PostGIS ; géolocalisation par latitude/longitude et `ST_Distance_Sphere`. |
| ADR-012 | §3.1 C1, §6 | Cartographie Google Maps ; écran de recherche « carte d'abord » avec recherche de lieu et cartes des garages triées par distance. |
| ADR-010 | §6, §13 | Une seule application mobile pour les clients et les garagistes (deux espaces). |
| R13 | §1.2, §12 | Extension vers les métiers techniques et le dépannage (électricité, plomberie, froid et climatisation, menuiserie…). La beauté reste hors périmètre (projet CHARM). |
