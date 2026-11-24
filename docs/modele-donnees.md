# Modèle de données (proposition initiale)

MySQL 8 (ADR-011). Montants en entiers XOF, téléphones en E.164, dates en UTC.
Les mentions (Rx) renvoient aux recommandations de
[analyse-et-recommandations.md](analyse-et-recommandations.md).

## Vue d'ensemble

```
cities ─< districts
trades ─< services
vehicle_types

users ─< businesses (propriétaire ; un utilisateur est garagiste dès qu'il a une fiche)
businesses ─< business_services >─ services / vehicle_types
businesses ─< opening_hours
businesses ─< photos
businesses ─< verification_requests
businesses ─< reviews >─ users
businesses ─< business_subscriptions >─ commercial_offers
businesses ─< business_promotions (garages en vedette)
businesses ─< business_events  →  business_daily_stats (agrégats)
users ─ wallets ─< wallet_entries, wallet_top_ups (portefeuille, ADR-014)
users ─< distress_signals ─< distress_signal_recipients >─ businesses
users ─< contact_requests >─ businesses
users ─< business_contacts >─ businesses (contacts échangés)
users ─< device_tokens (notifications push)
platform_settings (paramètres du back-office)
reports (signalements polymorphes : fiche ou avis)
favorites (users × businesses)
admins (back-office, séparé de users)
```

## Référentiels (configurables, base de l'extension villes/métiers)

| Table | Champs principaux | Notes |
|---|---|---|
| `cities` | name, slug, country_code, latitude, longitude, is_active | Lomé seule active au lancement. |
| `districts` | city_id, name, slug, latitude, longitude (centre du quartier) | Quartiers de Lomé : recherche par quartier, mise en avant Premium par quartier. |
| `trades` | slug, name, is_active | `mecanique` au lancement. |
| `services` | trade_id, slug, name, icon, position | Vidange, freins, électricité, pneus/vulcanisation, dépannage, diagnostic, carrosserie, climatisation… |
| `vehicle_types` | slug, name, icon | Voiture, moto, poids lourd, tricycle (R7). |

## Utilisateurs

| Table | Champs principaux | Notes |
|---|---|---|
| `users` | google_id (unique), email, phone (E.164, nullable), name, last_seen_at, team_business_id, username (unique), password | Compte personnel : connexion par compte Google, sans mot de passe (ADR-017). `phone` : numéro où joindre l'utilisateur, saisi par lui, non vérifié, obligatoire pour envoyer une demande. Compte d'équipe (ADR-018) : team_business_id, identifiant et mot de passe créés par le garagiste ; pas de compte Google. Pas de rôle stocké (ADR-010). |
| `admins` | name, email, password (haché), role (`admin`, `moderateur`) | Garde Filament séparée (ADR-004). |

## Professionnels

| Table | Champs principaux | Notes |
|---|---|---|
| `businesses` | owner_user_id, trade_id, name, slug, phone, whatsapp_phone, description, city_id, district_id, latitude, longitude (`DECIMAL(10,7)`, index composite), landmark (« en face de… »), status (`draft`, `published`, `suspended`), membership_ends_at, has_unlimited_membership, services_quota, team_quota, verification_status (`none`, `pending`, `verified`, `rejected`), verified_at, is_mobile + service_radius_km (R7), is_24_7 (R7), rating_avg, rating_count | `membership_ends_at`, `has_unlimited_membership` et `services_quota` sont recopiés des offres actives (ADR-015) : visible = publiée et adhésion active. `phone` et `whatsapp_phone` ne sont donnés qu'aux clients en contact. `verification_status`, `rating_*` : à venir (lots 6 et 8). |
| `business_services` | business_id, service_id, vehicle_type_id (nullable), description, price_min, price_max, is_published | Fourchettes indicatives en XOF. `is_published` : couvert par les packs de services actifs (premiers services ajoutés). |
| `opening_hours` | business_id, day_of_week (0–6), opens_at, closes_at | Plusieurs plages par jour possibles (pause de midi). |
| `photos` | business_id, path, position, width, height, size_bytes | Espace public, versions compressées. |
| `verification_requests` | business_id, submitted_by_user_id, id_document_path, activity_proof_path, status, reviewed_by_admin_id, reviewed_at, rejection_reason, documents_purged_at | Espace privé, purge après décision (R11). |

## Avis et modération

| Table | Champs principaux | Notes |
|---|---|---|
| `reviews` | business_id, user_id, rating (1–5), comment, status (`published`, `hidden`), source (`contact` ; `qr` après le pilote, R5), device_id, pro_reply, pro_replied_at | Unicité (business_id, user_id). `device_id` sert à détecter les avis multiples depuis un même appareil. |
| `reports` | reporter_user_id (nullable), reportable_type, reportable_id, reason, comment, status (`open`, `resolved`, `rejected`), handled_by_admin_id, handled_at | Fiche ou avis. |
| `favorites` | user_id, business_id | Côté application, favoris aussi stockés localement sans compte. |

