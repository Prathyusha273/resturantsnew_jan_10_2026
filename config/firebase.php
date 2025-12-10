<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Admin SDK
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'jippymart-27c08'),
    'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
    'private_key' => env('FIREBASE_PRIVATE_KEY'),
    'client_email' => env('FIREBASE_CLIENT_EMAIL'),
    'client_id' => env('FIREBASE_CLIENT_ID'),
    'client_x509_cert_url' => env('FIREBASE_CLIENT_X509_CERT_URL'),
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),

    /*
    |--------------------------------------------------------------------------
    | Restaurant Panel URL
    |--------------------------------------------------------------------------
    |
    | The URL of the restaurant panel for redirects
    |
    */

    'restaurant_panel_url' => env('RESTAURANT_PANEL_URL', 'https://restaurant.restaurant-system.com'),
];
