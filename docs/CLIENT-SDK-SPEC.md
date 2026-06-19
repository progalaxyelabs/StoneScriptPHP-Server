# StoneScriptPHP — Generated Client SDK Specification

**Status:** APPROVED (review sign-off 2026-06-14)
**Author:** ProGalaxy eLabs dev team  
**Date:** 2026-06-13 · revised 2026-06-14  
**Scope:** `php stone generate client` command, URL route conventions, relationship with `ngx-stonescriptphp-client`

---

## 0. Resolved Decisions (review sign-off — 2026-06-14)

Outcome of the orchestrator review of the DRAFT. These OVERRIDE any conflicting text in
the sections below; app-dev revises §§1–14 to match. Authority for tenancy + auth is
**AUTH-SPEC** — this client spec references it, never re-defines it.

### URL & grouping convention (agreed)

Path shape: **`/{service}/tenant/{tenantId}/{group}/{action}[/{id}]`**

- **scope** = tenancy scope (`/tenant/{tenantId}`). Resolved + authorized by framework middleware.
  It is NOT a client namespace — in the generated client it's the silent `setTenant()` context.
- **group** = one per **domain resource/intent** → `api.{group}.*` (kebab→camel). Per-table is the
  common case, but the rule is by *concept*, not schema.
- **action** = explicit verb in the URL (kebab→camel method): `items/update-name` → `items.updateName`.
- **HTTP method rule (naming convention):** reads → `GET`, writes → `POST` by default. The action
  word carries the verb; prefer NOT to map update→PUT / delete→DELETE (collapses synonym drift
  across 11 platforms). **Transport capability (since v4.2.0):** the generated client DOES support
  `PUT`/`DELETE`/`PATCH` for routes that declare them — see §5. The GET/POST preference is a naming
  guideline, not a transport limitation; PUT/DELETE/PATCH routes are first-class and emit real typed
  methods (no reject-stub).
- **Naming authority (ends the dev-team `wou` debates):** *the group/action name is the contract
  concept the endpoint serves — declared explicitly on the route. Physical schema (table, column,
  or SQL join/FROM order) is NEVER a naming input.*
  - single resource (even via junction-table-driven joins) → that resource's group
  - composed / cross-entity view → a purpose group (`reports`, `dashboard`, `analytics`)
  - column-as-resource (e.g. `item_prices.batch_number`) → name by concept: standalone → own group;
    child-of-parent → sub-resource action on parent (`items.batches(itemId)`); else → a param
- **Search:** entity-scoped search = an action in its group (`items.search`); genuine cross-entity
  search = its own `search` group (`search.global`). Not "search as the broadest module."
- **Grouping is DECLARED on the route, never inferred** from path or query.

### B1 — De-medstoreapp the scoper
`/tenant/{tenantId}/` is the fixed, framework-level tenant segment (not store/warehouse/workspace).
`setStore()` → `setTenant()`. Vocabulary matches token/gateway/AUTH-SPEC (`tenant_id`).

### B2 — Tenant authz is per AUTH-SPEC §T (mode-aware) — NOT a new rule here
- Target platforms (medstoreapp, logisticsapp, restrantapp, instituteapp) are **T3 (Multi-Tenant/URL)**:
  tenant-less identity JWT; tenant from URL; validated by the **store-access middleware against the
  identity's memberships (§5c)** — a membership check, NOT a `token.tenant_id === path` compare.
- **webmeteor = T2** (tenant in platform JWT via `/api/auth/exchange`, no URL tenant). **T1** = none.
- The generated client is **tenancy-mode-aware**: T3 → `setTenant()`/URL param; T2 → tenant via
  exchanged JWT, no URL tenant; T1 → none.
- Cross-tenant/impersonation bypass: **deferred** (default-deny).

### B3 — Declared grouping + no stale-dist
Group/action declared explicitly on each route (satisfied structurally by the rigid
`{group}/{action}[/{id}]` shape). Generated `dist/` is **un-gitignored + a CI build-gate** so a
broken regen fails red instead of shipping.

### B4 — Auth: no `auth` group in the generated business client
Split by mode: **builtin** auth → routes are in the platform's `routes.php`, so the framework
generates them (conformant by construction); **external** auth (private `progalaxyelabs-auth`) → NOT
generated. Three pieces:
- **`stonescriptphp-auth-client`** = the **contract/interface** only (interface + DTOs + error codes +
  token convention + envelope-normalization). Opensource. No concrete network class.
- **builtin impl** → emitted by `php stone generate client`.
- **external impl** → a separate **private/Verdaccio** concrete adapter (e.g. `progalaxyelabs-auth-client`)
  implementing the contract against the private daemon. Written once, reused across all platforms.
Both impls satisfy the one AUTH-SPEC-defined interface → builtin↔external swappable, zero app change.
This **splits** today's concrete `stonescriptphp-auth-client` (deliberate refactor, not a rename).
The generated business client stays zero-dep; it shares only the **token convention** (keys + refresh).

### B5 — Single envelope in the business client
`MinimalHttp` handles the **builtin** envelope ONLY (`{status:"ok"|"error", message, data}`), per
AUTH-SPEC §Response Envelopes:
- success ⇔ `status === "ok"` → return `body.data` **verbatim, including `null`** (kill the `?? data` leak)
- missing or `!== "ok"` ⇒ error → throw `ApiError(message, httpStatus, body)`, exposing machine code
  `data.error` as `ApiError.code`
- transport failures (5xx / network / non-JSON) handled distinctly → `ApiError(httpStatus)`; guard `res.json()`
- the bare/external envelope (`{success,...}`) is the **auth-client's** job (§P5), not the business client's
- **refresh:** endpoint + response shape pinned in the AUTH-SPEC **token contract**; read the pinned
  field (no `data.access_token` vs `data.data.access_token` guessing). Keep 401→refresh→retry-once.

### B6 — TokenStore: frozen contract, localStorage, no escape hatch (adapter deferred)
- **Fixed by contract:** key names (`ssp_access_token` / `ssp_refresh_token`) + refresh route, owned by
  AUTH-SPEC — this is what keeps auth-client ↔ business-client in sync.
- **Hardcoded localStorage**; **delete the "fork to customize" line** (regen would clobber it); keep the
  `typeof localStorage !== 'undefined'` guard consistently on ALL methods (defensive only).
- **Injectable storage adapter is DEFERRED** — adding it later is backward-compatible (optional ctor arg
  defaulting to localStorage). Revisit on the first real non-localStorage consumer.

### B7 — Delete the medstoreapp-revert band-aid
Remove §13 "Short-term (unblock medstoreapp QC)" entirely. No replacement — finalize the contract,
regenerate once, fix forward.

### Open-question dispositions
1. **SSR** → out of scope. 2. **Multi-tenant switching** → T3 supports simultaneous tabs via tab-local
URL (§T); `setTenant` re-callable; document the single-active-context constraint. 3. **Admin** →
**CLOSED (see A6 below):** separate generated client, identity-scoped, no `/tenant/{id}` segment,
partitioned by `service` marker on the route. 4. **Offline/PWA** → localStorage for tokens; offline data
is a PWA-layer concern. 5. **Phasing** → **big-bang (decided 2026-06-14)** — no customers to protect, so generator
self-containment + URL restructure + all 11 platforms migrate in **one coordinated sweep**, no
dual-convention period. Guardrail: every platform still passes **factory QC + the un-gitignored-dist
build-gate before any deploy** — the contract changes all at once, but no platform half-migrates and
none deploys un-revalidated. 6. **ngx-pay** → depends on `ApiClient`; align with the new
`stonescriptphp-pay` design.

---

### Amendments (2026-06-14) — contract validation against logisticsapp + webmeteor

Six decisions surfaced during the ≥2-platform process gate. All are **resolved and binding**;
§§1–14 are to be updated to match. Amendments are numbered A1–A6.

#### A1 — Streaming / SSE endpoints are EXCLUDED from the generated business client

**Trigger:** webmeteor `GET /api/workspaces/:id/events` — Server-Sent Events
(`text/event-stream`); the build-event stream cannot be modelled as a Promise-returning
method.

**Decision:** Routes annotated `streaming: true` (see A2 for the declaration mechanism)
are **excluded from `php stone generate client` entirely**. They do not appear in the
generated `ApiClient`. The generator logs a notice when it skips a streaming route so
the omission is visible.

**How the caller consumes streaming endpoints:** via a hand-written `EventSource` wrapper
(or `fetch` with `ReadableStream`) authored once per platform and placed **outside** the
generated client package (e.g. `docker/api/client/{service}/streaming/events.ts`). The
generated package contains a comment block naming every excluded streaming route and
pointing to this convention so developers know where to look.

**What is NOT streaming:** a route that returns a large JSON payload, a long-poll
endpoint, or a paginated list is **not** streaming — it is a normal GET route. The
`streaming: true` annotation is reserved for `text/event-stream` / `Transfer-Encoding:
chunked` / WebSocket upgrade routes only.