## Portefeuille et offres (cahier des charges v3.0, ADR-014, ADR-015)

| Table | Champs principaux | Notes |
|---|---|---|
| `wallets` | user_id (unique), balance_xof | Solde jamais négatif. |
| `wallet_entries` | wallet_id, type (`top_up`, `top_up_refund`, `purchase`, `request_acceptance`, `refund`, `adjustment`), direction (`credit`, `debit`), amount_xof, balance_after_xof, reference (unique), description | Chaque mouvement, avec le solde après opération ; la référence unique empêche un double enregistrement. |
| `wallet_top_ups` | public_id, wallet_id, amount_xof, status, payment_method (`moov_tg`, `togocel`), provider (`fedapay`), provider_transaction_id, provider_status, failure_reason, completed_at, expires_at | Crédit uniquement après confirmation par FedaPay. |
| `commercial_offers` | type (`membership`, `service_bundle`, `staff_bundle`), name, quantity, price_xof, duration_value, duration_unit, is_unlimited, is_active, position | Catalogue modifiable depuis le back-office. |
| `business_subscriptions` | business_id, commercial_offer_id, offer_type, name, quantity, price_xof, duration_value, duration_unit, is_unlimited, starts_at, ends_at, status (`active`, `expired`, `renewed`), source (`wallet`, `renewal`, `admin`), auto_renew, wallet_entry_id, granted_by_admin_id, renewed_from_id, reminded_7_days_at, reminded_1_day_at | Copie de l'offre au moment de l'achat. Une adhésion achetée pendant une autre commence à sa fin. |
| `business_promotions` | business_id (unique), position, starts_at, ends_at, is_unlimited | Garages en vedette, en tête de leur tranche de distance (R2). |
| `platform_settings` | key, value | `distress_signal_wait_minutes`, `contact_request_wait_hours`, `request_acceptance_price_xof` (prix unique d'une demande acceptée, qui remplace les offres « request_acceptance »). |
| `referrals` | referrer_business_id, referred_business_id, rewarded_at | Parrainage (plus tard). |

## Demandes des clients (C4, C5)

| Table | Champs principaux | Notes |
|---|---|---|
| `distress_signals` | public_id, client_user_id, message, service_id, vehicle_type_id, latitude, longitude, status (`open`, `accepted`, `expired`, `cancelled`), expires_at, accepted_business_id, accepted_by_user_id, accepted_at, price_xof, wallet_entry_id, closed_at | Un seul signal ouvert par client ; 5 par jour au plus. |
| `distress_signal_recipients` | distress_signal_id, business_id, distance_m, declined_at | Garages alertés, figés à l'envoi : adhésion active, se déplacent, client dans leur rayon. |
| `contact_requests` | public_id, client_user_id, business_id, message, status (`pending`, `accepted`, `declined`, `expired`, `cancelled`), expires_at, responded_at, accepted_by_user_id, price_xof, wallet_entry_id | Une demande en attente par couple client–garage. |
| `business_contacts` | business_id, client_user_id (unique ensemble), source (`distress_signal`, `contact_request`) | Contacts échangés : le garage n'est plus débité pour une demande de contact de ce client. |
| `device_tokens` | user_id, token (unique), platform | Jetons Firebase des appareils (ADR-016). |

## Statistiques

| Table | Champs principaux | Notes |
|---|---|---|
| `business_events` | business_id, type (`view`, `call_click`, `whatsapp_click`, `directions_click`, `share`, `qr_scan`), user_id (nullable), device_id, created_at | Journal brut, purgé après agrégation (ex. 90 jours). |
| `business_daily_stats` | business_id, date, views, calls, whatsapp, directions, shares | Agrégation quotidienne, dédoublonnée par appareil et par jour ; consultations du propriétaire exclues. Source des statistiques pro (P6) et du tableau de bord admin. |
| `notification_logs` | channel, recipient, template, status, cost, sent_at | Suivi du coût des SMS. |

## Classement des résultats de recherche (R2)

1. Filtrer : rectangle autour du point (index latitude/longitude), puis `ST_Distance_Sphere` ≤ rayon, statut `published` et adhésion active, filtres (service publié, véhicule, ouvert maintenant, note).
2. Trier par tranche de distance (0–1 km, 1–3 km, 3–7 km, au-delà).
3. Dans chaque tranche : garages en vedette (par rang), puis les autres.
4. Puis note moyenne pondérée par le nombre d'avis (avec les avis, lot 6), puis distance exacte.
