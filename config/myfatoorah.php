<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MyFatoorah Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for MyFatoorah payment gateway
    |
    */

    // API Configuration
'api_key' => env('MYFATOORAH_API_KEY', '***REMOVED***'),

    'test_mode' => env('MYFATOORAH_TEST_MODE', true),

    'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET', '***REMOVED***=='),

    // Default Settings
    'currency' => env('MYFATOORAH_CURRENCY', 'SAR'),
    'country_code' => env('MYFATOORAH_COUNTRY_CODE', '+966'),
    'language' => env('MYFATOORAH_LANGUAGE', 'ar'),
    'region' => env('MYFATOORAH_REGION', 'SAU'), // SAU for Saudi Arabia, KWT for Kuwait, etc.
    'notification_option' => env('MYFATOORAH_NOTIFICATION_OPTION', 'Lnk'), // Lnk, SMS, EML, or ALL

    // Callback URLs
    'success_url' => env('MYFATOORAH_SUCCESS_URL', '/payment/success'),
    'error_url' => env('MYFATOORAH_ERROR_URL', '/payment/error'),
    'webhook_url' => env('MYFATOORAH_WEBHOOK_URL', '/payment/callback'),

    // Logging
    'log_enabled' => env('MYFATOORAH_LOG_ENABLED', true),
    'log_file' => env('MYFATOORAH_LOG_FILE', storage_path('logs/myfatoorah.log')),

    // Payment Methods
    'payment_methods' => [
        'myfatoorah' => 'myfatoorah',
        'knet' => 'kn',
        'visa_master' => 'vm',
        'amex' => 'ae',
        'sadad' => 'sd',
        'apple_pay' => 'ap',
        'stc_pay' => 'stcpay',
    ],

    // Supported Gateways for Course Payments
    'course_gateways' => [
        'myfatoorah',
        'knet',
        'visa_master',
        'amex',
        'sadad',
        'apple_pay',
        'stc_pay',
    ],
];