**Auth on streaming routes:** the caller is responsible for forwarding the Authorization
header (or query param token where EventSource does not support custom headers). The
generated client's `TokenStore` is exported so streaming helpers can read the token via
`api.tokens.get()`.

**Cross-reference:** §5 (MinimalHttp implements GET/POST/PUT/DELETE/PATCH, no streaming), §7 (ApiClient
generated methods).

---

#### A2 — Group-declaration syntax in routes.php (EXACT mechanism)

**Trigger:** §0 says "group is declared on the route, never inferred" but gives no PHP
syntax. Both validated platforms currently declare `scope` (portal / admin / shared infra
bucket), not `group` (domain concept). These are different dimensions and must not be
conflated.

**Terms clarified:**
- **`service`** — the first URL segment and the PHP router's top-level handler split
  (`portal`, `admin`, `public`, `api`). Unchanged from existing framework usage; this is
  what platforms currently call `scope`. The existing `scope` parameter on `$router->group()`
  is **renamed `service`** in the new convention to eliminate the ambiguity.
- **`group`** — the domain-concept grouping for the generated client (`inventory`, `billing`,
  `routes`, `workspaces`). This is a NEW required named parameter on individual route
  definitions.
- **`action`** — the explicit verb for the generated method name. When the URL already
  encodes a clear action and no alias is needed, the generator derives it from the last
  non-id URL segment (kebab→camel). When the URL action word would produce a misleading
  method name, the route may declare an explicit `action:` override. The `action` parameter
  is optional; `group` is mandatory.
- **`streaming`** — boolean, optional, default `false`. When `true` the generator skips
  the route (see A1).

**Exact route definition syntax (before / after):**

```php
// BEFORE (current — scope conflated with service, no group)
$router->group('/portal/tenant/{tenantId}', ['middleware' => 'tenant-access', 'scope' => 'portal'], function() {
    $router->get('/items',              ListItemsRoute::class);
    $router->get('/items/{id}',         GetItemRoute::class);
    $router->post('/items/create',      CreateItemRoute::class);
});

// AFTER (new — service declared on group, group+action+streaming on each route)
$router->group('/portal/tenant/{tenantId}', ['middleware' => 'tenant-access', 'service' => 'portal'], function() {
    $router->get('/items',              ListItemsRoute::class,      group: 'inventory');
    $router->get('/items/{id}',         GetItemRoute::class,        group: 'inventory');
    $router->post('/items/create',      CreateItemRoute::class,     group: 'inventory');
    $router->post('/items/{id}/update', UpdateItemRoute::class,     group: 'inventory');
    // streaming route — generator skips, emits notice
    $router->get('/workspaces/{id}/events', WorkspaceEventsRoute::class, group: 'workspaces', streaming: true);
});
```

**If `group` is absent on a route definition, the generator MUST emit a hard error and
abort.** It does NOT fall back to inferring the group from path segments. This is the
lesson from the v3.30 breakage: silent inference produces a different client shape on every
regen and ships broken consumers. Hard error + fix-the-declaration is the only safe path.

**Generator error format:**
```
[stone generate client] ERROR: Route GET /portal/tenant/{tenantId}/items has no `group` declaration.
Add group: '<concept>' to the route definition. Generation aborted.
```

**Cross-reference:** §8 (URL route convention and PHP route definition pattern — update
the example there to use the new `service` / `group` parameters), §7 (grouping table).

---

#### A3 — Non-tenant route homes: health, JWKS, root, and public inbound webhooks

**Trigger:** Several route categories are not tenant-scoped and several are direction-reversed
(server ← external caller, often API-key authenticated rather than JWT): health checks,
JWKS discovery, home/root, and public inbound webhooks such as Razorpay payment callbacks
(`POST /payments/webhook`) and job-queue callbacks (`POST /jobs/callback`).

**Decision:** All of the following are **EXCLUDED from the generated business client**,
consistently with the existing exclusion of `/api/internal/*` and auth routes (B4):

| Route category | Example | Reason for exclusion |
|---|---|---|
| Health check | `GET /health` | Infrastructure probe, no business logic |
| JWKS discovery | `GET /.well-known/jwks.json` | Auth infrastructure, handled by auth-client |
| Root / home | `GET /` | Not a business API endpoint |
| Public inbound webhooks | `POST /payments/webhook`, `POST /jobs/callback` | Direction-reversed; called BY external systems, not BY the client |

**No "public/integration" group is introduced.** A grouping in the generated client implies
a caller-invoked method. Direction-reversed routes (webhooks) are server-side receivers —
adding them to the client API would be semantically wrong. Plain exclusion is the correct
treatment.

**How excluded routes are declared:**

```php
// Excluded from generation — declare service: 'infra' OR annotate exclude: true
$router->get('/health',                  HealthRoute::class,          service: 'infra');
$router->get('/.well-known/jwks.json',   JwksRoute::class,            service: 'infra');
$router->post('/payments/webhook',       RazorpayWebhookRoute::class, service: 'webhook');
$router->post('/jobs/callback',          JobCallbackRoute::class,     service: 'webhook');
```

The generator skips any route whose `service` is `infra` or `webhook`. If the route
has no `service` and no `group`, the A2 hard-error rule applies (missing `group`). This
means every route must have either a `group` (include) or a `service: 'infra'|'webhook'`
(exclude) — nothing falls through silently.

**Webhook security note (not a client-SDK concern, recorded here for completeness):**
inbound webhooks MUST validate a provider signature (e.g. Razorpay `X-Razorpay-Signature`
header) server-side. They are NOT JWT-authenticated. The route middleware for `service:
'webhook'` routes MUST skip the standard JWT auth middleware.

**Cross-reference:** B4 (auth routes excluded), §8 (hierarchy table — add `infra` and
`webhook` as excluded service values), §13 Phase 4 (route restructure checklist — add
webhook routes to the exclusion pass).

---

#### A4 — Sub-resource vs action vs cross-entity group: worked examples

**Trigger:** Ambiguity about when a nested URL segment is an `{action}` on the parent
group vs. deserving its own `group`. logisticsapp has `GET /portal/.../routes/:id/shipments`
(shipments belonging to a route) alongside cross-entity `search`.

**Rule (binding):** The `group` declaration on the route definition is the sole authority.
Path segment depth is irrelevant. The question "is this a sub-resource or its own group?"
is answered by the declaring developer — NOT inferred from path structure. The convention
below guides the decision:

| Pattern | Recommended grouping | Generated method | Rationale |
|---|---|---|---|
| `GET /routes/:id/shipments` | `group: 'routes'` | `routes.shipments(id)` | Shipments are a child view of a route; callers think "give me this route's shipments" |
| `POST /routes/:id/assign-driver` | `group: 'routes'` | `routes.assignDriver(id, data)` | Action on a route resource; RPC-style verbs are valid actions |
| `POST /routes/:id/start` | `group: 'routes'` | `routes.start(id)` | Same — state-transition action on a specific route |
| `GET /shipments/search?q=...` | `group: 'shipments'` | `shipments.search(params)` | Shipment-scoped search; not cross-entity |
| `GET /search?q=...&types=routes,shipments` | `group: 'search'` | `search.global(params)` | Genuine cross-entity search; own group per §0 search rule |
| `GET /dashboard` | `group: 'dashboard'` | `dashboard.summary()` | Composed/cross-entity view; purpose group per §0 |

**The sub-resource-as-action pattern** (`routes.shipments(id)`) is preferred when:
- the nested resource only makes sense in the context of the parent (shipments of a
  specific route), AND
- the nested resource is not independently queryable without the parent ID.

**The own-group pattern** (`shipments.*`) is used when:
- the resource has standalone operations (create, list all, search) that don't require
  a parent ID, OR
- the route developer explicitly judges it a first-class domain concept.

**RPC-style action verbs** (`start`, `stop`, `optimize`, `assign-driver`) are valid
`{action}` values and produce valid generated method names (`routes.start(id)`,
`routes.optimize(id, params)`). They are NOT a signal that the route needs its own group.

**Cross-reference:** §0 URL & grouping convention (naming authority), §7 (grouping table),
§8 (URL route convention).

---

#### A5 — Non-`:id` tail path parameters

**Trigger:** Platforms use `:tracking_number`, `:slug`, `:package_id` instead of `:id`
in the optional tail segment `[/{id}]`. The convention currently only names this param
`{id}`.

**Decision: platforms normalize tail path parameters to `:id`.** The alternative — allowing
arbitrary param names and mapping each to a distinct generated method argument name —
requires the generator to read semantic intent from param names and produces inconsistent
method signatures across platforms (some `get(id)`, some `get(trackingNumber)`, some
`get(slug)`). Normalization is the lower-churn option.

