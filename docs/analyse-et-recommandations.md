# Analyse du cahier des charges v2.0 et recommandations

Octobre 2026. Chaque recommandation porte un identifiant (R1, R2…) et un statut.
Une recommandation validée est reportée dans le cahier des charges et, si besoin,
dans le [modèle de données](modele-donnees.md) et le [backlog](backlog-mvp.md).

## Points forts du cahier des charges

- **Périmètre resserré et assumé** : pas de chat, pas de rendez-vous, pas de marketplace. Le contact passe par l'appel et WhatsApp, ce qui est à la fois le bon usage local et la solution la moins coûteuse à construire.
- **Modèle neutre sans commission** : le contournement de la plateforme n'est pas un problème puisqu'on ne prélève rien sur la prestation.
- **Lucidité sur le risque principal** : il est commercial, pas technique. L'étape 0 de validation terrain et le paiement manuel assisté sont les bonnes réponses.
- **Contraintes locales bien identifiées** : points de repère plutôt qu'adresses, Android d'entrée de gamme, data chère, Mobile Money, FCFA.
- **Évolutivité prévue** : catégories et services configurables, ce qui conditionne l'extension à d'autres métiers et villes.

## Recommandations

| ID | Sujet | Statut |
|---|---|---|
| R1 | Découpler le badge « Vérifié » de l'abonnement | Adopté |
| R2 | Classement par tranches de distance, puis par formule | Adopté |
| R3 | Statistiques visibles aussi en formule gratuite | Adopté |
| R4 | Fiches web publiques partageables | Adopté (lot 5) |
| R5 | Avis prouvés par QR code en atelier | Reporté après le pilote |
| R6 | Rôle « agent terrain » et revendication de fiche | Abandonné (voir ci-dessous) |
| R7 | Mécaniciens mobiles et dépannage | Adopté |
| R8 | Back-office Filament, espace pro dans l'app mobile | Adopté (ADR-004) |
| R9 | Cartographie : tuiles OSM via fournisseur, liste par défaut | Remplacé par ADR-012 (Google Maps) |
| R10 | OTP par SMS d'abord, protection contre la fraude SMS | Adopté (ADR-007) |
| R11 | Pièces d'identité : stockage privé et durée de conservation | Adopté (ADR-006) |
| R12 | Réalisme économique de la phase Lomé | Pour information |
| R13 | Recoupement avec le projet CHARM | Tranché (voir ci-dessous) |

Décisions du 15 octobre 2026 : les statuts ci-dessus sont reportés dans le
[cahier des charges](cahier-des-charges.md) (§14, amendements v2.1).

### R1. Découpler le badge « Vérifié » de l'abonnement

Le cahier des charges réserve le badge à la formule Standard. Le badge signifierait alors « a payé » et non « identité et activité contrôlées » : les clients finiront par le comprendre et le badge perdra sa valeur de confiance. C'est aussi trompeur vis-à-vis des clients.

**Proposition** : la vérification est ouverte à tous les professionnels (y compris en formule gratuite). L'abonnement achète la visibilité (photos, services détaillés, position, statistiques détaillées), pas la confiance.

### R2. Classement : tranches de distance, puis formule

« Affichage en bas de liste » pour les gratuits dégrade l'expérience client : un garage gratuit à 300 m passerait derrière un garage payant à 6 km, et le client conclurait que l'application donne de mauvais résultats.

**Proposition** : trier d'abord par tranche de distance (par exemple 0–1 km, 1–3 km, 3–7 km, au-delà), puis par formule à l'intérieur de chaque tranche (Premium du quartier, Standard, Gratuit), puis par note et distance exacte. Le paiement reste un avantage visible sans casser la pertinence.

### R3. Statistiques visibles en formule gratuite

L'argument de vente n° 1 de l'abonnement, ce sont les contacts reçus. Un garage gratuit qui voit « 14 personnes ont cliqué sur votre numéro ce mois-ci » est le meilleur candidat à la conversion.

**Proposition** : compteur mensuel simple pour tous ; détail par semaine et par canal réservé aux formules payantes. Les compteurs doivent être crédibles : dédoublonnage par appareil et par jour, exclusion des consultations par le pro lui-même (voir [modèle de données](modele-donnees.md), événements).

### R4. Fiches web publiques partageables

Le besoin d'un mécanicien est ponctuel pour beaucoup d'automobilistes : installer une application pour une panne est une vraie friction, surtout avec une data chère. Par ailleurs, les fiches seront massivement partagées sur WhatsApp.

**Proposition** : chaque garage a une page web légère (rendu serveur, quelques Ko) avec les boutons Appeler, WhatsApp et Itinéraire, et une invitation à installer l'application. Bénéfices : partage WhatsApp, référencement Google (« garage Adidogomé »), et support direct du test de l'étape 0 (annuaire web de 20 garages). Coût : quelques jours avec Laravel (Blade), sans dupliquer l'application.

### R5. Avis prouvés par QR code en atelier

« Avis après un contact » est difficile à prouver : un clic sur « Appeler » ne garantit pas une prestation.

