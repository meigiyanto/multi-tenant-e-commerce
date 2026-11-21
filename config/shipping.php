<?php

return [
    'default' => env('SHIPPING_PROVIDER', 'rajaongkir'),
    'providers' => [
        'rajaongkir' => [
            'class' => App\Services\Shipping\RajaOngkirShippingProvider::class,
            'api_key' => env('RAJAONGKIR_API_KEY'),
            'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
            'couriers' => array_filter(explode(',', env('RAJAONGKIR_COURIERS', 'jne,sicepat,jnt,pos,tiki'))),
        ],
    ],
];
