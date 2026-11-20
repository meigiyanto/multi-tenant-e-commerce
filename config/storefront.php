<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Storefront Domain
    |--------------------------------------------------------------------------
    |
    | Every Store receives a platform-managed subdomain under this root.
    | Production must point a wildcard DNS record and TLS certificate here.
    |
    */
    'root_domain' => env('STOREFRONT_ROOT_DOMAIN', env('APP_ENV') === 'production' ? '' : 'localhost'),
    'scheme' => env('STOREFRONT_SCHEME', 'https'),
];
