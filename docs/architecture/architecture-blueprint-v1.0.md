# Architecture Blueprint v1.0

Status: Proposed baseline for review  
Baseline: dev  
Style: Laravel Modular Monolith  
Tenancy: Shared Database / Shared Schema, Team-based tenant, multi-store  
Scope: Architecture definition only; not feature implementation authorization.

## 1. Product vision

Build one SaaS application where multiple businesses manage one or more independent online stores. A Team is the tenant/business account. A Store is an operational storefront owned by exactly one Team. Customers visit a Store through its verified domain and transact within that Store context.

Two product surfaces:
- **SaaS Platform:** tenant onboarding, Team membership, Store provisioning, domain management, subscriptions and platform operations.
- **E-commerce Platform:** catalog, inventory, customer accounts, cart, checkout, orders, payments, shipping and promotions.

## 2. Goals and non-goals

### Goals
- Strong tenant and Store data isolation.
- One Team can own multiple Stores.
- Domain-based storefront resolution; never infer storefront from current_team_id.
- Team dashboard and public Storefront use separate request contexts.
- Modular boundaries without premature microservices.
- Every feature has explicit ownership, authorization and acceptance criteria.

### Non-goals for v1
- Microservices or database-per-tenant.
- Cross-Store carts or checkout spanning multiple Stores.
- Shared catalog across Stores.
- Store-specific staff roles before a concrete requirement exists.
- Subscription billing before the core Store lifecycle is stable.

## 3. Domain glossary

| Term | Meaning |
|---|---|
| User | Human identity authenticated by the application. |
| Team | Tenant/business account; owns Stores and team-level resources. |
| Membership | User's relationship and role within a Team. |
| TeamInvitation | Invitation to join a Team. |
| Store | Independent commerce operation owned by one Team. |
| StoreDomain | Hostname mapped to a Store; must be verified and active to resolve. |
| Tenant context | Team boundary for tenant-owned administration and data access. |
| Store context | Specific Store boundary for storefront and commerce operations. |
| Platform admin | SaaS operator, not a Team member by default. |

## 4. Target domain boundaries

1. **Identity & Access** — users, authentication, verification, passkeys and sessions.
2. **Platform Administration** — platform operators, tenant oversight, platform configuration and audit.
3. **Teams / Tenancy** — Team lifecycle, memberships, roles, invitations and tenant context.
4. **Stores** — Store lifecycle, settings, domains, domain verification and Store context.
5. **Catalog** — products, categories, variants, attributes and media.
6. **Inventory** — stock balances, adjustments and reservations; stock locations only if required.
7. **Customers** — customer identity scoped to a Store for v1.
8. **Cart & Checkout** — Store-scoped cart, pricing validation and checkout orchestration.
9. **Orders** — order lifecycle, line-item snapshots, cancellations and refund coordination.
10. **Payments** — payment attempts, provider integrations, webhook verification and reconciliation.
11. **Shipping** — shipping options, rates, fulfillment and tracking.
12. **Promotions** — coupons and discounts evaluated against one Store.
13. **Notifications & Audit** — delivery and traceable business/security events.

These are logical modules, not a mandate to create a directory, service or class for every noun. Introduce code boundaries when a module has behavior and independent invariants.

## 5. Tenancy and data ownership

### Core ownership
- teams: tenant root.
- team_members and team_invitations: Team-owned access relationships.
- stores: belongs to exactly one Team through team_id.
- store_domains: belongs to exactly one Store through store_id.

### v1 commerce ownership recommendation
Commerce data is Store-owned. Use store_id as the direct ownership key on products, categories, variants, inventory records, customers, carts, orders, promotions and fulfillment records. Resolve the owning Team through the Store relationship.

Do not add both team_id and store_id to every commerce table by default. Redundant ownership columns create consistency obligations. Add both only when a documented query/performance or database-integrity requirement justifies it, and enforce consistency (ideally with composite constraints).

| Data | Owner | v1 scope |
|---|---|---|
| Team/member/invitation | Team | Team |
| Store/domain | Team / Store | Store |
| Product/category/variant | Store | Store |
| Inventory | Store | Store |
| Customer | Store | Store |
| Cart | Store + customer/session | Store |
| Order/payment/shipping | Store | Store |
| Subscription/plan | Team | Team |
| Platform audit | Platform | Platform |

A product, customer or cart must never become visible to another Store merely because both Stores share the same Team.

## 6. Context resolution

### Team dashboard
Authenticated request -> route identifies Team -> authenticate -> verify Team membership -> authorize action -> query constrained to that Team or its Stores.

current_team_id is a dashboard navigation preference only. It is not proof of authorization and is not a storefront resolver.

### Storefront
Incoming hostname -> normalize hostname -> find exact StoreDomain -> require verified + active domain -> require active Store -> load owning Team -> establish StoreContext (and derive Tenant context) -> continue to Storefront routes.

Unknown, pending, unverified, suspended or deleted Store/domain must not resolve. Never fall back to current_team_id, a visitor-supplied slug or a default Store.

The existing ResolveStoreFromDomain and StoreDomainService are the intended foundation. Their presence alone does not activate Storefront routes: middleware must be attached to a dedicated Storefront route group.

## 7. Authorization model

