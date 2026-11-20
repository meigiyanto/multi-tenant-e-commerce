# Repository Gap Analysis — Multi-Tenant E-Commerce

Audit baseline: branch dev  
Audit date: 2026-11-20  
Scope: targeted source-level review. This is not a runtime test report.

## 1. Executive assessment

The repository currently contains a Laravel 13 / React / Inertia starter application with a Team management foundation and an initial Store + StoreDomain foundation. It is not yet a complete multi-tenant e-commerce application.

The highest-priority architectural gap is not missing commerce CRUD. Store domain resolution exists as middleware but is not attached to a dedicated Storefront route group. Tenant/Store context and authorization contracts therefore need to be completed and tested before commerce tables and features are introduced.

## 2. Confirmed existing implementation

| Area | Evidence inspected | Assessment |
|---|---|---|
| Authentication | User model, Fortify dependencies, web middleware | Foundation present |
| Team tenant root | Team model and teams migration | Present |
| Membership | Membership pivot, HasTeams, TeamRole | Present |
| Invitations | TeamInvitation model and routes | Present |
| Team authorization | TeamPolicy, EnsureTeamMembership | Present |
| Current Team navigation | users.current_team_id, SetTeamUrlDefaults | Present; navigation state is not authorization |
| Store ownership | stores.team_id, Store model | Present |
| Store domains | store_domains table, StoreDomain model | Present |
| Domain lookup | StoreDomainService | Present; requires active + verified domain and active Store |
| Store request context | StoreContext, ResolveStoreFromDomain | Present |
| Middleware alias | bootstrap/app.php alias store.domain | Registered, not attached to Storefront routes |
| Team/Store UI | TeamController and StoreController | Basic management UI present |
| Frontend/build | React 19, Inertia 3, Vite 7, Tailwind 3 | Starter stack present |

## 3. Confirmed gaps and risks

### P0 — Storefront route boundary is missing
Evidence: routes/web.php defines the welcome route, Team dashboard route and invitation actions, then loads settings routes. No public Storefront route group was found in the inspected route files. store.domain is only registered as an alias in bootstrap/app.php.

Impact: domain resolution code is not yet part of a real Storefront request flow.

Required outcome:
- dedicated Storefront route group applies store.domain;
- middleware establishes StoreContext before any Store-owned query;
- unknown/unverified/inactive domains fail closed;
- no fallback to current Team or client-supplied Store ID.

### P0 — Context contract is incomplete
Evidence: StoreContext stores Store and StoreDomain and exposes Team through Store. There is no separate TenantContext in the inspected source.

Impact: this is not necessarily a defect—Team can be derived from Store—but the application must define one consistent context contract for dashboard and Storefront. Avoid two mutable sources of truth.

Required outcome:
- explicitly define Team context for authenticated dashboard requests;
- define Store context for Storefront requests;
- derive Team from Store in Storefront requests;
- ensure scoped context lifecycle is reset between requests/jobs.

### P0 — Commerce domain is not present in inspected application modules
No Catalog, Inventory, Customers, Cart, Orders, Payments, Shipping or Promotions modules were found in the inspected application paths.

Impact: there is no commerce workflow to secure yet. Building CRUD before ownership and context rules are finalized risks schema rework.

Required outcome: create modules in dependency order only after tenancy and Store ownership rules are approved.

### P1 — Store lifecycle and domain verification are incomplete
Evidence: Store has a string status defaulting to draft. StoreDomain has status and verified_at. Domain resolver checks active status and verified_at, but the inspected source does not show a domain verification workflow or lifecycle service.

Impact: status values and transitions may become inconsistent if controllers mutate them directly.

Required outcome:
- define allowed Store and domain states;
- define transition authority;
- implement DNS/HTTP verification and re-verification;
- enforce unique active primary domain per Store with suitable DB guarantees.

### P1 — Team permission model is not yet a complete Store permission model
Evidence: TeamRole has Owner / Admin / Member; TeamPolicy covers Team operations. StoreController authorizes Team view/update before listing/creating Stores.

Impact: reasonable starting point, but catalog/order/inventory actions need explicit policies and must validate Store ownership against the authorized Team.

Required outcome:
- retain Team roles initially;
- define capabilities for catalog, inventory, orders and settings;
- avoid Store-specific roles until requirements justify them;
- keep Platform Admin authorization separate.

