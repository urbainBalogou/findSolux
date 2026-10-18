<?php

/*
 * Connexion par code à usage unique (ADR-007).
 */
return [

    // Pilote d'envoi : « log » (développement, interdit en production),
    // « array » (tests). Le pilote SMS sera ajouté avec le choix du fournisseur.
    'driver' => env('OTP_DRIVER', 'log'),

    // Seuls les numéros mobiles de ce pays sont acceptés en phase 1.
    'region' => env('OTP_REGION', 'TG'),

    'length' => 6,

    // Durée de validité d'un code, en secondes.
    'ttl' => (int) env('OTP_TTL', 300),

    // Essais autorisés par code avant qu'il soit invalidé.
    'max_attempts' => 5,

    // Délai minimal entre deux envois au même numéro, en secondes.
    'resend_cooldown' => 60,

    // Envois maximum par numéro et par jour.
    'max_per_phone_per_day' => 5,

    // Plafond global d'envois par jour : coupe-circuit contre le « SMS pumping ».
    'max_per_day' => (int) env('OTP_MAX_PER_DAY', 2000),

];