**Rule:**
- The tail path parameter in a route definition is always named `:id` in routes.php.
- The generated method argument is always named `id` with type `string | number`.
- The PHP route handler receives `id` from the path and maps it to its domain concept
  internally (e.g. `$trackingNumber = $params['id']`). This mapping lives in the handler,
  not in the URL contract.
- **Exception for semantic clarity:** when a route handler is authored, the developer MAY
  declare `param: 'tracking_number'` on the route definition to document the domain meaning.
  The generator still emits `id` in the TypeScript method signature, but the PHP handler
  receives the named param. This is documentation, not a behavior change.

**Migration note (one-time):** existing routes using `:tracking_number`, `:slug`, etc.
rename to `:id` during the Phase 4 route restructure sweep. No URL-breaking change is
needed for routes already using `:id`.

**Rationale:** Callers always know from context what the ID represents (they just fetched
the entity). A typed DTO (`T.TrackingLookupResponse`) communicates the domain semantics
more precisely than a parameter name.

**Cross-reference:** §8 (URL route convention, `[/{id}]` segment), §7 (generated method
signatures).

---

#### A6 — Admin client is a separate generated client (OQ3 CLOSED)

**Trigger:** OQ3 was open: "Admin → separate generated client (no tenant scope)." Both
validated platforms (logisticsapp admin, webmeteor admin) confirm admin routes exist
alongside portal routes and have distinct access-control semantics.

**Decision:**

`php stone generate client` produces **two separate packages** per platform that has both
surfaces:

| Package | Directory | Tenant scope | Auth |
|---|---|---|---|
| Business (tenant) client | `docker/api/client/{service}/` | `setTenant()` on T3; JWT-baked on T2; none on T1 | Identity JWT + tenant membership |
| Admin client | `docker/api/client/admin/` | None — admin acts on the entire platform | Identity JWT with admin role claim |

**Partitioning mechanism — how routes are assigned to each package:**

The `service` declared on the route definition (see A2) is the partition key:

```php
// Routes with service: 'portal' → business client package (docker/api/client/portal/)
$router->group('/portal/tenant/{tenantId}', ['middleware' => 'tenant-access', 'service' => 'portal'], function() {
    $router->get('/items', ListItemsRoute::class, group: 'inventory');
    // ...
});

// Routes with service: 'admin' → admin client package (docker/api/client/admin/)
$router->group('/admin', ['middleware' => 'admin-access', 'service' => 'admin'], function() {
    $router->get('/tenants',            ListTenantsRoute::class,     group: 'tenants');
    $router->get('/tenants/{id}',       GetTenantRoute::class,       group: 'tenants');
    $router->post('/tenants/{id}/suspend', SuspendTenantRoute::class, group: 'tenants');
    $router->get('/users',              ListUsersRoute::class,       group: 'users');
    $router->get('/analytics/overview', OverviewRoute::class,        group: 'analytics');
});
```

The generator runs once and emits one package per distinct `service` value that has
at least one non-excluded route. `service: 'infra'` and `service: 'webhook'` routes (A3)
are always excluded from all packages.

**Admin client shape:** identical structure to the business client — `MinimalHttp`,
`TokenStore`, `ApiError` verbatim; groups and methods generated from `group:` declarations;
all HTTP verbs (GET/POST/PUT/DELETE/PATCH) per §5. The sole difference is the absence of `setTenant()` and the absence of any
`/tenant/{id}` segment in generated URLs.

**Angular consumption:** the admin Angular service (`docker/admin/`) references
`"file:../api/client/admin"` in its `package.json`. It never imports the portal client.
The portal Angular service (`docker/portal/`) references `"file:../api/client/portal"`.
Packages are never cross-imported.

**Platforms with a single surface** (e.g. a T1 platform with only a `public` service)
emit a single package. The generator emits one package per non-excluded service; platforms
without an admin surface emit zero admin packages.

**Cross-reference:** §3 (architecture diagram — add admin client box), §4 (package
structure — note the per-service directory), §7 (ApiClient — note that the admin client
has no `setTenant()`), §9 (Tenant Scoping — note admin client has no tenant scope),
§13 Phase 3 (generator update — emit one package per service).

---

### Reconciliation required in AUTH-SPEC (single source of truth)
**RESOLVED 2026-06-14** — AUTH-SPEC §T has been amended. The `/{service}/tenant/{tenantId}/{group}/{action}`
convention is now the authoritative record in AUTH-SPEC. This client spec defers to it.

### Process gate before sign-off
**CLOSED 2026-06-14** — validated against logisticsapp (T3, mainstream) and webmeteor (T2 +
streaming + RPC). Convention held; 6 gaps/ambiguities surfaced and resolved as A1–A6 above.

---

## Table of Contents

