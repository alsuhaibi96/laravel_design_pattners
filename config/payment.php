<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your payment gateways here. Each gateway can be enabled/disabled
    | and has its own API credentials.
    |
    */

    'gateways' => [
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'api_key' => env('STRIPE_API_KEY', 'sk_test_mock'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', 'pk_test_mock'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', 'whsec_mock'),
        ],

        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', true),
            'client_id' => env('PAYPAL_CLIENT_ID', 'mock_client_id'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET', 'mock_client_secret'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID', 'mock_webhook_id'),
            'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
        ],

        'bank_transfer' => [
            'enabled' => env('BANK_TRANSFER_ENABLED', true),
            'api_key' => env('BANK_TRANSFER_API_KEY', 'mock_bank_key'),
            'account_id' => env('BANK_TRANSFER_ACCOUNT_ID', 'mock_account'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */

    'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'USD'),
    'max_retries' => env('PAYMENT_MAX_RETRIES', 2),
    'idempotency_ttl_hours' => env('PAYMENT_IDEMPOTENCY_TTL', 24),

    /*
    |--------------------------------------------------------------------------
    | Gateway Priority
    |--------------------------------------------------------------------------
    |
    | Order of gateways when no preference specified.
    | Gateways are tried in this order for failover.
    |
    */

    'gateway_priority' => [
        'stripe',
        'paypal',
        'bank_transfer',
    ],

    /*
    |--------------------------------------------------------------------------
    | High Value Transaction Threshold
    |--------------------------------------------------------------------------
    |
    | Transactions above this amount will be routed to optimize fees.
    |
    */

    'high_value_threshold' => env('PAYMENT_HIGH_VALUE_THRESHOLD', 1000),
];

