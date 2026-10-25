<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

     'myfatoorah' => [
        'token' => env('MYFATOORAH_API_KEY', env('MYFATOORAH_TOKEN')),
        'test_mode' => env('MYFATOORAH_TEST_MODE', true),
        'country_iso' => 'SAU',
        'currency_iso' => 'SAR',
        'language' => 'ar',
        'mobile_country_code' => '+966',
        'notification_option' => 'Lnk', // Lnk for payment link, SMS for SMS, EML for email
    ],

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'sender_id' => env('FIREBASE_SENDER_ID'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'api_key' => env('FIREBASE_API_KEY'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', storage_path('app/firebase-service-account.json')),
    ],

    'yandex' => [
        'translate_key' => env('YANDEX_TRANSLATE_API_KEY'),
    ],
];
