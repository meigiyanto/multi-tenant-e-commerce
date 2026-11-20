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

    /*
    |--------------------------------------------------------------------------
    | Custom Domain Verification
    |--------------------------------------------------------------------------
    |
    | Number of hours a DNS TXT challenge remains valid before it expires.
    |
    */
    'domain_verification_hours' => max(1, (int) env('STOREFRONT_DOMAIN_VERIFICATION_HOURS', 72)),
];
