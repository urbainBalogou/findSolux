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
businesses ─< subscriptions >─ plans
businesses ─< payments
businesses ─< business_events  →  business_daily_stats (agrégats)
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
| `users` | phone (unique, E.164), phone_verified_at, name, last_seen_at | Pas de mot de passe : connexion par OTP. Pas de rôle stocké (ADR-010). |
| `otp_codes` | phone, code_hash, channel (`sms`, `whatsapp`), attempts, expires_at, consumed_at, ip, device_id | Ou en cache ; voir ADR-007 pour les limites. |
| `admins` | name, email, password (haché), role (`admin`, `moderateur`) | Garde Filament séparée (ADR-004). |

## Professionnels

| Table | Champs principaux | Notes |
|---|---|---|
| `businesses` | owner_user_id, trade_id, name, slug, phone, whatsapp_phone, description, city_id, district_id, latitude, longitude (`DECIMAL(10,7)`, index composite), landmark (« en face de… »), status (`draft`, `published`, `suspended`), verification_status (`none`, `pending`, `verified`, `rejected`), verified_at, is_mobile + service_radius_km (R7), is_24_7 (R7), current_plan, plan_expires_at, rating_avg, rating_count | `current_plan`, `plan_expires_at`, `rating_avg` et `rating_count` sont dénormalisés pour un tri rapide des résultats. La vérification est indépendante de la formule (R1). |
| `business_services` | business_id, service_id, vehicle_type_id (nullable), description, price_min, price_max | Fourchettes indicatives en XOF. |
| `opening_hours` | business_id, day_of_week (0–6), opens_at, closes_at | Plusieurs plages par jour possibles (pause de midi). |
| `photos` | business_id, path, position, width, height, size_bytes | Espace public, versions compressées. |
| `verification_requests` | business_id, submitted_by_user_id, id_document_path, activity_proof_path, status, reviewed_by_admin_id, reviewed_at, rejection_reason, documents_purged_at | Espace privé, purge après décision (R11). |

## Avis et modération

| Table | Champs principaux | Notes |
|---|---|---|
| `reviews` | business_id, user_id, rating (1–5), comment, status (`published`, `hidden`), source (`contact` ; `qr` après le pilote, R5), device_id, pro_reply, pro_replied_at | Unicité (business_id, user_id). `device_id` sert à détecter les avis multiples depuis un même appareil. |
| `reports` | reporter_user_id (nullable), reportable_type, reportable_id, reason, comment, status (`open`, `resolved`, `rejected`), handled_by_admin_id, handled_at | Fiche ou avis. |
| `favorites` | user_id, business_id | Côté application, favoris aussi stockés localement sans compte. |

## Abonnements et paiements

| Table | Champs principaux | Notes |
|---|---|---|
| `plans` | code (`free`, `standard`, `premium`), name, price_monthly, price_yearly, features (json), is_active | Prix modifiables sans déploiement. |
| `subscriptions` | business_id, plan_id, status (`active`, `expired`, `cancelled`), starts_at, ends_at, source (`payment`, `manual`, `launch_offer`, `referral`), granted_by_admin_id | Une tâche planifiée quotidienne fait expirer les abonnements et repasse la fiche en gratuit (§7). |
| `payments` | business_id, subscription_id, amount, currency (`XOF`), provider (`manual`, agrégateur), provider_reference, payer_phone, status (`pending`, `succeeded`, `failed`, `refunded`), paid_at, raw_payload (json), recorded_by_admin_id | Historique complet, export CSV. |
| `referrals` | referrer_business_id, referred_business_id, rewarded_at | Parrainage (1 mois offert). |

## Statistiques

| Table | Champs principaux | Notes |
|---|---|---|
| `business_events` | business_id, type (`view`, `call_click`, `whatsapp_click`, `directions_click`, `share`, `qr_scan`), user_id (nullable), device_id, created_at | Journal brut, purgé après agrégation (ex. 90 jours). |
| `business_daily_stats` | business_id, date, views, calls, whatsapp, directions, shares | Agrégation quotidienne, dédoublonnée par appareil et par jour ; consultations du propriétaire exclues. Source des statistiques pro (P6) et du tableau de bord admin. |
| `notification_logs` | channel, recipient, template, status, cost, sent_at | Suivi du coût des SMS. |

## Classement des résultats de recherche (R2)

1. Filtrer : rectangle autour du point (index latitude/longitude), puis `ST_Distance_Sphere` ≤ rayon, statut `published`, filtres (service, véhicule, ouvert maintenant, note).
2. Trier par tranche de distance (0–1 km, 1–3 km, 3–7 km, au-delà).
3. Dans chaque tranche : Premium du quartier, puis Standard, puis Gratuit.
4. Puis note moyenne pondérée par le nombre d'avis, puis distance exacte.
