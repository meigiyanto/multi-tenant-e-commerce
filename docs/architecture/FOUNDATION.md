# Rebuild Foundation — Architecture Contract

Status: accepted baseline for the clean-rebuild branch.

This document is the contract for implementation. If existing code conflicts
with it, the code must be redesigned; the contract must not be weakened merely
to preserve legacy implementation details.

## 1. Identity boundaries

### Platform and merchant identities

The `users` table and the default `web` guard are reserved for people who
operate the platform:

- platform super administrators and platform administrators;
- merchant/team owners;
- merchant team members.

A merchant operator may belong to a team. Team membership and
`current_team_id` are platform/merchant concerns only.

### Storefront customers

Storefront customers are represented only by `customers` and authenticate
through the `customer` guard/provider. Customer registration, login, logout,
email verification, password reset, profile, and session handling must never
read from or write to `users`, `team_user`, or `current_team_id`.

The customer key is store-scoped. The database must enforce
`UNIQUE(store_id, email)`, not global email uniqueness. Therefore the same
email address can identify separate customer accounts in separate stores.

A customer must not be promoted into a platform identity as a side effect of
registration, login, checkout, or order lookup.

## 2. Store and tenant ownership

A store is the commercial boundary for catalog, cart, checkout, order,
inventory, shipping configuration, and payment configuration. Every
store-owned record must carry a `store_id` and be queried through a
store-aware boundary.

The team/merchant relationship is separate from customer identity. A store is
operated by its owning merchant/team; a customer only buys from the store.
Resolving a store from a hostname establishes storefront context, not a
platform team login and not a customer identity.

Cross-store reads and writes must be rejected even if a caller knows another
record's numeric ID.

## 3. Authentication and authorization

- `web` guard: platform and merchant operators.
- `customer` guard: storefront customers.
- Separate password brokers and reset-token storage for each identity type.
- Platform/merchant authorization must not accept a customer principal.
- Storefront authorization must verify both customer identity and matching
  `store_id`.
- Customer routes must not be nested under platform team middleware.
- No legacy-user lookup, password fallback, or account auto-creation may run
  during customer login.

## 4. Core commerce ownership

For authenticated checkout, each cart and order belongs to exactly one store
and one customer. The required ownership tuple is
`(store_id, customer_id)`. A customer can only view or mutate their own carts
and orders within the active store.

Guest carts are out of scope for the initial rebuild. Checkout requires a
customer session. This keeps ownership and payment state explicit.

Catalog, variants, inventory, carts, orders, order items, payments, and
fulfilments must all be store-scoped. Child records must be validated against
the same store as their parent; a valid foreign key alone is not sufficient
when it does not enforce the composite ownership boundary.

## 5. Legacy data preservation

The old application and its database are treated as source data, not as a
schema to modify casually.

- Never delete legacy `users` to make customer authentication work.
- Never disable foreign-key checks or null historical order ownership merely
  to get a migration through.
- Keep existing order/payment totals, statuses, timestamps, and references.
- Build a separately executable migration/import process that maps each
  eligible legacy customer to a customer record per store.
- One legacy user may map to multiple customer records when that person has
  purchased from multiple stores.
- Do not convert platform administrators, merchant owners, or team members
  into customers based only on an email address.
- Produce an explicit mapping report for ambiguous, orphaned, or conflicting
  records. Ambiguous records require review; they must not be guessed.
- Retain legacy foreign keys until the backfill has been verified and a later,
  separately reviewed migration retires obsolete references.

The conversion must be repeatable or safely resumable, transactional in
bounded batches, and tested against a copy of the legacy database before any
production execution.

## 6. Implementation sequence

1. **Foundation:** clean Laravel application configuration, identity models,
   guards/providers, store/team/customer relationships, and schema constraints.
2. **Store context:** hostname resolution and store-scoped query boundaries.
3. **Platform access:** platform login, merchant onboarding, teams, roles, and
   policies without customer coupling.
4. **Storefront access:** customer registration/login/verification/reset and
   account isolation tests.
5. **Catalog and inventory:** store-owned products, variants, categories, and
   stock invariants.
6. **Cart and checkout:** customer-owned carts, address/shipping, order
   creation, and immutable order-item snapshots.
7. **Payments:** Stripe, Midtrans, and COD with idempotent callbacks and
   explicit payment state transitions.
8. **Operations:** merchant order processing, fulfilment, tracking, and
   notifications.
9. **Legacy conversion:** dry-run report, mapping, backfill, reconciliation,
   and only then retirement of obsolete schema.
10. **Hardening:** cross-store authorization tests, concurrency tests,
    integration tests, and deployment/rollback runbook.

## 7. Required acceptance tests

At minimum, automated tests must prove:

- a customer registration creates only a `customers` row;
- customer login never authenticates against `users`;
- a customer has no team membership and no `current_team_id` attribute;
- the same email can register in two different stores;
- duplicate email in the same store is rejected;
- a customer cannot access another customer's cart or order;
- a customer cannot access another store's catalog, cart, or order by changing
  a URL or numeric ID;
- merchant/platform users cannot be used as customer principals;
- customer sessions do not silently become platform sessions, and vice versa;
- legacy conversion preserves order counts, totals, payment records, and
  timestamps, and reports unresolved mappings without deleting source rows.

## 8. Definition of done for every stage

A stage is not complete because files exist or a migration command was
written. It is complete only when:

1. implementation and schema agree;
2. positive and negative tests pass;
3. formatting and static analysis pass where configured;
4. upgrade and rollback behavior is documented;
5. no existing user data is destructively changed;
6. the exact commands and environment used for verification are recorded.
