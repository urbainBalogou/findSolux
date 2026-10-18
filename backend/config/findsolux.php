<?php

return [

    // Rectangle englobant le Togo : rejette les positions GPS aberrantes
    // (0,0 quand le GPS n'a pas encore de signal, position d'un autre pays…).
    'country_bounds' => [
        'latitude' => [5.9, 11.2],
        'longitude' => [-0.3, 1.9],
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

];