- **Platform admin:** platform-level capability, separate from Team membership. Must not be granted by a Team role.
- **Team roles:** current Owner / Admin / Member hierarchy remains the initial Team access model.
- **Store permissions:** initially inherited from Team permissions, with explicit policies checking that Store belongs to the authorized Team.
- **Customer:** can access only their own customer profile, cart and orders within the resolved Store.
- **Public visitor:** can read only published/active Store catalog and public Store data.

Every action must authorize the resource, not merely trust a route parameter or current_team_id. Tenant and Store scoping must be enforced in query construction as well as policies. Platform support access should be audited and purpose-limited.

## 8. Request and transaction invariants

1. Every tenant-owned record has an unambiguous owner.
2. Every Store-owned commerce record references one Store.
3. A Store's Team cannot be changed through ordinary Store update operations.
4. A request cannot switch Store context using client-supplied IDs after domain resolution.
5. Cart and checkout revalidate product availability, price, discounts and shipping server-side.
6. Order lines preserve product name, SKU, unit price, tax/discount and quantity snapshots at purchase time.
7. Payment state changes only through trusted provider responses/webhooks or authorized manual operations.
8. Webhooks are signature-verified, idempotent and safe to retry.
9. Inventory mutation is transactional and protected against overselling.
10. Suspension/deletion must not silently erase financial or audit history.

## 9. Store lifecycle (proposed)

draft -> active -> suspended -> archived

- Draft: owner configures Store; it is not publicly resolvable.
- Active: Store may serve verified active domains.
- Suspended: storefront resolution and new checkout are blocked; owner access remains for remediation.
- Archived: terminal operational state; retention and deletion rules apply.

Domain lifecycle is separate: pending -> active after verification, with explicit disabled/failed states as needed. A domain must not be active until ownership verification succeeds.

## 10. Recommended Laravel structure

Keep the current Laravel application as a Modular Monolith. Do not reorganize the entire repository merely to imitate a diagram. Introduce module boundaries incrementally around real domain behavior.

Suggested logical organization:
- app/Domain/Teams
- app/Domain/Stores
- app/Domain/Catalog
- app/Domain/Inventory
- app/Domain/Customers
- app/Domain/Cart
- app/Domain/Orders
- app/Domain/Payments
- app/Domain/Shipping
- app/Domain/Promotions
- app/Domain/Platform
- app/Support/Tenancy and app/Support/Stores

Within a module, use Models, Policies, Actions/Services, Events and DTOs only where needed. Controllers remain thin. Avoid generic repositories, base services and abstractions without a concrete requirement.

Frontend areas should be separated by product surface: Team dashboard, Storefront and Platform Admin. They may remain in the same React/Inertia application.

## 11. Delivery sequence and dependencies

0. Architecture + repository audit.
1. Tenant/Store foundation hardening: contexts, middleware registration, ownership invariants, authorization and tests.
2. Store provisioning: lifecycle, domain management and verification.
3. Catalog: Store-scoped categories/products/variants/media.
4. Inventory: Store-scoped stock and atomic adjustments/reservations.
5. Storefront: domain-resolved public pages and customer authentication.
6. Transaction engine: cart, checkout, order snapshots, payment and shipping.
7. SaaS monetization: plans, subscriptions, entitlements, limits and suspension policy.
8. Production readiness: security review, observability, backups, load and failure testing.

Do not start a later phase while prerequisite invariants are not tested.

## 12. Minimum testing strategy

- Unit: role/permission rules, domain normalization, pricing and state transitions.
- Feature: cross-Team and cross-Store isolation for every tenant-owned resource.
- Middleware: active verified domain resolves exactly one Store; invalid states fail closed.
- Integration: Store provisioning -> domain verification -> catalog -> cart -> checkout -> order -> payment webhook.
- Concurrency: stock reservation and duplicate webhook delivery.
- Security: IDOR, mass assignment, route model binding and unauthorized Team/Store access.
- Regression: existing authentication, Team management, invitations and dashboard remain functional.

## 13. Decisions to confirm before commerce implementation

| Decision | v1 recommendation | Status |
|---|---|---|
| Shared or per-Store catalog | Per-Store | Proposed |
| Customer identity | Per-Store account; same email may exist in multiple Stores | Proposed |
| Inventory ownership | Per-Store | Proposed |
| Team vs Store roles | Team roles first; Store-specific roles deferred | Proposed |
| Platform admin access | Separate platform permission boundary | Required design rule |
| Cross-Store cart/order | Not supported; one cart/order per Store | Proposed |
| Suspension behavior | Block public storefront and new checkout; preserve owner access/history | Proposed |
| Custom domain | DNS/HTTP ownership verification before activation | Required design rule |

These recommendations are explicit so they can be accepted or changed before schema design. No migration should be written until relevant decisions are confirmed.

## 14. Architecture change policy

For every implementation task:
1. State business requirement and domain owner.
2. Identify current implementation and exact gap.
3. Describe affected modules, routes, schema and compatibility risks.
4. Define acceptance criteria and tests.
5. Implement the smallest complete change on a new branch from dev.
6. Run targeted tests, then relevant regression checks.
7. Review diff and report what was and was not executed.

Never work directly on main; never merge to main without explicit approval.
