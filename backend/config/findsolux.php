<?php

return [

    // Rectangle englobant le Togo : rejette les positions GPS aberrantes
    // (0,0 quand le GPS n'a pas encore de signal, position d'un autre pays…).
    'country_bounds' => [
        'latitude' => [5.9, 11.2],
        'longitude' => [-0.3, 1.9],
    ],

    // Pays des numéros de téléphone acceptés en phase 1 (garages et clients).
    'phone_region' => env('PHONE_REGION', 'TG'),

    // Lien d'installation de l'application, affiché sur les pages web des garages.
    'app_download_url' => env('APP_DOWNLOAD_URL'),

    // Fuseau des horaires d'ouverture (« ouvert maintenant »).
    'timezone' => 'Africa/Lome',

    'search' => [
        'default_radius_km' => 10,
        'max_radius_km' => 30,
        'max_results' => 30,
        // Plus grand rayon d'intervention d'un garagiste qui se déplace.
        'max_service_radius_km' => 50,
        // Tranches de distance du classement (R2), en mètres : un garage en
        // vedette ne passe devant que les garages de la même tranche.
        'distance_bands_m' => [1000, 3000, 7000],
    ],

    'photos' => [
        'disk' => env('PHOTOS_DISK', 'public'),
        'max_per_business' => 8,
        // Taille d'envoi maximale acceptée, en kilo-octets.
        'max_upload_kb' => 8192,
        // Plus grand côté, en pixels, après redimensionnement.
        'max_dimension' => 1280,
        'thumbnail_dimension' => 400,
        'webp_quality' => 75,
    ],

    // Demandes des clients (cahier des charges v3.0, §3.1 C4–C5). Les délais
    // sont des valeurs par défaut, modifiables dans le back-office.
    'requests' => [
        'distress_signal_wait_minutes' => 10,
        'contact_request_wait_hours' => 24,
        // Signaux de détresse par client et par jour (abus, faux signaux).
        'max_distress_signals_per_day' => 5,
        // Prix débité au garage pour chaque demande acceptée, en FCFA : un seul
        // tarif, quelle que soit son offre. Gratuit tant qu'il n'est pas fixé.
        'request_acceptance_price_xof' => 0,
    ],

    // Offres achetées (§4.3).
    'subscriptions' => [
        // Le renouvellement automatique est tenté un peu avant l'échéance, pour
        // que la fiche ne disparaisse pas entre deux passages de la tâche planifiée.
        'renewal_lead_minutes' => 15,
    ],

];
