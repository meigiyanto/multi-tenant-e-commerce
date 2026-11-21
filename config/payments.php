<?php

use App\Services\Payments\MidtransGateway;

return [
    'default' => env('PAYMENT_GATEWAY', 'midtrans'),
    'enabled' => array_filter(explode(',', (string) env('PAYMENT_GATEWAYS_ENABLED', 'midtrans'))),
    'drivers' => [
        'midtrans' => MidtransGateway::class,
    ],
    'gateways' => [
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'signature_secret_key' => env('MIDTRANS_SIGNATURE_SECRET_KEY'),
            'production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        ],
    ],
];
