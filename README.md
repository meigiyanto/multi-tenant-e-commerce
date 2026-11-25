# Multi-Tenant E-Commerce

This branch is the clean-rebuild track for the application. It is intentionally
separate from the existing development line.

## Rebuild status

The branch was created from the existing application as a safe starting point.
It is **not yet a clean implementation**: legacy routes, models, and migrations
still exist and must be replaced or reconciled in deliberate stages.

The rebuild follows the architecture contract in
[docs/architecture/FOUNDATION.md](docs/architecture/FOUNDATION.md).

## Non-negotiable boundaries

- Platform and merchant identities live in `users`.
- Storefront customers live in `customers` and authenticate through a separate
  customer guard.
- A customer is never a platform user or a team member and never receives
  `current_team_id`.
- Customer identity is store-scoped: the same email may exist in multiple
  stores.
- Orders and carts belong to a store and, for authenticated checkout, to that
  store's customer.
- Existing production data must be preserved. Legacy data conversion is an
  explicit, auditable migration process, never a login-time fallback.

## Delivery rule

Each stage must include its schema, implementation, automated tests, and a
clear migration/rollback story before the next stage begins. No claim of
runtime verification is made until the application has actually been run
against a disposable test database.
