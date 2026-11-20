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
    | Number of minutes a DNS TXT challenge remains valid before it expires (temporary test default: 1).
    |
    */
    'domain_verification_minutes' => max(1, (int) env('STOREFRONT_DOMAIN_VERIFICATION_MINUTES', 1)),
];