**Proposition** : le sticker du garage (déjà prévu pour l'acquisition, §9.2) porte un QR code « Laissez un avis ». Un avis issu du QR code est marqué comme « visite confirmée » et pèse davantage. L'avis après clic sur un bouton de contact reste possible.

**Décision : reporté après le pilote.** Le MVP applique la règle du cahier des charges (avis après un contact depuis l'application). Le champ `source` des avis est prévu dès le départ pour ajouter le QR code sans migration de données.

### R6. Rôle « agent terrain » et revendication de fiche

Le §9.1 prévoit une équipe terrain qui crée les profils sur place, mais aucun rôle correspondant n'existe dans les fonctionnalités.

**Proposition** :
- rôle **agent** dans l'application : création de fiches pour le compte d'un pro, photos, localisation GPS prise sur place ;
- **revendication** : le pro prend possession de sa fiche par OTP sur le numéro enregistré ;
- **suivi par agent** : nombre de fiches créées, vérifiées, converties en payant (base de la rémunération terrain).

**Décision : abandonné.** L'équipe terrain accompagne le garagiste qui s'inscrit lui-même, sur son propre téléphone. Aucun rôle ni écran dédié. Conséquence : l'inscription d'un garage doit pouvoir se faire en moins de 5 minutes sur un Android d'entrée de gamme, avec le minimum de champs obligatoires (nom, numéro WhatsApp, position prise sur place, quartier, au moins un service) ; le reste de la fiche se complète plus tard.

### R7. Mécaniciens mobiles et dépannage

Beaucoup de mécaniciens, notamment moto, se déplacent et n'ont pas d'atelier fixe. Pour un zémidjan en panne, la question est « qui peut venir ? ».

**Proposition** : attributs « se déplace » avec rayon d'intervention, et « dépannage 24 h/24 ». Filtre « dépannage sur place ». Ajouter aussi les tricycles aux types de véhicules (liste configurable).

### R8. Back-office Filament, espace pro dans l'application mobile

Une interface web pour les pros double le travail sans bénéfice en phase 1 : les mécaniciens utilisent leur téléphone, pas un ordinateur. Le back-office est construit avec Filament (Laravel), qui fournit tableaux, filtres, formulaires, exports CSV et tableaux de bord en quelques jours au lieu de plusieurs semaines. Voir ADR-004.

### R9. Cartographie

Les serveurs de tuiles publics d'OpenStreetMap (`tile.openstreetmap.org`) interdisent l'usage intensif par une application. Il faut un fournisseur de tuiles (offre gratuite ou payante) ou un auto-hébergement d'un extrait du Togo (format PMTiles, fichier statique). La vue **liste** est affichée par défaut ; la carte est chargée à la demande pour économiser la data. Voir ADR-005.

### R10. OTP : SMS d'abord, protection contre la fraude

- WhatsApp comme canal d'OTP suppose un compte WhatsApp Business API vérifié par Meta et des modèles de messages approuvés : délai et coût à prévoir. Démarrer par le SMS via un fournisseur local ou un agrégateur.
- Les points d'envoi d'OTP sont la cible d'attaques de « SMS pumping » (envois massifs vers des numéros surtaxés). Mesures : numéros +228 uniquement en phase 1, limitation par numéro, par appareil et par IP, plafond journalier global, alerte de coût.
- Budget à suivre : chaque connexion et chaque avis coûtent un SMS.

### R11. Pièces d'identité

Données sensibles au sens de la loi togolaise sur la protection des données personnelles (loi n° 2019-014 du 29 octobre 2019, à confirmer avec un juriste) :
- stockage dans un espace **privé**, jamais via une URL publique ; accès par lien signé à durée courte, depuis le back-office uniquement ;
- consultation journalisée (qui, quand) ;
- suppression des pièces après la décision de vérification, passé un délai défini avec le juriste ; conservation de la seule décision (vérifié le…, par…).

### R12. Réalisme économique de la phase Lomé

À 12 mois, avec les hypothèses du cahier des charges : 300 garages × 20 à 25 % de conversion = 60 à 75 abonnés. Avec un panier moyen d'environ 5 000 FCFA/mois (majorité Standard, quelques Premium), le revenu mensuel récurrent est de l'ordre de **300 000 à 400 000 FCFA (≈ 450 à 600 €)**.

Cela couvre l'hébergement et les SMS, pas une équipe de 2 à 3 développeurs, un designer et des agents terrain. La phase Lomé est donc une **validation** : il faut un financement ou des coûts très réduits pendant cette période, et la rentabilité dépend de l'extension (villes, métiers) et des revenus complémentaires (§4.4). À intégrer dans le plan de financement.

### R13. Recoupement avec le projet CHARM

L'extension prévue vers les coiffeurs recoupe le périmètre du projet CHARM (beauté et bien-être). Il faut décider tôt si findSolux reste centré sur les métiers techniques (mécanique, plomberie, électricité, menuiserie…) ou si les deux projets partagent un socle commun.

**Décision** : findSolux reste centré sur les **métiers techniques et le dépannage** (mécanique, puis électricité, plomberie, froid et climatisation, menuiserie…). La beauté et le bien-être restent le domaine de CHARM. Cette décision n'a aucun effet sur le code du MVP (les métiers sont configurables) et peut être revue avant l'extension.

## Points mineurs

- **« Ouvert maintenant »** : dépend d'horaires que les pros mettent rarement à jour. Prévoir un rappel périodique et un signalement « horaires incorrects ».
- **Favoris (C7)** : stockés sur le téléphone sans compte, synchronisés si l'utilisateur se connecte.
- **Étape 0** : ne pas attendre la fin de la validation terrain pour poser le socle technique, mais ne pas développer l'intégration de paiement avant d'avoir mesuré la disposition à payer (le cahier des charges le prévoit déjà avec le paiement manuel).
- **Délai** : 3 à 4 mois est réaliste avec 2 à 3 développeurs et un périmètre tenu. Un développeur seul doit compter plutôt 5 à 6 mois.
