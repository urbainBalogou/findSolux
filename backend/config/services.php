<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Clé serveur (API Places) : distincte de la clé de l'application mobile,
    // jamais envoyée au téléphone.
    'google_maps' => [
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
    ],

    // Connexion par compte Google (ADR-017). Identifiants client OAuth acceptés
    // comme destinataire du jeton, séparés par des virgules : en pratique celui
    // de type « Application Web », transmis à l'application (serverClientId).
    'google_sign_in' => [
        'client_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_SIGN_IN_CLIENT_IDS', '')),
        ))),
        'timeout' => (int) env('GOOGLE_SIGN_IN_TIMEOUT', 10),
    ],

    // Notifications push (ADR-016). Pilotes : log (développement), array (tests), fcm.
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
    ],

    'firebase' => [
        // Fichier JSON du compte de service Firebase, hors Git.
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'timeout' => (int) env('FIREBASE_TIMEOUT', 10),
    ],

    'fedapay' => [
        'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
        'secret_key' => env('FEDAPAY_SECRET_KEY'),
        'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
        'customer_email_domain' => env('FEDAPAY_CUSTOMER_EMAIL_DOMAIN', 'mecadispo.com'),
        'timeout' => (int) env('FEDAPAY_TIMEOUT', 20),
        'minimum_top_up_xof' => (int) env('FEDAPAY_MINIMUM_TOP_UP_XOF', 100),
        'maximum_top_up_xof' => (int) env('FEDAPAY_MAXIMUM_TOP_UP_XOF', 1_000_000),
    ],

];