1. [Problem Statement](#1-problem-statement)
2. [Design Goals](#2-design-goals)
3. [Architecture](#3-architecture)
4. [Generated Package Structure](#4-generated-package-structure)
5. [MinimalHttp — Generated Connection Layer](#5-minimalhttp--generated-connection-layer)
6. [TokenStore — Frozen Token Management](#6-tokenstore--frozen-token-management)
7. [ApiClient — Module-Grouped Route Methods](#7-apiclient--module-grouped-route-methods)
8. [URL Route Convention](#8-url-route-convention)
9. [Tenant Scoping — setTenant()](#9-tenant-scoping--settenant)
10. [Request / Response Contract](#10-request--response-contract)
11. [Error Handling](#11-error-handling)
12. [ngx-stonescriptphp-client — Angular Wrapper Role](#12-ngx-stonescriptphp-client--angular-wrapper-role)
13. [Migration Path](#13-migration-path)
14. [Files & Binary I/O — separate service + client](#14-files--binary-io--separate-service--client-not-the-json-sdk)

---

## 1. Problem Statement

The current `php stone generate client` command emits a TypeScript `ApiClient` class that:

1. **Depends on `ngx-stonescriptphp-client`** for its HTTP connection (`ApiConnectionService`). This means:
   - The generated client cannot be used outside Angular.
   - npm installs a nested copy of `ngx-stonescriptphp-client` inside the generated package, causing TypeScript to see two separate declarations of the same class — breaking `new ApiClient(connection)` with TS2345.
   - The generated client is useless if the consumer doesn't also set up the Angular library.

2. **Groups all routes under a single property named after the business entity** (e.g. `api.stores.*`). A platform with 200 routes ends up with 200 methods under one namespace. `api.stores.postPortalInventoryItems(storeId, data)` communicates neither the module nor the intent — `tenantId` leaks into every call signature even though the consumer sets their tenant once per session.

3. **Delegates token management to the Angular library**, which then offers configurable storage strategies (localStorage, IndexedDB, cookie). This is unnecessary complexity — the choice is not meaningful for the target use case and creates decision fatigue and divergent implementations across platforms.

4. **Does not own the URL convention** — the generated URL paths (e.g. `/stores/{storeId}/portal/inventory/items`) were driven by a PHP route change rather than a considered client SDK design. The service discriminator (`portal`) ends up buried after the tenant scoper (`stores/{storeId}`), inverting the natural hierarchy.

5. **Uses HTTP verbs as semantic differentiators** (PUT for update, DELETE for delete). This spreads the semantic intent across both the HTTP method and the route — creating synonym drift when teams name routes across 11 platforms independently.

The net effect: every platform that consumes the generated client needs a hand-written Angular service (`ApiClientService`) that re-maps all generated methods back to raw `apiConnection.get/post` calls. The generator is producing dead weight.

---

## 2. Design Goals

| Goal | Description |
|------|-------------|
| **Self-contained** | The generated package has zero runtime dependencies. No Angular, no axios, no external HTTP lib. |
| **Framework-agnostic** | The generated `ApiClient` works in Angular, React, Vue, Svelte, or vanilla JS. |
| **Module-grouped** | Routes are grouped by concept, declared on the route: `api.inventory.create()`, `api.billing.createBill()`. No `api.stores.*` catchall. |
| **Tenant-context-once** | The consumer calls `api.setTenant(id)` once after tenant selection (T3 only). All subsequent tenant-scoped calls use that context silently. No `tenantId` in every method signature. |
| **Frozen token management** | localStorage, fixed key names, no choices. AUTH-SPEC owns the contract. |
| **All HTTP verbs** | Reads → `GET`. Writes → `POST` by convention (action name carries the verb), but the client also supports `PUT`/`DELETE`/`PATCH` (since v4.2.0) for routes that declare them. |
| **Auth is separate** | The business client contains no auth routes. Auth is the domain of `stonescriptphp-auth-client` (contract interface) and its builtin or external concrete implementations. |
| **Angular wrapper is thin** | `ngx-stonescriptphp-client` wraps the generated client for Angular-specific concerns (signals, route guards, UI components). It does not own HTTP or tokens. |
| **Source of truth is the API** | The generated client mirrors `routes.php` exactly. When routes change, regenerate. No hand-maintained URL strings in Angular code. |
| **Broken regen = red build** | `dist/` is un-gitignored. A failed regeneration fails the CI build-gate — it does not silently ship stale types. |

---

## 3. Architecture

```
┌───────────────────────────────────────────────────────────────────┐
│  Generated Business Client  (docker/api/client/{service}/)        │
│  Produced by: php stone generate client                           │
│                                                                   │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐  │
│  │  MinimalHttp    │  │  TokenStore     │  │  DTOs / types   │  │
│  │  (fetch-based,  │  │  (localStorage, │  │  (from PHP DTOs)│  │
│  │   all verbs)    │  │   frozen keys)  │  │                 │  │
│  └────────┬────────┘  └────────┬────────┘  └─────────────────┘  │
│           │                    │                                   │
│  ┌────────▼────────────────────▼────────────────────────────────┐ │
│  │  ApiClient  (tenancy-mode-aware)                              │ │
│  │                                                               │ │
│  │  T3 (Multi-Tenant/URL — medstoreapp, logisticsapp, etc.):    │ │
│  │    setTenant(id) — called once after tenant selection         │ │
│  │    inventory.list()  inventory.create()  inventory.update()   │ │
│  │    billing.create()  billing.list()                           │ │
│  │    reports.stock()   reports.salesDaily()                     │ │
│  │    settings.get()    settings.update()                        │ │
│  │    onboarding.status()  onboarding.bulkCreate()               │ │
│  │    ...one readonly property per group, one fn per route       │ │
│  │                                                               │ │
│  │  T2 (webmeteor): tenant encoded in platform JWT via exchange  │ │
│  │    no setTenant() — no URL tenant segment                     │ │
│  │                                                               │ │
│  │  T1 (single-tenant): no tenant scope at all                   │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  Zero external dependencies.  Plain Promises.  No Angular.        │
└──────────────────────┬──────────────────────────────────────────┘
                       │  wraps
          ┌────────────┴───────────────────────────┐
          │                                        │
┌─────────▼─────────────────────┐    ┌────────────▼───────────────────┐
│  stonescriptphp-auth-client   │    │  ngx-stonescriptphp-client     │
│  (opensource contract only)   │    │  (Angular library)             │
│                               │    │                                │
│  IAuthClient interface        │    │  provideNgxStoneScriptPhpClient │
│  DTOs + error codes           │    │    → constructs ApiClient       │
│  Token convention (keys)      │    │    → provides via Angular DI    │
│  Envelope normalization spec  │    │  AuthService: signals, guards   │
│  No concrete HTTP class       │    │  <lib-tenant-login> component   │
│                               │    │  Error handler → toast/alert    │
│  ← builtin impl: generated    │    │                                │
│  ← external impl: private pkg │    │  No HTTP, tokens, or URLs.     │
└───────────────────────────────┘    └────────────────────────────────┘
```

---

## 4. Generated Package Structure

`php stone generate client` writes to `docker/api/client/{service}/`:

```
client/
└── portal/
    ├── package.json          # zero dependencies, no peerDependencies
    ├── tsconfig.json
    ├── .gitignore            # does NOT ignore dist/ — dist is un-gitignored (B3)
    ├── src/
    │   ├── http.ts           # MinimalHttp — emitted verbatim (not generated)
    │   ├── tokens.ts         # TokenStore — emitted verbatim (not generated)
    │   ├── errors.ts         # ApiError class — emitted verbatim
    │   ├── types.ts          # All DTOs — generated from PHP DTO classes
    │   └── client.ts         # ApiClient — generated from routes.php
    └── dist/                 # committed — CI build-gate: regen must produce clean dist
        ├── index.js
        └── index.d.ts
```

`package.json`:

```json
{
  "name": "@stonescript/api-client",
  "version": "0.0.0",
  "description": "Auto-generated API client for {platform} {service}",
  "main": "dist/index.js",
  "types": "dist/index.d.ts",
  "scripts": {
    "build": "tsc"
  },
  "dependencies": {},
  "peerDependencies": {},
  "devDependencies": {
    "typescript": "^5.0.0"
  }
}
```

No external runtime dependencies. The `file:` reference in the Angular service's `package.json` points here.

**CI build-gate (B3):** After any route or DTO change, `php stone generate client` must be run and the resulting `dist/` committed. CI verifies that `dist/` matches a clean `tsc` build of the generated `src/`. A stale `dist/` fails the build — it does not ship silently.

---

## 5. MinimalHttp — Generated Connection Layer

Emitted verbatim (not generated from routes). Included in every client output.

**HTTP methods (B2, amended v4.2.0):** Supports `GET`, `POST`, `PUT`, `DELETE`, and `PATCH`. Reads → `GET`; writes → `POST` by naming convention (the action name carries the semantic verb, minimizing synonym drift), but `PUT`/`DELETE`/`PATCH` routes are first-class: `MinimalHttp` exposes `put()`, `delete()`, and `patch()` that delegate to the same private `request()` as `post()` — identical auth-header injection, 401-refresh retry, and envelope handling. `DELETE` carries an optional body. The method-emission step emits real typed methods for every verb (no `Unsupported HTTP method` reject-stub).

**Envelope contract (B5):** Handles `{status:"ok"|"error", message, data}` only. Returns `body.data` verbatim on success (including `null` — no `?? body` fallback). Exposes `body.data.error` as `ApiError.code` on failure. Guards `res.json()` against non-JSON transport errors.

```typescript
// src/http.ts — emitted verbatim by php stone generate client

import { TokenStore } from './tokens';
import { ApiError } from './errors';

export interface HttpParams {
  [key: string]: string | number | boolean | null | undefined;
}

export class MinimalHttp {
  constructor(
    private readonly baseUrl: string,
    private readonly tokens: TokenStore,
    // Refresh endpoint pinned to AUTH-SPEC §4a: POST /api/auth/refresh.
    // Do not change without updating AUTH-SPEC §token-contract.
    private readonly refreshEndpoint: string = '/api/auth/refresh',
  ) {}

  async get<T = unknown>(path: string, params?: HttpParams): Promise<T> {
    return this.request<T>('GET', path, undefined, params);
  }

  async post<T = unknown>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('POST', path, body);
  }

  async put<T = unknown>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('PUT', path, body);
  }

  async patch<T = unknown>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('PATCH', path, body);
  }

  // DELETE may carry an optional body. Mirrors post().
  async delete<T = unknown>(path: string, body?: unknown): Promise<T> {
    return this.request<T>('DELETE', path, body);
  }

  private async request<T>(
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH',
    path: string,
    body?: unknown,
    params?: HttpParams,
    isRetry = false,
  ): Promise<T> {
    const url = new URL(this.baseUrl + path);
    if (params) {
      for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null) {
          url.searchParams.set(k, String(v));
        }
      }
    }

    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    const token = this.tokens.get();
    if (token) headers['Authorization'] = `Bearer ${token}`;

    let res: Response;
    try {
      res = await fetch(url.toString(), {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
      });
    } catch (networkErr) {
      throw new ApiError('Network error — check your connection', 0, networkErr, null);
    }

    // 401 → attempt one token refresh, then retry
    if (res.status === 401 && !isRetry) {
      const refreshed = await this.attemptRefresh();
      if (refreshed) {
        return this.request<T>(method, path, body, params, true);
      }
      this.tokens.clear();
      throw new ApiError('Session expired. Please log in again.', 401, null, null);
    }

    // Guard non-JSON responses (5xx HTML error pages, etc.)
    let data: unknown;
    try {
      data = await res.json();
    } catch {
      throw new ApiError(
        `Server returned non-JSON response (HTTP ${res.status})`,
        res.status,
        null,
        null,
      );
    }

    // StoneScriptPHP envelope: {status, message, data}
    // success ⇔ status === "ok" → return body.data verbatim (B5)
    const envelope = data as Record<string, unknown>;
    if (!envelope || envelope['status'] !== 'ok') {
      const message = (envelope?.['message'] as string) ?? 'Request failed';
      const code    = (envelope?.['data'] as Record<string, unknown>)?.['error'] as string ?? null;
      throw new ApiError(message, res.status, envelope, code);
    }

    // Return data.data verbatim — null is a valid value, do NOT fall back to body (B5)
    return envelope['data'] as T;
  }

  private async attemptRefresh(): Promise<boolean> {
    const refresh = this.tokens.getRefresh();
    if (!refresh) return false;

    let res: Response;
    try {
      res = await fetch(this.baseUrl + this.refreshEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refresh }),
      });
    } catch {
      return false;
    }

    if (!res.ok) return false;

    let data: unknown;
    try { data = await res.json(); } catch { return false; }

    // Read the field name pinned in AUTH-SPEC §token-contract.
    // Do not guess between data.access_token and data.data.access_token — they are different envelopes.
    const envelope = data as Record<string, unknown>;
    const newAccess  = envelope?.['data'] !== undefined
      ? (envelope['data'] as Record<string, unknown>)?.['access_token'] as string | undefined
      : envelope?.['access_token'] as string | undefined;

    if (newAccess) {
      this.tokens.set(newAccess);
      const newRefresh = (envelope?.['data'] as Record<string, unknown>)?.['refresh_token']
        ?? envelope?.['refresh_token'];
      if (typeof newRefresh === 'string') this.tokens.setRefresh(newRefresh);
      return true;
    }

    return false;
  }
}
```

---

## 6. TokenStore — Frozen Token Management

Emitted verbatim. Not configurable. Not injectable. AUTH-SPEC owns the key names and refresh route — this class is the single implementation of that contract in the business client.

The `typeof localStorage !== 'undefined'` guard is present on **every** read and write method — defensive against SSR/test environments, not an indication that SSR is supported (it is out of scope per §0 OQ1).

```typescript
// src/tokens.ts — emitted verbatim by php stone generate client

// Key names are owned by AUTH-SPEC §token-contract. Do not rename.
const ACCESS_KEY  = 'ssp_access_token';
const REFRESH_KEY = 'ssp_refresh_token';

export class TokenStore {
  get(): string | null {
    return typeof localStorage !== 'undefined'
      ? localStorage.getItem(ACCESS_KEY)
      : null;
  }

  set(token: string): void {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(ACCESS_KEY, token);
    }
  }

  getRefresh(): string | null {
    return typeof localStorage !== 'undefined'
      ? localStorage.getItem(REFRESH_KEY)
      : null;
  }

  setRefresh(token: string): void {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(REFRESH_KEY, token);
    }
  }

  clear(): void {
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem(ACCESS_KEY);
      localStorage.removeItem(REFRESH_KEY);
    }
  }

  hasToken(): boolean {
    return !!this.get();
  }
}
```

**Why localStorage is frozen (B6):**
- Target use case is a browser SPA. localStorage is appropriate.
- IndexedDB is async and overkill for a JWT string.
- HttpOnly cookies require server-side session handling — a different auth model entirely.
- An injectable storage adapter is deferred — the first real non-localStorage consumer triggers that work. Adding it later is backward-compatible (optional constructor arg defaulting to localStorage).

**Single token authority (2026-06-15):** This `TokenStore` is the one token authority for the entire SDK family — the JSON `ApiClient`, the files client (`@progalaxyelabs/ngx-stonescriptphp-files-client`), and any streaming helper read the access token from **here**. No sibling client keeps its own token copy or accepts a hand-passed `token` argument; doing so re-introduces the `access_token` vs `ssp_access_token` drift bug. See §14.

**Tokens are OPAQUE to the client (2026-06-15, AUTH-SPEC §S3a):** `TokenStore` stores, returns, and clears token *strings* — it MUST NOT expose any claim-reading or JWT-decode method, and clients MUST NOT decode tokens anywhere (no `decodeJwtPayload`, no `atob(token.split('.')[1])`, not even "unverified client-side" reads). A token is an opaque bearer credential; its internal shape is the server's private business and changes without notice. When the client needs identity/session facts (tenant selected?, role, onboarding state, user id/email, display name) it **asks the server** (`GET /api/auth/me` / `/api/auth/profile` / per-platform session+onboarding endpoints) and the server serves them in JSON. The client asks for what it needs; it does not introspect the credential. Any such decode in client code is a defect to remove (the old `client-core` `TokenService.decodeJwtPayload` is deprecated — drop it).

---

## 7. ApiClient — Module-Grouped Route Methods

The generator reads `routes.php` and groups routes by the `group` declared on each route definition. The group name is the API concept served — **never** inferred from path segments, table names, or SQL schema.

### Grouping table (T3 portal — medstoreapp example)

| Route declaration | Group | Generated method |
|------------------|-------|-----------------|
| `GET /portal/tenant/{tenantId}/items` | `inventory` | `inventory.list()` |
| `GET /portal/tenant/{tenantId}/items/{id}` | `inventory` | `inventory.get(id)` |
| `GET /portal/tenant/{tenantId}/items/search` | `inventory` | `inventory.search(params)` |
| `POST /portal/tenant/{tenantId}/items/create` | `inventory` | `inventory.create(data)` |
| `POST /portal/tenant/{tenantId}/items/{id}/update` | `inventory` | `inventory.update(id, data)` |
| `POST /portal/tenant/{tenantId}/items/{id}/delete` | `inventory` | `inventory.delete(id)` |
| `POST /portal/tenant/{tenantId}/items/{id}/adjust` | `inventory` | `inventory.adjust(id, data)` |
| `GET /portal/tenant/{tenantId}/bills` | `billing` | `billing.list()` |
| `POST /portal/tenant/{tenantId}/bills/create` | `billing` | `billing.create(data)` |
| `POST /portal/tenant/{tenantId}/bills/{id}/delete` | `billing` | `billing.delete(id)` |
| `GET /portal/tenant/{tenantId}/reports/stock` | `reports` | `reports.stock()` |
| `GET /portal/tenant/{tenantId}/reports/sales/daily` | `reports` | `reports.salesDaily(params)` |
| `GET /portal/tenant/{tenantId}/dashboard` | `dashboard` | `dashboard.summary()` |
| `GET /portal/tenant/{tenantId}/settings/business` | `settings` | `settings.getBusiness()` |
| `POST /portal/tenant/{tenantId}/settings/business/update` | `settings` | `settings.updateBusiness(data)` |
| `GET /subscription/status` | `subscription` | `subscription.status()` |
| `POST /subscription/orders/create` | `subscription` | `subscription.createOrder(data)` |

**Note on T2 and T1:** For T2 platforms (webmeteor), the tenant is encoded in the platform JWT; no `/tenant/{tenantId}` segment exists in URLs and `setTenant()` is not generated. For T1 platforms, no tenant scope at all.

**Generated output shape (T3 example — medstoreapp portal):**

```typescript
// src/client.ts — generated from routes.php

import { MinimalHttp, HttpParams } from './http';
import { TokenStore }              from './tokens';
import * as T                      from './types';

export class ApiClient {
  readonly tokens: TokenStore;        // exposed so ngx wrapper can read auth state
  private readonly http: MinimalHttp;
  private _tenantId: string | number | null = null;

  constructor(baseUrl: string) {
    this.tokens = new TokenStore();
    this.http   = new MinimalHttp(baseUrl, this.tokens);
  }

  /**
   * Set the active tenant context (T3 platforms only).
   * Call once after the user selects a tenant. Re-callable for tenant switching.
   * All tenant-scoped module calls will use this tenantId in the URL path.
   * Single-active-context constraint: this instance tracks one tenant at a time.
   * For simultaneous multi-tenant access, create separate ApiClient instances.
   */
  setTenant(id: string | number): this {
    this._tenantId = id;
    return this;
  }

  private get t(): string {
    if (this._tenantId === null) {
      throw new Error(
        '[ApiClient] Tenant context not set. Call setTenant(id) after tenant selection.',
      );
    }
    return `/portal/tenant/${this._tenantId}`;
  }

  // ─────────────────────────────────────────────────────────────
  // inventory — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly inventory = {
    list:        (p?: T.ListItemsParams)          => this.http.get<T.InventoryListResponse>(`${this.t}/items`, p),
    get:         (id: number)                     => this.http.get<T.ItemDetail>(`${this.t}/items/${id}`),
    search:      (p: T.SearchItemsParams)         => this.http.get<T.Item[]>(`${this.t}/items/search`, p),
    lowStock:    ()                               => this.http.get<T.Item[]>(`${this.t}/items/low-stock`),
    expiring:    ()                               => this.http.get<T.Item[]>(`${this.t}/items/expiring`),
    batches:     (itemId: number)                 => this.http.get<T.Batch[]>(`${this.t}/items/${itemId}/batches`),
    create:      (d: T.CreateItemRequest)         => this.http.post<T.Item>(`${this.t}/items/create`, d),
    update:      (id: number, d: T.UpdateItemRequest) => this.http.post<T.Item>(`${this.t}/items/${id}/update`, d),
    delete:      (id: number)                     => this.http.post(`${this.t}/items/${id}/delete`, {}),
    adjust:      (id: number, d: T.AdjustRequest) => this.http.post(`${this.t}/items/${id}/adjust`, d),
    vendors:     ()                               => this.http.get<T.Vendor[]>(`${this.t}/vendors`),
    createVendor:(d: T.CreateVendorRequest)       => this.http.post<T.Vendor>(`${this.t}/vendors/create`, d),
    distributors:()                               => this.http.get<T.Distributor[]>(`${this.t}/distributors`),
    createDistributor: (d: T.CreateDistributorRequest) => this.http.post<T.Distributor>(`${this.t}/distributors/create`, d),
    locations:   ()                               => this.http.get<T.Location[]>(`${this.t}/item-locations`),
    createBatch: (d: T.CreateBatchRequest)        => this.http.post<T.Batch>(`${this.t}/batches/create`, d),
  };

  // ─────────────────────────────────────────────────────────────
  // billing — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly billing = {
    list:        ()                               => this.http.get<T.BillListResponse>(`${this.t}/bills`),
    get:         (id: number)                     => this.http.get<T.BillDetail>(`${this.t}/bills/${id}`),
    dailySummary:()                               => this.http.get(`${this.t}/bills/daily-summary`),
    create:      (d: T.CreateBillRequest)         => this.http.post<T.Bill>(`${this.t}/bills/create`, d),
    update:      (id: number, d: T.UpdateBillRequest) => this.http.post<T.Bill>(`${this.t}/bills/${id}/update`, d),
    delete:      (id: number)                     => this.http.post(`${this.t}/bills/${id}/delete`, {}),
    reserve:     (d: T.BillingReserveRequest)     => this.http.post(`${this.t}/bills/reserve`, d),
    clearReserve:(d: T.ClearReserveRequest)       => this.http.post(`${this.t}/bills/reserve/clear`, d),
  };

  // ─────────────────────────────────────────────────────────────
  // invoices (distributor purchase invoices) — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly invoices = {
    list:              (p?: T.ListInvoicesParams) => this.http.get<T.InvoiceListResponse>(`${this.t}/invoices`, p),
    get:               (id: number)               => this.http.get<T.InvoiceDetail>(`${this.t}/invoices/${id}`),
    attachments:       (id: number)               => this.http.get<T.Attachment[]>(`${this.t}/invoices/${id}/attachments`),
    create:            (d: T.CreateInvoiceRequest) => this.http.post<T.Invoice>(`${this.t}/invoices/create`, d),
    update:            (id: number, d: T.UpdateInvoiceRequest) => this.http.post<T.Invoice>(`${this.t}/invoices/${id}/update`, d),
    delete:            (id: number)               => this.http.post(`${this.t}/invoices/${id}/delete`, {}),
    addAttachment:     (id: number, d: T.AddAttachmentRequest) => this.http.post(`${this.t}/invoices/${id}/attachments/add`, d),
    deleteAttachment:  (id: number, attachmentId: number) => this.http.post(`${this.t}/invoices/${id}/attachments/${attachmentId}/delete`, {}),
    submitDistributor: (d: T.DistributorInvoiceRequest) => this.http.post(`${this.t}/distributor-invoices/submit`, d),
    updateDistributor: (id: number, d: T.DistributorInvoiceRequest) => this.http.post(`${this.t}/distributor-invoices/${id}/update`, d),
    getDistributor:    (id: number)               => this.http.get(`${this.t}/distributor-invoices/${id}`),
  };

  // ─────────────────────────────────────────────────────────────
  // dashboard — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly dashboard = {
    summary:    ()                                => this.http.get<T.DashboardResponse>(`${this.t}/dashboard`),
    stats:      ()                                => this.http.get<T.DashboardStats>(`${this.t}/dashboard/stats`),
    activities: (p?: T.ActivitiesParams)          => this.http.get(`${this.t}/dashboard/activities`, p),
    sales:      (p?: T.SalesParams)               => this.http.get(`${this.t}/dashboard/sales`, p),
  };

  // ─────────────────────────────────────────────────────────────
  // reports — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly reports = {
    stock:            ()                          => this.http.get<T.StockReportResponse>(`${this.t}/reports/stock`),
    salesDaily:       (p?: T.DateParams)          => this.http.get(`${this.t}/reports/sales/daily`, p),
    salesMonthly:     (p?: T.MonthYearParams)     => this.http.get(`${this.t}/reports/sales/monthly`, p),
    cashDaily:        (p?: T.DateParams)          => this.http.get(`${this.t}/reports/cash/daily`, p),
    cashMonthly:      (p?: T.MonthYearParams)     => this.http.get(`${this.t}/reports/cash/monthly`, p),
    profitLossDaily:  (p?: T.DateParams)          => this.http.get(`${this.t}/reports/profit-loss/daily`, p),
    profitLossMonthly:(p?: T.MonthYearParams)     => this.http.get(`${this.t}/reports/profit-loss/monthly`, p),
    caPackage:        (p: T.CaPackageParams)      => this.http.get(`${this.t}/reports/ca-package`, p),
  };

  // ─────────────────────────────────────────────────────────────
  // settings — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly settings = {
    getBusiness:    ()                            => this.http.get<T.BusinessSettings>(`${this.t}/settings/business`),
    list:           ()                            => this.http.get(`${this.t}/settings`),
    getByKey:       (key: string)                 => this.http.get(`${this.t}/settings/${key}`),
    updateBusiness: (d: T.UpdateBusinessSettings) => this.http.post<T.BusinessSettings>(`${this.t}/settings/business/update`, d),
    set:            (d: T.SetSettingRequest)      => this.http.post(`${this.t}/settings/set`, d),
    delete:         (key: string)                 => this.http.post(`${this.t}/settings/${key}/delete`, {}),
  };

  // ─────────────────────────────────────────────────────────────
  // users (tenant members / staff) — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly users = {
    list:          ()                             => this.http.get<T.User[]>(`${this.t}/users`),
    get:           (id: number)                   => this.http.get<T.User>(`${this.t}/users/${id}`),
    profile:       ()                             => this.http.get<T.UserProfile>(`${this.t}/users/profile`),
    invite:        (d: T.InviteUserRequest)       => this.http.post<T.User>(`${this.t}/users/invite`, d),
    update:        (id: number, d: T.UpdateUserRequest) => this.http.post<T.User>(`${this.t}/users/${id}/update`, d),
    delete:        (id: number)                   => this.http.post(`${this.t}/users/${id}/delete`, {}),
    updateProfile: (d: T.UpdateProfileRequest)    => this.http.post<T.UserProfile>(`${this.t}/users/profile/update`, d),
  };

  // ─────────────────────────────────────────────────────────────
  // onboarding — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly onboarding = {
    status:              ()                       => this.http.get(`${this.t}/onboarding/status`),
    medicineCategories:  ()                       => this.http.get(`${this.t}/onboarding/medicines/categories`),
    searchMedicines:     (p: T.SearchMedicinesParams) => this.http.get(`${this.t}/onboarding/medicines/search`, p),
    bulkCreate:          (d: T.OnboardingBulkCreateRequest) => this.http.post(`${this.t}/onboarding/bulk-create`, d),
    saveEstablishment:   (d: T.OnboardingEstablishmentRequest) => this.http.post(`${this.t}/onboarding/establishment/save`, d),
  };

  // ─────────────────────────────────────────────────────────────
  // stockReconciliation — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly stockReconciliation = {
    list:     ()                                  => this.http.get<T.StockReconciliationListResponse>(`${this.t}/stock-reconciliation`),
    get:      (id: number)                        => this.http.get(`${this.t}/stock-reconciliation/${id}`),
    summary:  (id: number)                        => this.http.get(`${this.t}/stock-reconciliation/${id}/summary`),
    create:   (d: T.CreateStockReconciliationRequest) => this.http.post(`${this.t}/stock-reconciliation/create`, d),
    update:   (id: number, d: T.UpdateStockReconciliationRequest) => this.http.post(`${this.t}/stock-reconciliation/${id}/update`, d),
    delete:   (id: number)                        => this.http.post(`${this.t}/stock-reconciliation/${id}/delete`, {}),
    start:    (id: number)                        => this.http.post(`${this.t}/stock-reconciliation/${id}/start`, {}),
    approve:  (id: number)                        => this.http.post(`${this.t}/stock-reconciliation/${id}/approve`, {}),
    complete: (id: number)                        => this.http.post(`${this.t}/stock-reconciliation/${id}/complete`, {}),
  };

  // ─────────────────────────────────────────────────────────────
  // payments — tenant context required
  // ─────────────────────────────────────────────────────────────
  readonly payments = {
    list:         ()                              => this.http.get(`${this.t}/payments`),
    get:          (id: number)                    => this.http.get(`${this.t}/payments/${id}`),
    byReference:  (type: string, id: number)      => this.http.get(`${this.t}/payments/by-reference/${type}/${id}`),
    create:       (d: T.CreatePaymentRequest)     => this.http.post(`${this.t}/payments/create`, d),
    update:       (id: number, d: T.UpdatePaymentRequest) => this.http.post(`${this.t}/payments/${id}/update`, d),
    delete:       (id: number)                    => this.http.post(`${this.t}/payments/${id}/delete`, {}),
  };

  // ─────────────────────────────────────────────────────────────
  // subscription — no tenant scope (uses identity JWT, cross-cutting)
  // ─────────────────────────────────────────────────────────────
  readonly subscription = {
    status:        ()                             => this.http.get('/subscription/status'),
    plans:         ()                             => this.http.get('/subscription/plans'),
    createOrder:   (d: T.CreateOrderRequest)      => this.http.post('/subscription/orders/create', d),
    verifyPayment: (d: T.VerifyPaymentRequest)    => this.http.post('/subscription/payments/verify', d),
  };

  // ─────────────────────────────────────────────────────────────
  // lookup — shared dropdown/reference data (tenant context required)
  // ─────────────────────────────────────────────────────────────
  readonly lookup = {
    dropdown: (table: string, field: string, p?: HttpParams) =>
                this.http.get(`${this.t}/lookup/dropdown/${table}/${field}`, p),
    states:   ()                                  => this.http.get(`/portal/lookup/states`),
  };
}
```

---

## 8. URL Route Convention

### Agreed convention (§0 B1)

```
/{service}/tenant/{tenantId}/{group}/{action}[/{id}]
```

Examples:

```
GET  /portal/tenant/42/items                          → inventory.list()
GET  /portal/tenant/42/items/123                      → inventory.get(123)
GET  /portal/tenant/42/items/search?q=amox            → inventory.search({q:'amox'})
POST /portal/tenant/42/items/create                   → inventory.create(data)
POST /portal/tenant/42/items/123/update               → inventory.update(123, data)
POST /portal/tenant/42/items/123/delete               → inventory.delete(123)
POST /portal/tenant/42/items/123/adjust               → inventory.adjust(123, data)
GET  /portal/tenant/42/reports/sales/daily            → reports.salesDaily()
GET  /subscription/status                             → subscription.status()   (no tenant)
GET  /public/medicines/search?q=amox                  → (no auth, no tenant)
```

**Hierarchy:**

```
/{service}          — router's first split: portal | admin | public | api
  /tenant/{id}      — T3 only: framework middleware extracts id, validates membership (AUTH-SPEC §5c)
    /{group}        — declared concept group (inventory, billing, reports, …)
      /{action}     — declared action verb (create, update, delete, search, …)
        [/{id}]     — optional path parameter for a specific resource
```

**Why service-first:**
The PHP router's first decision is which handler group owns the request. Putting service last (old pattern: `/stores/{storeId}/portal/…`) meant parsing a variable-length tenant segment before knowing the service context. Service-first allows early-return routing.

**Why `tenant` not `store`/`warehouse`/`workspace`:**
AUTH-SPEC, the gateway, and the DB all use `tenant_id`. The URL must match the vocabulary of the authorization layer.

**Why GET + POST is the naming default (B2) — and why PUT/DELETE/PATCH are still supported:**
For most writes, PUT and DELETE are aliases for "update" and "remove" — already said by the action word in the URL. Defaulting writes to POST collapses synonym drift: teams don't debate "should this be PUT or PATCH?" for ordinary CRUD; the action name is the contract. However, some routes genuinely need REST verbs (idempotent PUT replace, RESTful DELETE, JSON-merge PATCH), and forcing them through POST-only meant those endpoints were uncallable. Since v4.2.0 the client emits real typed methods for PUT/DELETE/PATCH routes (§5). Keep using POST-by-convention for normal writes; reach for PUT/DELETE/PATCH only when the route is deliberately REST-shaped.

**PHP route definition pattern:**

```php
// routes.php

// T3 portal routes — tenant-scoped
$router->group('/portal/tenant/{tenantId}', ['middleware' => 'tenant-access'], function() {
    // inventory group — declared, not inferred
    $router->get('/items',                 ListItemsRoute::class,         group: 'inventory');
    $router->get('/items/{id}',            GetItemRoute::class,           group: 'inventory');
    $router->get('/items/search',          SearchItemsRoute::class,       group: 'inventory');
    $router->post('/items/create',         CreateItemRoute::class,        group: 'inventory');
    $router->post('/items/{id}/update',    UpdateItemRoute::class,        group: 'inventory');
    $router->post('/items/{id}/delete',    DeleteItemRoute::class,        group: 'inventory');
    $router->post('/items/{id}/adjust',    AdjustItemRoute::class,        group: 'inventory');

    // billing group
    $router->get('/bills',                 ListBillsRoute::class,         group: 'billing');
    $router->post('/bills/create',         CreateBillRoute::class,        group: 'billing');
    // ...
});

// Subscription — no tenant scope
$router->group('/subscription', function() {
    $router->get('/status',                SubscriptionStatusRoute::class, group: 'subscription');
    $router->post('/orders/create',        CreateOrderRoute::class,        group: 'subscription');
    // ...
});
```

The `group:` parameter is the grouping declaration (B3). The generator reads it — it does not infer the group from path segments.

The `{tenantId}` path parameter is extracted by the framework middleware, validated against the identity's memberships (AUTH-SPEC §5c), and injected into every route handler — not read from the request body.

---

## 9. Tenant Scoping — setTenant()

**T3 platforms (Multi-Tenant/URL: medstoreapp, logisticsapp, restrantapp, instituteapp):**

```typescript
// Consumer code (Angular component, or any JS)

const api = new ApiClient('https://api.medstoreapp.in');

// Step 1 — authenticate (handled by auth-client, not the business client):
// api.tokens.set(accessToken);   // auth-client sets the token after login

// Step 2 — set tenant context once after tenant selection:
api.setTenant(tenantId);  // e.g. setTenant(42)

// Step 3 — all tenant-scoped calls use that context silently:
const items    = await api.inventory.list();         // GET /portal/tenant/42/items
const bill     = await api.billing.create(billData); // POST /portal/tenant/42/bills/create
const dashboard= await api.dashboard.summary();      // GET /portal/tenant/42/dashboard
```

No `tenantId` in any call signature after `setTenant()`. The Angular library calls `api.setTenant(id)` inside its `AuthService.selectTenant()` method — the Angular developer never touches it directly.

**`setTenant()` is re-callable.** A user switching between tenants in the same session calls `setTenant(newId)` without a page reload. Single-active-context constraint: this instance tracks one tenant at a time. For genuine simultaneous multi-tenant access (e.g. admin comparing two tenants), create separate `ApiClient` instances.

**Early-error if called before `setTenant()`:**

```
Error: [ApiClient] Tenant context not set. Call setTenant(id) after tenant selection.
```

This is a programming error — caught immediately in development, never reaches production.

**T2 platforms (webmeteor):**

No `setTenant()`. The tenant is encoded in the platform JWT obtained via the auth exchange route. The generated `ApiClient` for T2 has no `t` getter and no `/tenant/{id}` prefix in any URL.

**T1 platforms (single-tenant):**

No `setTenant()`. No tenant scope. All routes are at `/{service}/{group}/{action}`.

---

## 10. Request / Response Contract

The generated DTOs mirror the PHP DTO classes. The generator reads `src/App/DTO/*.php` and emits TypeScript interfaces in `src/types.ts`.

**Business client envelope (builtin — B5):**

```json
{ "status": "ok",    "message": "",              "data": { ... } }
{ "status": "error", "message": "Human-readable", "data": { "error": "MACHINE_CODE", ... } }
```

`MinimalHttp` unwraps this envelope automatically:
- `status === "ok"` → returns `data` verbatim (including `null`).
- `status !== "ok"` → throws `ApiError` with `message`, `httpStatus`, and `code` (`data.error`).

Callers never see the envelope wrapper. They receive the payload directly on success, and a typed `ApiError` on failure.

**`ApiResponse` baseline type (amended v4.2.0):** the generated `types.ts` baseline is:

```typescript
export type ApiResponse = unknown;
```

Previously this was `Record<string, unknown> | unknown[] | null`. That union's `unknown[]` member broke strict narrowing — consumers could not write `const x = resp as MyType` and instead had to double-cast `resp as unknown as MyType`. Typing the baseline as `unknown` lets every consumer narrow the payload with a single `as MyType` cast (or a type guard). Platforms that generate specific DTOs still override the per-endpoint return types; `ApiResponse` is only the generic fallback. `ApiRequestBody` is unchanged.

**Auth-client envelope (external — handled by auth-client, not MinimalHttp):**

The `progalaxyelabs-auth` daemon uses a different envelope shape (`{success,...}`). That normalization is the auth-client's responsibility (AUTH-SPEC §P5). `MinimalHttp` in the business client does not handle it — they are separate concerns.

---

## 11. Error Handling

```typescript
// Consumer code
import { ApiError } from '@stonescript/api-client';

try {
  const bill = await api.billing.create(billData);
  // bill is the unwrapped data payload
} catch (e) {
  if (e instanceof ApiError) {
    console.error(e.message);     // "Item out of stock" (human-readable from API)
    console.error(e.code);        // "ITEM_OUT_OF_STOCK" (machine code from data.error — may be null)
    console.error(e.httpStatus);  // 200 (StoneScriptPHP error in envelope) or 401/503 (transport)
    console.error(e.response);    // full response body (for debugging)
  }
}
```

**`ApiError` class (emitted verbatim in `src/errors.ts`):**

```typescript
export class ApiError extends Error {
  constructor(
    message: string,
    public readonly httpStatus: number,
    public readonly response: unknown,
    public readonly code: string | null,    // data.error from envelope — null if absent
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
```

The Angular library (`ngx-stonescriptphp-client`) catches `ApiError` globally and maps `code` to user-facing toasts, retry prompts, or redirect actions. The generated client does not depend on any UI framework to do this.

---

## 12. ngx-stonescriptphp-client — Angular Wrapper Role

After this redesign, the Angular library's responsibility shrinks and becomes clearly bounded:

**Keeps:**
- `provideNgxStoneScriptPhpClient(apiUrl)` — constructs `ApiClient(apiUrl)`, registers it in Angular DI
- `AuthService` — mediates between auth-client and the business client:
  - calls `auth-client.login()` / `auth-client.selectTenant()` to handle auth flow
  - calls `api.setTenant(id)` after tenant selection (T3 platforms)
  - exposes `isAuthenticated` signal, `currentUser` signal, `currentTenant` signal
- Route guards (`authGuard`, `tenantSelectedGuard`)
- `<lib-tenant-login>` component — the login/OTP/OAuth UI shell
- Global error handler — catches `ApiError`, maps `code` to user-facing alert/toast
- Loading state directives (optional, additive)

**Removes:**
- `ApiConnectionService` — replaced by `MinimalHttp` inside the generated client
- Token management code — lives in the generated `TokenStore`
- HTTP interceptors for auth headers / refresh — `MinimalHttp` handles both
- Storage strategy configuration — frozen in `TokenStore`

**Relationship to auth-client:**

`ngx-stonescriptphp-client` depends on `stonescriptphp-auth-client` (the contract interface). It adapts the auth-client's events (login success, token refresh) into Angular signals and DI calls (`api.setTenant()`). It does NOT implement auth HTTP directly.

**The boundary test:** if you can describe what `ngx-stonescriptphp-client` does without mentioning HTTP requests, token storage, or URL strings — the boundary is correct.

---

## 13. Migration Path

**Phasing decision (§0 OQ5): big-bang.** No customers to protect, so generator self-containment + URL restructure + all 11 platforms migrate in one coordinated sweep. No dual-convention period. Each platform must clear factory QC + the CI build-gate (un-gitignored `dist/`) before it deploys — nothing half-migrates, nothing ships un-revalidated.

### Phase 1 — AUTH-SPEC amendments

1. Apply the pending-amendment note in AUTH-SPEC §T: update §T, §5c, and the §S1 path-prefix table from `/stores/:storeId/portal/…` to `/{service}/tenant/{tenantId}/…`.
2. AUTH-SPEC is the single source of truth — this client spec defers to it.

### Phase 2 — Contract validation (process gate from §0)

Validate the `/{service}/tenant/{tenantId}/{group}/{action}` convention against ≥2 non-medstoreapp platforms before implementation:

- **logisticsapp (T3):** routes involve warehouses, shipments, routes — verify group names fit the convention cleanly and don't collide with `tenant/` vocabulary.
- **webmeteor (T2):** no URL tenant — verify the T2 generated client shape (no `setTenant`, no `/tenant/{id}` segment) is coherent and the auth exchange flow maps correctly.
- **An admin surface:** admin has no tenant scope — verify the separate admin client approach (§0 OQ3) is clean.

This gate exists because a contract proven only on its drafting platform ships journey-seam bugs.

### Phase 3 — Generator (framework change)

1. Update `php stone generate client` to emit `MinimalHttp`, `TokenStore`, `ApiError` verbatim.
2. Implement declared `group:` on route definitions; generator reads declarations, never infers from paths.
3. Generate `setTenant(id)` on T3 clients; omit on T2 and T1.
4. Remove `ngx-stonescriptphp-client` dependency from generated `package.json`.
5. Generate only `get` and `post` calls in `ApiClient` — no `put` or `delete`.
6. Un-gitignore `dist/` in generated `.gitignore`; add CI build-gate step.
7. Split `stonescriptphp-auth-client` into contract interface + builtin impl (see B4).

### Phase 4 — Route restructure (all 11 platforms)

1. Update PHP route definitions from old convention to `/{service}/tenant/{tenantId}/{group}/{action}[/{id}]`.
2. Add `group:` declarations to each route.
3. Change all write routes from PUT/DELETE to POST with explicit action verbs.
4. Run `php stone generate client` → commit new `src/` + `dist/`.

### Phase 5 — Angular library slim-down

1. Remove `ApiConnectionService` from `ngx-stonescriptphp-client`.
2. Remove token management and HTTP interceptor code.
3. Update `provideNgxStoneScriptPhpClient(apiUrl: string)` — constructs `ApiClient(apiUrl)` internally.
4. Update `AuthService` to call `api.setTenant(id)` after tenant selection (T3).
5. Wire auth-client contract into `AuthService` (replaces direct auth API calls).

### Phase 6 — Platform updates (all 11)

1. Remove hand-written `ApiClientService` wrappers from all platforms.
2. Update Angular components: inject `ApiClient` via DI, call `api.{group}.{action}()` directly.
3. Run factory QC + build-gate on each platform before it deploys.

### Phase 7 — Files & binary I/O alignment (first cross-package "in-sync" checkpoint)

This phase precedes broad platform file migration. Its goal is a **proven, version-aligned trio**: the api-client generator, the `stonescriptphp-files` server, and the `ngx-stonescriptphp-files-client`, all consuming one token authority and E2E-green together.

1. Update `@progalaxyelabs/ngx-stonescriptphp-files-client` to source the access token from the api-client's `TokenStore` (§6/§14) — remove the hand-passed `token` parameter from its public methods and share `MinimalHttp`'s 401→refresh→retry behavior (delegate, don't duplicate).
2. Confirm `@progalaxyelabs/stonescriptphp-files` (server) JWT verification matches the api-client token contract (same key / audience / `ssp_*` access token).
3. **Pilot:** migrate medstoreapp portal file handling off raw `fetch` onto `FilesService`. No raw `fetch`/manual `Bearer` left for file I/O.
4. Verify all three are version-aligned and pass E2E together before any further platform adopts the files client.

---

## 14. Files & Binary I/O — separate service + client (NOT the JSON SDK)

**Decision (Pradeep, 2026-06-15):** The generated business client (`ApiClient` / `MinimalHttp`) is **JSON-only**. It MUST NOT grow blob / FormData / binary or streaming methods. Binary file I/O is owned by a dedicated service + client; streaming is owned by hand-written helpers (§5 / A1). Three lanes, **one** token authority.

### Three lanes
| Concern | Owner | Token source |
|---|---|---|
| Business data (JSON in/out) | generated `ApiClient` (`MinimalHttp`) | `TokenStore` (§6) — **the authority** |
| File upload / download / list / delete (binary) | `@progalaxyelabs/stonescriptphp-files` (Express + Azure Blob + JWT server) + `@progalaxyelabs/ngx-stonescriptphp-files-client` (`FilesService`) | reads the SAME token from `ApiClient.tokens` (§6) |
| Streaming (SSE / chunked) | hand-written helpers under `client/{service}/streaming/` (§5 A1) | reads via exposed `ApiClient.tokens` |

### Token authority (single source of truth)
- The generated `ApiClient`'s `TokenStore` (§6) is the **single token authority** for the whole SDK family. Key names (`ssp_access_token` / `ssp_refresh_token`) and the refresh route are owned by AUTH-SPEC.
- `@progalaxyelabs/ngx-stonescriptphp-files-client` MUST NOT read tokens from its own storage or a hand-passed `token` argument. It obtains the access token from the api-client's `TokenStore` (the same instance ngx provides via DI) and honors the same 401→refresh→retry behavior `MinimalHttp` implements (delegate to it; do not re-implement).
- Rationale: two independent token readers drift (the `access_token` vs `ssp_access_token` bug). One authority — every lane reads it.
- Integration: ngx's `provideNgxStoneScriptPhpClient(apiUrl)` constructs `ApiClient`; the files `FilesService` is given the same `ApiClient` (or its `tokens: TokenStore`) so both lanes share auth state. The files-client's public surface (`upload(file, entityType?, entityId?)`, `download(fileId): Blob`, `list()`, `delete(fileId)`) stays; only its token sourcing changes.

### Platforms MUST NOT
- Use raw `fetch` / `HttpClient` to the files service from platform code — use `FilesService`.
- Construct `Authorization: Bearer` headers by hand anywhere (re-introduces the stale-key bug).
- Add blob / FormData methods to `ApiClient`. A route that returns or accepts binary belongs to the files service.

---

*This spec is approved at the decision level. Implementation begins after Phase 2 validation sign-off.*