### P1 — Platform administration and SaaS subscription are absent
Evidence: no platform admin, plan, subscription, billing or entitlement module was found in inspected application paths.

Impact: tenant provisioning and paid-plan enforcement do not yet exist.

Required outcome: defer until Store and transaction lifecycle are stable; then introduce Team-owned subscriptions and explicit entitlements.

### P1 — Data ownership convention is not finalized in schema
Evidence: current schema has Team ownership for Store and Store ownership for domains. Commerce tables are not present in the inspected source.

Impact: adding both team_id and store_id indiscriminately would duplicate ownership and create consistency risk.

Required outcome: v1 recommendation is Store-owned commerce records with store_id, deriving Team through Store. Add redundant team_id only with a documented integrity/query reason.

### P2 — Existing Team URL defaults must remain separate from Store resolution
Evidence: SetTeamUrlDefaults is appended to the global web middleware stack; HasTeams.switchTeam updates current_team_id and URL defaults.

Impact: dashboard navigation convenience can be confused with tenant isolation if reused by Storefront logic.

Required outcome: current_team_id selects dashboard context only. Public Storefront must resolve solely from verified hostname.

## 4. Source-level observations requiring follow-up

- EnsureTeamMembership resolves route parameters current_team or team, checks membership and optionally minimum role. Useful dashboard guard, but not a substitute for policy checks on each resource.
- StoreDomainService normalizes lowercase and trailing dots and checks active/verified domain plus active Store. Follow-up should consider IDN/punycode, ports, trusted proxy/host handling and domain claim collision behavior.
- StoreDomain enforces global domain uniqueness in the database. A partial unique constraint for one primary domain per Store may be needed depending on DB support and desired semantics.
- StoreController creates draft Stores under a Team and scopes slug uniqueness to that Team. Store update, activation and domain management are not present in the inspected controller.
- AppServiceProvider registers StoreContext as scoped, appropriate for request/job scope. Verify behavior under Octane/long-lived workers before production.
- Composer scripts define lint, static analysis and tests, but this audit did not execute them.

## 5. Recommended implementation order

| Order | Work package | Why now | Exit criteria |
|---:|---|---|---|
| 1 | Tenant/Store architecture contract | Prevents ownership ambiguity | ADRs and authorization/context tests accepted |
| 2 | Context + middleware hardening | Prerequisite for every Store request | Team dashboard and Storefront contexts isolated |
| 3 | Store lifecycle + domain verification | Needed before public Storefront | Only active verified Store domains resolve |
| 4 | Catalog | First commerce dependency | Cross-Store catalog isolation tests pass |
| 5 | Inventory | Required for reliable purchase | Atomic stock updates/reservations tested |
| 6 | Customers + cart | Checkout prerequisites | Store-scoped customer/cart isolation passes |
| 7 | Orders + checkout | Core commerce workflow | Snapshot and order lifecycle tests pass |
| 8 | Payments + shipping | External transaction integrations | Webhook idempotency and fulfillment tests pass |
| 9 | Subscription + entitlements | SaaS monetization | Plan limits and suspension behavior tested |
| 10 | Production readiness | Release gate | Security, observability, backup and load checks pass |

## 6. Explicitly out of scope for the next implementation task

Do not:
- create every commerce migration at once;
- add tenant_id and store_id to every table without a data ownership decision;
- add Store-specific roles prematurely;
- implement subscription billing before the core Store workflow;
- split the application into microservices;
- reorganize all existing folders solely for aesthetic consistency;
- change authentication/Team management without a failing requirement or test.

## 7. Audit limitations

This review fetched and inspected: bootstrap/app.php, routes/web.php, routes/settings.php, composer.json, package.json, Team/Store/Membership/User models, Team role and concern, TeamPolicy, EnsureTeamMembership, StoreContext, ResolveStoreFromDomain, StoreDomainService, StoreController, TeamController, the Team and Store/StoreDomain migrations, and StoreDomainResolutionTest.

This was a targeted source audit, not an exhaustive scan of every file, live database inspection, or executed test/build run. Findings marked "not found" mean not found in inspected paths and route definitions, not proof that no related code exists anywhere in the repository.
