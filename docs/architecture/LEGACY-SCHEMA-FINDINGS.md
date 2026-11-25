# Legacy Schema Findings for the Rebuild

This inventory records concrete conflicts found in the current branch. It is
not an endorsement of the legacy design.

## Confirmed from migration source

| Area | Current schema | Rebuild requirement |
|---|---|---|
| Stores | `stores.team_id` is required | Keep store ownership with the merchant/team; do not attach customers to teams |
| Customers | A later migration creates `customers` and adds nullable ownership columns | Create customer identity before commerce tables in the clean schema |
| Carts | `carts.user_id` nullable plus `session_id`; later `customer_id` nullable | Authenticated cart ownership must be `(store_id, customer_id)`; guest/session carts are excluded initially |
| Orders | `orders.user_id` nullable; customer identity is duplicated in snapshot fields | New orders must reference `customer_id`; retain snapshot name/email/phone/address for historical accuracy |
| Auth | Separate customer guard/provider already exists in config | Preserve the separation, then remove any runtime legacy-user fallback |

## Specific migration conflict

The current customer migration is timestamped after both cart and order
creation migrations. It adds nullable `customer_id` columns while retaining
nullable `user_id` columns and their original foreign keys.

That is not the target schema. Merely changing `customer_id` to NOT NULL in
that late migration would fail for existing rows and would not solve the
legacy ownership ambiguity.

## Required clean-schema correction

For the new application schema, customer identity must be created before
carts and orders. The initial carts and orders migrations should reference
`customers` directly using a composite ownership foreign key:

```sql
FOREIGN KEY (store_id, customer_id)
REFERENCES customers (store_id, id)
```

The corresponding customer table must have both
`UNIQUE(store_id, email)` and `UNIQUE(store_id, id)`.

The new cart schema should not contain `user_id` or `session_id` when guest
checkout is out of scope. The new order schema should not use `user_id` as
customer ownership. Order customer details remain immutable snapshots.

## Legacy conversion is a separate concern

These clean-schema changes must not be applied as destructive alterations to
an existing production database. Existing `user_id` foreign keys and rows
must remain intact in the legacy database while a separate import process:

1. identifies eligible customer identities per store;
2. excludes platform and merchant identities;
3. writes a deterministic legacy-user/store to customer mapping;
4. backfills customer ownership in imported orders and carts;
5. reconciles record counts, amounts, and references;
6. reports unresolved cases for manual review.

Only a verified import into the new schema can be considered complete. This
document does not claim that a conversion has run.
