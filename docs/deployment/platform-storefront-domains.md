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

## Custom domain verification expiration

The current test configuration gives DNS TXT challenges a one-minute validity period.
Set the duration in minutes with:

```dotenv
STOREFRONT_DOMAIN_VERIFICATION_MINUTES=1
```

This one-minute value is temporary for testing. Before production, restore the recommended
72-hour validity period by setting `STOREFRONT_DOMAIN_VERIFICATION_MINUTES=4320`.

The Store detail page displays a live client-side countdown. When it reaches zero, the
page reloads and the server marks the challenge as `expired`; its token is invalidated.
Users can then generate a fresh challenge. The verification endpoint also rejects
expired challenges directly, even if the scheduler has not run yet.

The Laravel scheduler expires overdue challenges every minute. Ensure the server runs
`php artisan schedule:run` every minute, for example with this cron entry:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

The platform subdomain is provisioned as an active, platform-owned domain when a Store is created. Existing draft Stores receive the same platform subdomain when activated. It does not require customer DNS verification.
