# Platform Storefront Domains

Each Store receives a platform-managed subdomain automatically when it is created.
The generated hostname uses the Store slug plus its database ID to remain globally unique,
for example `my-store-42.platform.example.com`.

## Application configuration

Set the root domain without a scheme or path:

```dotenv
STOREFRONT_ROOT_DOMAIN=platform.example.com
STOREFRONT_SCHEME=https
```

For local development, `STOREFRONT_ROOT_DOMAIN=localhost` and
`STOREFRONT_SCHEME=http` produce hosts such as `my-store-42.localhost`.

## DNS and TLS

In production, configure a wildcard DNS record for `*.platform.example.com`
to point to the same ingress/load balancer as the Laravel application. The ingress
must accept these hosts and terminate TLS with a certificate covering the wildcard
name. If the platform uses a CDN or reverse proxy, configure its host forwarding and
trusted-proxy settings accordingly.

After changing environment configuration, rebuild the Laravel config cache:

```bash
php artisan config:cache
```

Custom domains remain optional and continue to require ownership verification.
The platform subdomain is provisioned as an active, platform-owned domain and does
not require customer DNS verification.
