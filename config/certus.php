<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email administrateur Certus
    |--------------------------------------------------------------------------
    | Destinataire des alertes de piratage (signaux 1 à 4).
    | Si non défini, utilise l'adresse d'expédition par défaut du mailer.
    */
    'admin_email' => env('CERTUS_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS', 'admin@expertosoft.com')),

    /*
    |--------------------------------------------------------------------------
    | URL du portail administrateur
    |--------------------------------------------------------------------------
    | Lien inclus dans tous les emails d'alerte pour accès rapide au portail.
    */
    'portal_url' => env('CERTUS_PORTAL_URL', env('APP_URL', 'https://certus.expertosoft.com') . '/admin'),

];
