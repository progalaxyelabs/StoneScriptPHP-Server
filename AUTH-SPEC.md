# StoneScriptPHP — Auth Route Specification

**Status:** Prescriptive. Code conforms to this spec; the spec is never reverse-engineered
from code. A deviation in the implementation is a bug in the implementation.

**Scope:** StoneScriptPHP servers in both auth modes:
- `external` — identity lifecycle (OTP, OAuth, accounts) lives in a separate auth server
  (e.g. progalaxyelabs-auth). Tenant lifecycle lives in the platform PHP API.
  Frontend calls the auth server directly for identity operations and the PHP API for
  tenant operations. Only `POST /auth/provision-tenant` and `POST /api/auth/exchange`
  live on the PHP API.
- `builtin` — both identity and tenant lifecycle live in the platform PHP API. Same
  endpoints, same shapes, same host.

---

## Core Principles

### P1 — Verified Profile

Every auth method (OTP, OAuth, password) produces a **verified profile**: a proven
contact + optional identity attributes + provider linkage. Once produced, it is the
canonical input for identity creation or lookup.

```
VerifiedProfile {
  contact:       { type: "email" | "phone", value: string }  // proven at verify time
  display_name:  string | null    // supplied by user or sourced from OAuth provider
  photo_url:     string | null    // sourced from OAuth provider, null for OTP
  provider:      "otp" | "google" | "progalaxyelabs" | "emailPassword"
  provider_uid:  string | null    // OAuth sub claim, null for OTP
}
```

`display_name` and `photo_url` are identity attributes populated opportunistically from
whatever method supplied them. Neither is required to exist before identity creation —
`display_name` is required from the user for OTP register specifically (see §1b).

### P2 — Explicit Intent

Intent is first-class for all auth methods. The user is either **signing in** or
**signing up**. The server applies intent-driven resolution:

| Intent | Account exists | Action |
|--------|----------------|--------|
| `signin` | yes | Log in — return tokens |
| `signin` | no | **Stop** — do not create. Return `no_account` result (see §3b) |
| `signup` | no | Create identity — return tokens |
| `signup` | yes | Log in — safe per P3. Return tokens; `identity_was_created: false` tells the frontend |

### P3 — Account-creation rule

> **Never create an account the user did not explicitly intend.**
> Logging in an existing, verified user is always safe.

Strict on create, lenient on login. A `signup` intent that finds an existing account
silently logs in. A `signin` intent that finds no account **stops** — it does not create.

### P4 — Account Linking

When a verification succeeds for a contact that already belongs to an existing identity
(matched by **email address**, not by provider UID):

1. The new provider + provider UID is linked to the existing identity.
2. Missing `display_name` / `photo_url` are backfilled from the new provider
   (non-destructive: only writes if the field is currently null).
3. The existing identity's tokens and memberships are unchanged.

---

## Response Envelopes

Two envelope formats exist. Which one a client sees depends on which server it calls.

### builtin mode — StoneScriptPHP envelope

Every response from the PHP API uses the standard StoneScriptPHP envelope:

```json
{
  "status": "ok | error",
  "message": "Human-readable description",
  "data": { ... }
}
```

Error responses carry a machine-readable code:
```json
{ "status": "error", "message": "...", "data": { "error": "identity_not_found" } }
```

### external mode — bare payloads (auth server)

The auth server returns bare JSON — no `status`/`message`/`data` wrapper:

```json
{ "success": true, "access_token": "...", "identity": { ... } }
{ "success": false, "error": "identity_not_found", "message": "..." }
```

Clients read `success` and payload fields directly, not `data.*`.

### P5 — Envelope normalization (S5)

The auth plugin (e.g. `ProgalaxyElabsAuth`) MUST unwrap the builtin envelope at the
plugin boundary so application code always sees the bare-payload shape. Dual-envelope
parsing lives in one adapter. App code is never aware of which envelope arrived.

---

## Path Prefix Convention (S1)

**Canonical prefix:** `/api/`

All auth routes use the `/api/` prefix regardless of mode:

| Namespace | Prefix | Example |
|-----------|--------|---------|
| OTP / identity / OAuth / token | `/api/auth/` | `/api/auth/login/email/otp/send` |
| Identity operations | `/api/identity/` | `/api/identity/login` |
| OAuth lifecycle | `/api/oauth/` | `/api/oauth/promote` |
| Account management | `/api/account/` | `/api/account/profile` |
| Memberships | `/api/memberships/` | `/api/memberships/invite` |
| Platform-only (external mode) | `/api/auth/` | `/api/auth/exchange` |

> **Builtin-mode implementation note:** `ExternalAuthRoutes` currently registers routes
> under `/auth/` (no `/api/`). This is a known drift from the canonical prefix.
> New builtin implementations MUST use `/api/` prefix. Existing deployments using
> `/auth/` are in non-conformance with D3 (cross-mode identical contract); migration
> path is to register both prefixes during a transition window.

---

## Common Types

```
IdentityObject {
  identity_id:   string (UUID)
  email:         string | null
  phone:         string | null
  display_name:  string | null
  photo_url:     string | null
  is_email_verified: boolean
  created_at:    ISO-8601
}

MembershipObject {
  id:           string (UUID)
  tenant_id:    string (UUID)   // authoritative key — use for all lookups
  tenant_slug:  string | null   // display-only label; nullable; NOT unique; NOT a lookup key
  tenant_name:  string          // human-readable display name
  role:         string  // "owner" | "cashier" | "manager" | platform-defined
  status:       string  // "active" | "pending" | "suspended"
  joined_at:    ISO-8601
}

TokenBundle {
  access_token:  string (JWT)
  refresh_token: string (JWT)
  token_type:    "Bearer"
  expires_in:    number (seconds)
}
```

### Field naming — no footguns (S7)

Two similarly-named fields appear in responses at different points:

| Field | Appears in | Meaning |
|-------|-----------|---------|
| `identifier_is_new` | OTP **send** response | `true` = no identity exists yet for this contact. Renamed from `is_new_identifier`. |
| `identity_was_created` | OTP **verify** + OAuth **callback** responses | `true` = an identity row was created during this request. Renamed from `is_new_identity`. |

Do not conflate. The first is about the identifier at send time; the second confirms
identity creation at verify/callback time.

---

## JWT Claims Reference (S3)

Four JWT types flow through the system. Every spec branch that reads "does the JWT
have tenant_id?" refers to this table.

| Claim | `verified_token` | identity JWT (no tenant) | identity JWT (tenant-scoped) | platform JWT |
|-------|-----------------|--------------------------|------------------------------|--------------|
| `iss` | auth server | auth server | auth server | PHP API |
| `sub` | identifier | identity_id | identity_id | identity_id |
| `identity_id` | — | ✓ | ✓ | ✓ |
| `email` | identifier (if email) | ✓ | ✓ | ✓ |
| `platform_code` | ✓ | ✓ | ✓ | ✓ |
| `tenant_id` | — | — | ✓ | ✓ |
| `tenant_slug` | — | — | ✓ | ✓ |
| `role` | — | — | membership role | — |
| `roles[]` | — | — | — | platform RBAC roles array |
| `token_type` | `"verified"` | — | — | `"platform"` |
| `purpose` | `"verified"` | — | — | — |

`POST /api/auth/exchange` requires a **tenant-scoped identity JWT** (has `tenant_id`).
It rejects identity JWTs without `tenant_id` with `invalid_identity_token`.

> **`tenant_slug` is a non-authoritative, display-only label.** It is nullable, not
> guaranteed unique, and MUST NOT be used as a lookup key, uniqueness constraint, or
> routing decision. `tenant_id` (UUID) is the only authoritative tenant key. Any code
> that writes `WHERE tenant_slug = ?` or rejects a request because a slug already exists
> reintroduces the 2026-05-28 "store name already taken" regression.
>
> **Design note for single-host platforms (e.g. medstoreapp):** `tenant_slug` has no
> functional role — there is no subdomain routing, no slug-based path routing, and
> schema names use UUID not slug. Its only use is as a display convenience (showing a
> label without a second lookup). If no platform UI renders the slug specifically,
> omitting it from the JWT is the cleaner choice. Keep it only if pretty URLs are a
> planned feature.

---

## TTL Reference (S4)

All values from source code; configurable via environment variables where noted.

| Token / code | TTL | Configurable | Notes |
|-------------|-----|-------------|-------|
| OTP code | **300 s (5 min)** | `OTP_EXPIRY_SECONDS` | Client show countdown from `expires_in` in send response |
| `verified_token` | **600 s (10 min)** | No | Only used by `/api/identity/*` non-OTP path |
| `oauth_state` handle | **3600 s (1 h)** | No | `oauth_pending_connections` table, swept every 10 min |
| `access_token` (identity JWT) | **3600 s (1 h)** | No | |
| `access_token` (platform JWT) | **3600 s (1 h)** | No | Platform API keys in `TokenExchangeRoute` |
| `refresh_token` | **2,592,000 s (30 days)** | No | Revoked on logout |

---

## Validation Precedence (S2)

Servers MUST apply checks in this deterministic order for every request:

1. **Unexpected fields** — any field not in the endpoint's defined schema → 400 `unexpected_fields`
2. **Required fields missing** — any required field absent or null → 400 `<field>_required`
3. **Field format** — invalid email format, E.164 phone, etc. → 400 `invalid_<field>`
4. **Rate limit** — too many requests for this identifier/IP → 429 `rate_limited`
5. **Identity state** — identity exists / not found / pending invite → 400 per error table

Clients MUST tolerate **unknown response fields** (Postel's law). Adding fields to a
response is non-breaking. Removing or renaming fields is breaking.

---

## 1. OTP Authentication

> **Path naming note:** `/email/` in every OTP path is a legacy naming artifact. These
> endpoints handle both email and phone identifiers. Server detects type from the
> `identifier` value: `@` → email; digit string → phone (normalised to E.164 `+<cc><number>`).
> No separate `/phone/otp/send` path exists.

### OTP Send Response Fields

| Field | Type | Meaning |
|-------|------|---------|
| `masked_identifier` | string | Display-safe: `u***@example.com` or `+91 ****1234`. Show in "code sent to…" confirmation. |
| `expires_in` | number (s) | OTP code TTL — show countdown (default 300 s). |
| `resend_after` | number (s) | Resend cooldown (default 60 s). |
| `identifier_is_new` | boolean | `true` = no identity exists for this contact yet. Login-send blocks on `true`; register-send expects it. |
| `hide_alternates` | boolean | `true` = OTP flow is active; hide OAuth provider buttons to prevent fork. Reset on "Start over". |

### OTP Verify Response Fields (D1 — Model A)

Under Model A both verify endpoints are **terminal**: they return a full `TokenBundle`
directly. The intermediate `verified_token` is gone from the OTP path. Clients never
hold a `verified_token` after OTP login or OTP register.

| Field | Type | Meaning |
|-------|------|---------|
| `access_token` | string | Identity JWT (tenant-scoped if single membership; tenant-less if multiple). |
| `refresh_token` | string | 30-day refresh token. |
| `expires_in` | number | Access token TTL in seconds (3600). |
| `identity` | IdentityObject | The authenticated (or just-created) identity. |
| `identity_was_created` | boolean | `true` = identity row was created in this request (register-verify only). |
| `membership` | MembershipObject \| null | Populated for single-tenant identities; null when `requires_tenant_selection: true`. |
| `memberships` | MembershipObject[] | Populated when user has multiple memberships. Present only when `requires_tenant_selection: true`. |
| `requires_tenant_selection` | boolean | `true` = multiple tenants; frontend must show `TenantSelectComponent`. |

---

### 1a. Send OTP — Login mode

```
POST /api/auth/login/email/otp/send
Auth: none (public)
```

Sends a code to the identifier. Fails with `identity_not_found` (HTTP 400) if no
identity exists — no OTP is sent, no identity is created.

**Strict field validation.** Any field not listed below → 400 `unexpected_fields`.

**Request:**
```json
{ "identifier": "user@example.com", "platform_code": "medstoreapp" }
```

| Field | Required | Notes |
|-------|----------|-------|
| `identifier` | ✓ | Email or phone (E.164 for phone) |
| `platform_code` | ✓ | Scopes the OTP to this platform |

**Response 200 — OTP sent (builtin envelope):**
```json
{
  "status": "ok", "message": "OTP sent",
  "data": {
    "success": true,
    "identifier_type": "email",
    "masked_identifier": "u***@example.com",
    "expires_in": 300,
    "resend_after": 60,
    "identifier_is_new": false,
    "hide_alternates": true
  }
}
```

**Response 200 — OTP sent (external auth server, bare):**
```json
{
  "success": true, "identifier_type": "email",
  "masked_identifier": "u***@example.com",
  "expires_in": 300, "resend_after": 60,
  "identifier_is_new": false, "hide_alternates": true
}
```

**Response 400 — identity not found (HTTP 400, not 200 — this is terminal at login):**
```json
{ "success": false, "error": "identity_not_found", "message": "No account found. Please sign up first." }
```

**Response 400 — unexpected fields:**
```json
{ "success": false, "error": "unexpected_fields", "fields": ["display_name"] }
```

**Response 429 — rate limited:**
```json
{ "success": false, "error": "rate_limited", "message": "...", "retry_after": 60 }
```

---

### 1b. Send OTP — Register mode

```
POST /api/auth/register/email/otp/send
Auth: none (public)
```

Sends a code. Fails with `identity_exists` (HTTP 400) if the identity already exists.
Fails with `invitation_pending` (HTTP 400) if a pending invite exists for this email
(see Invite Journey Seam §1f below).

`display_name` is **required**. Stored with the OTP row. At verify time, the identity
is created from the stored name — no separate identity-creation step.

**Strict field validation.** Any field not listed below → 400 `unexpected_fields`.

**Request:**
```json
{ "identifier": "new@example.com", "platform_code": "medstoreapp", "display_name": "Pradeep Kumar" }
```

| Field | Required | Notes |
|-------|----------|-------|
| `identifier` | ✓ | Email or phone |
| `platform_code` | ✓ | |
| `display_name` | ✓ | User's full name. Stored with the OTP row. |

**Response 200 — OTP sent:**
```json
{ "success": true, "identifier_type": "email", "masked_identifier": "n***@example.com",
  "expires_in": 300, "resend_after": 60, "identifier_is_new": true, "hide_alternates": false }
```

**Response 400 — display_name missing:**
```json
{ "success": false, "error": "display_name_required", "message": "display_name is required for registration" }
```

**Response 400 — identity already exists:**
```json
{ "success": false, "error": "identity_exists", "message": "Email already registered. Please sign in instead." }
```

---

### 1c. Verify OTP — Login mode (TERMINAL — returns full bundle)

```
POST /api/auth/login/email/otp/verify
Auth: none (public)
```

Verifies the code. **Returns a full `TokenBundle` + identity directly.** No separate
`/api/identity/login` call is needed. If the identity has multiple memberships, returns
`requires_tenant_selection: true` with the memberships list.

**Strict field validation.** Any field not listed below → 400 `unexpected_fields`.

**Request:**
```json
{ "identifier": "user@example.com", "code": "483921", "platform_code": "medstoreapp" }
```

| Field | Required |
|-------|----------|
| `identifier` | ✓ |
| `code` | ✓ |
| `platform_code` | ✓ |

**Response 200 — verified, single tenant:**
```json
{
  "success": true,
  "access_token": "<identity JWT with tenant_id>",
  "refresh_token": "<JWT>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "identity": { ...IdentityObject },
  "membership": { ...MembershipObject },
  "identity_was_created": false,
  "requires_tenant_selection": false
}
```

**Response 200 — verified, multiple tenants:**
```json
{
  "success": true,
  "access_token": "<identity JWT, no tenant_id>",
  "refresh_token": "<JWT>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "identity": { ...IdentityObject },
  "membership": null,
  "memberships": [ ...MembershipObject[] ],
  "identity_was_created": false,
  "requires_tenant_selection": true
}
```

**Response 400 — invalid code:**
```json
{ "success": false, "error": "otp_invalid", "remaining_attempts": 2, "message": "..." }
```

**Response 400 — expired:**
```json
{ "success": false, "error": "otp_expired", "message": "Code expired. Please request a new one." }
```

**Response 429 — too many wrong attempts:**
```json
{ "success": false, "error": "otp_rate_limited", "message": "..." }
```

---

### 1d. Verify OTP — Register mode (TERMINAL — returns full bundle, identity always created)

```
POST /api/auth/register/email/otp/verify
Auth: none (public)
```

Verifies the code. **Always creates the identity** from the `display_name` stored at
send time and **returns a full `TokenBundle`** immediately. No separate
`/api/identity/register` call. `identity_was_created: true` is unconditional.

The newly-created identity has no tenant membership. The frontend navigates to
provision-tenant (onboarding) next.

**Strict field validation.** Same allowed fields as login-verify: `identifier`, `code`,
`platform_code`. No additional fields accepted.

**Response 201 — identity created:**
```json
{
  "success": true,
  "access_token": "<identity JWT, no tenant_id>",
  "refresh_token": "<JWT>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "identity": { ...IdentityObject },
  "membership": null,
  "identity_was_created": true,
  "requires_tenant_selection": false
}
```

**Response 400 — identity already exists (defensive):**
```json
{ "success": false, "error": "identity_exists", "message": "An account already exists for this email." }
```

---

### 1e. Cancel Pending OTP

```
DELETE /api/auth/otp/pending?identifier=user@example.com
Auth: none (public)
```

Idempotent. Returns success even if no OTP exists.

**Response 200:**
```json
{ "success": true, "deleted": true }
```

---

### 1f. Invite Journey Seam

When `POST /api/auth/register/email/otp/send` is called and a **pending invitation**
exists for the email:

- No OTP is sent.
- No identity is created.
- The pending invite row is untouched.
- Response:

```json
{
  "success": false,
  "error": "invitation_pending",
  "message": "You have a pending invitation to join an existing store. Check your email for the invite link.",
  "invite_hint": "<store name> — check your email"
}
```

The `invite_hint` is display-safe (store name only; no token). The invite token lives
only in the already-sent email.

**This check also runs for OAuth signup intent** (§3b). An invited user who clicks
"Sign up with Google" receives `invitation_pending` in the callback response, not a
new-identity token.

---

## 2. Identity Operations  *(non-OTP paths only)*

> **OTP flow:** These endpoints are NOT called in the standard OTP signup or login flow.
> Under Model A (§1c/§1d) both OTP-verify endpoints return full token bundles directly.
>
> `/api/identity/login` and `/api/identity/register` exist for:
> - Password-based auth plugins that perform their own verification and need to exchange
>   a `verified_token` for a session.
> - Custom auth plugins that implement a non-OTP verification step.
> - Integration tests that bypass the full OTP flow.

### 2a. Identity Login

```
POST /api/identity/login
Auth: none (public — caller holds a verified_token)
```

**Request:**
```json
{ "verified_token": "<10-min JWT from a non-OTP verify step>", "platform_code": "medstoreapp" }
```

**Response 200 — found:** Full token bundle (same shape as §1c single-tenant response).

**Response 200 — not found (flow branch, HTTP 200 intentionally):**
```json
{ "success": false, "message": "identity_not_found" }
```

> HTTP 200, not 404. Caller branches on `message === "identity_not_found"` to show
> registration form. This is a mid-flow branch, not an error.

---

### 2b. Identity Register

```
POST /api/identity/register
Auth: none (public — caller holds a verified_token)
```

**Request:**
```json
{ "verified_token": "<JWT>", "display_name": "Pradeep Kumar", "platform_code": "medstoreapp" }
```

**Response 201:** Full token bundle with `identity_was_created: true`, `membership: null`.

**Response 400 — already exists:**
```json
{ "success": false, "error": "identity_exists" }
```

---

## 3. OAuth Authentication

### 3a. Initiate OAuth

```
POST /api/auth/oauth/initiate
Auth: none (public)
```

`intent` is **required**. It is threaded through the OAuth state so the callback can
apply the correct resolution matrix (P2) without asking the user again.

**Strict field validation.**

**Request:**
```json
{
  "provider":      "google",
  "platform_code": "medstoreapp",
  "redirect_uri":  "https://auth.progalaxyelabs.com/api/auth/oauth/callback",
  "intent":        "signup",
  "tenant_slug":   null
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `provider` | ✓ | `"google"` \| `"progalaxyelabs"` |
| `platform_code` | ✓ | |
| `redirect_uri` | ✓ | Must exactly match OAuth app configuration |
| `intent` | ✓ | `"signin"` \| `"signup"` |
| `tenant_slug` | — | Display hint only — used to pre-select a tenant in a multi-tenant flow. Not a routing key. Nullable. |

**Response 200:**
```json
{ "success": true, "authorization_url": "https://accounts.google.com/o/oauth2/auth?..." }
```

---

### 3b. OAuth Callback

```
POST /api/auth/oauth/callback
Auth: none (public)
```

Applies the **intent × account** resolution matrix (P2).

**"Account exists" is determined by verified email, not by provider UID (per P4).**
A user who signed up via OTP with email X and later clicks "Sign in with Google" on
email X is an *existing account* — even though no Google provider UID is linked yet.
The callback looks up the identity by email first; if found, it links the new provider
(P4 account linking) and logs in. It does NOT produce a `no_account` result.

`confirm-signup` (§3c) also re-checks by email before creating. If the email now exists
(e.g. the user registered via OTP between initiate and callback), it links instead of
creating — preventing a collision between an OTP identity and the OAuth confirmation.

| Intent | Account exists (by email) | Result |
|--------|---------------------------|--------|
| `signup` | no | Create identity → return token bundle, `identity_was_created: true` |
| `signup` | yes | Log in silently (P3 safe) + link provider (P4) → token bundle, `identity_was_created: false` |
| `signin` | yes | Log in + link provider if new (P4) → token bundle |
| `signin` | no | **Stop** → return `no_account` result with profile + `confirm_handle` (see §3c) |

**Invite check (all signup paths):** If a pending invite exists for the OAuth email,
return `invitation_pending` (see §1f) regardless of intent.

**Request:**
```json
{ "provider": "google", "code": "<auth code>", "state": "<CSRF state>" }
```

**Response 200 — login/signup success (single tenant):**
```json
{
  "success": true,
  "access_token": "<JWT>", "refresh_token": "<JWT>",
  "token_type": "Bearer", "expires_in": 3600,
  "identity": { ...IdentityObject },
  "membership": { ...MembershipObject },
  "identity_was_created": false
}
```

**Response 200 — login/signup success (multiple tenants):**
```json
{
  "success": true,
  "access_token": "<identity JWT, no tenant>", "refresh_token": "<JWT>",
  "token_type": "Bearer", "expires_in": 3600,
  "identity": { ...IdentityObject }, "membership": null,
  "memberships": [ ...MembershipObject[] ],
  "identity_was_created": false,
  "requires_tenant_selection": true
}
```

**Response 200 — OAuth signup, no tenant yet:**
```json
{
  "success": true,
  "access_token": "<identity JWT, role=oauth_pending>",
  "refresh_token": null,
  "token_type": "Bearer", "expires_in": 3600,
  "identity": { ...IdentityObject }, "membership": null,
  "identity_was_created": true,
  "oauth_pending": true,
  "oauth_state": "<opaque state — pass to provision-tenant>"
}
```

> **`oauth_pending: true`** — `refresh_token` is null (oauth_pending tokens are not
> refreshable). Frontend navigates to provision-tenant; passes `oauth_state` so
> provision-tenant can finalise the OAuth connection. Do NOT call `POST /api/oauth/promote`
> separately — provision-tenant handles it (see §5a).

**Response 200 — signin, no account (inline confirm path):**
```json
{
  "success": false,
  "error": "no_account",
  "message": "No account found for this Google address.",
  "verified_profile": {
    "email": "user@example.com",
    "display_name": "Pradeep Kumar",
    "photo_url": "https://..."
  },
  "confirm_handle": "<opaque handle, 1h TTL>"
}
```

> Frontend shows: *"No account found for user@example.com. Create one?"* with Yes/No.
> On YES → call `POST /api/auth/oauth/confirm-signup` with the `confirm_handle`.
> On NO → discard. No second Google round-trip. No URL change.

**Response 400 — invitation pending:**
```json
{ "success": false, "error": "invitation_pending", "invite_hint": "...", "message": "..." }
```

**Response 400 — CSRF state invalid:**
```json
{ "success": false, "error": "oauth_state_invalid", "message": "..." }
```

---

### 3c. OAuth Confirm Signup  *(new endpoint — inline confirm path)*

```
POST /api/auth/oauth/confirm-signup
Auth: none (public — confirm_handle is self-authenticating)
```

Creates an identity from the verified profile held server-side against the
`confirm_handle`. No second Google auth round-trip. The handle is single-use and
expires in 1 hour (same sweeper as oauth_state).

**Request:**
```json
{ "confirm_handle": "<from no_account response>" }
```

**Response 201 — identity created:**
```json
{
  "success": true,
  "access_token": "<identity JWT>", "refresh_token": null,
  "token_type": "Bearer", "expires_in": 3600,
  "identity": { ...IdentityObject }, "membership": null,
  "identity_was_created": true,
  "oauth_pending": true,
  "oauth_state": "<for provision-tenant>"
}
```

**Response 400 — handle expired or already used:**
```json
{ "success": false, "error": "oauth_pending_expired", "message": "..." }
```

---

### 3d. Promote OAuth  *(called by provision-tenant internally)*

```
POST /api/oauth/promote
Auth: Bearer <oauth_pending JWT>
```

Finalises the OAuth connection row (moves `oauth_pending_connections` → `oauth_connections`).
Under D2, identity was created at callback/confirm time; promote only commits the
connection linkage.

> **Called internally by `POST /api/auth/provision-tenant` when `oauth_state` is
> present in the request.** Platforms should not call this directly unless bypassing
> provision-tenant.

**Request:**
```json
{ "oauth_state": "<from callback or confirm-signup response>" }
```

**Response 200:**
```json
{ "success": true, "access_token": "<JWT>", ... }
```

---

### 3e. Abandon OAuth

```
DELETE /api/oauth/abandon
Auth: none (public)
```

Discards a pending OAuth connection. Idempotent.

**Request:**
```json
{ "oauth_state": "<opaque state>" }
```

**Response 200:**
```json
{ "success": true }
```

---

## 4. Token Management

### 4a. Refresh Token

```
POST /api/auth/refresh
Auth: none (public)
```

Issues a new access token from a refresh token. **When the existing JWT carries
`tenant_id`, the refreshed JWT preserves it.** `tenant_slug` is also preserved if
present, but it is a display label — `tenant_id` is the operationally significant
claim. Tenant context is
carried in the **refresh token's own claims** — preservation does not depend on the
optional `access_token` field. An implementation that reads tenant context from the
optional field reintroduces the 2026-05-28 "lost tenant_id after provision" bug
whenever the client omits it. Refresh never strips tenant context from a tenant-scoped
identity JWT. If the identity has multiple tenants and the JWT is tenant-less (no
`tenant_id`), returns `requires_tenant_selection: true` instead.

**Request:**
```json
{ "refresh_token": "<JWT>", "access_token": "<expired JWT>" }
```

> `access_token` is optional and only helps the server identify the identity in logs.
> It must never be the source of tenant context.

**Response 200 — success (tenant context preserved):**
```json
{ "success": true, "access_token": "<new JWT, same tenant_id>", "expires_in": 3600 }
```

**Response 200 — tenant selection required (tenant-less JWT):**
```json
{
  "success": true,
  "access_token": "<tenant-less JWT>", "refresh_token": "<JWT>",
  "expires_in": 3600, "identity": { ...IdentityObject },
  "memberships": [ ...MembershipObject[] ],
  "requires_tenant_selection": true
}
```

**Response 401:**
```json
{ "success": false, "error": "refresh_token_invalid", "message": "Please sign in again." }
```

#### Session continuity — platform JWT renewal

The platform JWT (issued by `POST /api/auth/exchange`) lives 1 hour. The identity
**access token** also lives 1 hour; the **refresh token** extends the session to 30
days. When the platform JWT expires mid-session:

```
1. API call returns 401 (platform JWT expired)
2. Client calls POST /api/auth/refresh  → new identity JWT (tenant_id preserved)
3. Client calls POST /api/auth/exchange → new platform JWT
4. Retry the original API call
```

The `ApiConnectionService` in `ngx-stonescriptphp-client` handles steps 2–4
automatically on 401. Platforms do not need to implement this manually.

> **The §5a warning ("do NOT call refresh right after provision-tenant") applies only
> to the provision flow.** Steady-state session refresh (step 2 above) is correct and
> expected. The distinction: provision-tenant returns a *platform JWT* directly — the
> caller should store it and use it; calling refresh at that moment discards the
> freshly-provisioned tenant context. Mid-session refresh operates on the identity JWT
> (not the platform JWT) and is always safe.

---

### 4b. Logout (Q8)

```
POST /api/auth/logout
Auth: Bearer <identity access token>
```

Revokes the **identity refresh token**. Access tokens are short-lived and cannot be
individually revoked — client discards them locally.

Platform JWTs (issued by the PHP API) have no refresh token; they expire naturally in
1h. Client discards them locally.

**Logout procedure:**
1. Call `POST /api/auth/logout` with the identity `refresh_token`
2. Discard identity JWT and platform JWT from local storage
3. Redirect to `/auth/login`

**Request:**
```json
{ "refresh_token": "<identity refresh JWT>" }
```

**Response 200:**
```json
{ "success": true }
```

---

### 4c. Platform Token Exchange  *(external mode only)*

```
POST /api/auth/exchange
Auth: Bearer <tenant-scoped identity JWT>
```

**Lives on the PHP API, not the auth server.** Validates the identity JWT via JWKS,
upserts the local tenant DB user/role rows (lazy sync), and issues a platform JWT
signed with the PHP API's own RSA keypair.

**Request body: none.** Exchange reads `tenant_id`, `identity_id`, and `role` directly
from the identity JWT. Rejects tenant-less JWTs with `invalid_identity_token`.

By the time exchange is called, the JWT already has `tenant_id` because:
- `TenantSelectComponent` calls `POST /api/auth/select-tenant` internally before
  emitting `tenantSelected` — this stamps `tenant_id` into the JWT.
- `POST /api/auth/accept-invite` returns a JWT that already contains the invited tenant.

**Called after:**
1. `TenantSelectComponent.onContinue()` fires `tenantSelected` (single or multi-tenant login)
2. `POST /api/auth/accept-invite` (invited employee)

**Not called after** `POST /api/auth/provision-tenant` — provision-tenant calls exchange
internally and returns the platform JWT directly (see §5a token contract).

**Response 200:**
```json
{
  "status": "ok", "message": "Token exchanged",
  "data": {
    "access_token": "<platform JWT>",
    "token_type": "Bearer",
    "expires_in": 3600,
    "roles": ["owner"]
  }
}
```

**Response 401:**
```json
{ "status": "error", "data": { "error": "invalid_identity_token" } }
```

---

## 5. Tenant Management

### 5a. Provision Tenant

```
POST /api/auth/provision-tenant
Auth: Bearer <identity JWT>
```

**In `external` mode (PHP API):**
1. Creates the tenant database schema and runs migrations
2. Registers the tenant + membership with the auth server
3. If `oauth_state` present in request, promotes the OAuth connection (calls §3d internally)
4. **Always** calls `POST /api/auth/exchange` internally
5. Returns the resulting **platform JWT** — not the identity JWT

**In `builtin` mode:** Same external contract; no outbound auth server call.

> **Token contract (critical):** The `access_token` in the 201 response is a
> **platform-scoped JWT** (has `tenant_id`, `roles[]` claims). Use it immediately for
> `/portal/*` calls. Do **NOT** call `POST /api/auth/refresh` or `POST /api/auth/exchange`
> after provision-tenant — refresh strips the tenant claim and exchange requires the
> auth-server token, not the platform token. Calling refresh here was the root cause
> of the 2026-05-28 "lost tenant_id after provision" incident.

> **Idempotency (S9):** Schema creation and migrations are idempotent — re-running on
> an existing schema is a no-op. If provision-tenant fails partway, the retry is safe.
> Include an `idempotency_key` in the request (platform-generated UUID, stored on the
> client) to prevent double-creates on network retry. If the same key is seen again
> within 24h, provision-tenant returns the existing tenant data (200, not 201).

**Request:**
```json
{
  "tenant_name":   "Sharma Medical Store",
  "idempotency_key": "<client-generated UUID>",
  "oauth_state":   "<from OAuth callback — if this is an OAuth signup>",
  ...platform-defined fields...
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `tenant_name` | ✓ | Display name for the new store/org |
| `idempotency_key` | ✓ | Client-generated UUID. Prevents double-creates on retry. |
| `oauth_state` | — | Present only for OAuth signup flows |

**Response 201 — tenant created:**
```json
{
  "status": "ok", "message": "Tenant created",
  "data": {
    "access_token": "<platform JWT>",
    "refresh_token": "<identity refresh JWT>",
    "token_type": "Bearer",
    "expires_in": 3600,
    "tenant": { "id": "uuid", "name": "...", "slug": "...", "db_schema": "..." },
    "identity": { ...IdentityObject },
    "membership": { ...MembershipObject }
  }
}
```

**Response 200 — idempotent replay:**
```json
{ "status": "ok", "message": "Tenant already provisioned", "data": { ...same shape as 201... } }
```

**Response 409 — tenant already exists (no idempotency_key):**
```json
{ "status": "error", "data": { "error": "tenant_already_exists" } }
```

---

### 5b. Select Tenant

```
POST /api/auth/select-tenant
Auth: Bearer <tenant-less identity JWT>
```

Stamps `tenant_id` into a new identity JWT for a selected membership. Called
**internally by `TenantSelectComponent`** before emitting `tenantSelected` — platforms
do not call this from page-level code directly.

**Request:**
```json
{ "tenant_id": "uuid", "platform_code": "medstoreapp" }
```

**Response 200:**
```json
{
  "success": true,
  "access_token": "<tenant-scoped JWT>", "refresh_token": "<JWT>",
  "token_type": "Bearer", "expires_in": 3600,
  "identity": { ...IdentityObject }, "membership": { ...MembershipObject }
}
```

**Response 400 — not a member:**
```json
{ "success": false, "error": "invalid_tenant_selection" }
```

**Response 401 — JWT already has tenant (cannot re-select):**
```json
{ "success": false, "error": "tenant_already_selected" }
```

> **Multi-tenant flow sequence (confirmed from source code):**
> ```
> 1. OTP/OAuth verify → tenant-less JWT + memberships[]
> 2. Frontend renders TenantSelectComponent
> 3. User picks tenant → TenantSelectComponent.selectAndContinue()
>    → POST /api/auth/select-tenant  (internal, transparent)
>    → JWT now has tenant_id
> 4. TenantSelectComponent emits tenantSelected
> 5. Page calls POST /api/auth/exchange (external) or navigates to dashboard (builtin)
> ```
> This sequence holds for both OTP logins and OAuth logins.

---

### 5c. Get Tenant Memberships

```
GET /api/auth/memberships?platform_code=medstoreapp
Auth: Bearer <identity JWT>
```

**Response 200:**
```json
{ "success": true, "memberships": [ ...MembershipObject[] ] }
```

---

## 6. Membership / Invite

### 6a. Invite Member

```
POST /api/memberships/invite
Auth: Bearer <platform JWT> (owner or manager)
```

**Request:**
```json
{ "email": "staff@example.com", "tenant_id": "uuid", "role": "cashier" }
```

**Response 201:**
```json
{
  "success": true,
  "invitation_id": "uuid",
  "email": "staff@example.com",
  "expires_at": "ISO-8601",
  "invite_link": "https://app.medstoreapp.in/#/auth/accept-invite?token=..."
}
```

---

### 6b. Accept Invite

```
POST /api/auth/accept-invite
Auth: none (public — invite token in body)
```

> **Path note:** In `external` mode, the auth server's native path is
> `/api/memberships/accept-invite`. The framework (`ExternalAuthRoutes`) exposes it at
> `/api/auth/accept-invite`. All spec cross-references use `/api/auth/accept-invite`
> (framework path).

Accepts a pending invite. Links the email to an existing identity if one exists (P4
account linking applies). Creates a new identity from `display_name` if none exists.
Returns a **tenant-scoped** identity JWT — accept-invite always resolves to a specific
tenant.

**After accept-invite** (external mode): call `POST /api/auth/exchange`. The returned
JWT already has `tenant_id` — no select-tenant step needed.

**Request:**
```json
{ "token": "<from invite link ?token=...>", "display_name": "Ramesh Kumar" }
```

| Field | Required | Notes |
|-------|----------|-------|
| `token` | ✓ | From `?token=` in the invite link |
| `display_name` | ✓ if new identity | Used when creating a new identity for the invitee |

**Response 200:**
```json
{
  "success": true,
  "access_token": "<tenant-scoped JWT>", "refresh_token": "<JWT>",
  "token_type": "Bearer", "expires_in": 3600,
  "identity": { ...IdentityObject }, "membership": { ...MembershipObject }
}
```

**Response 400 — expired:**
```json
{ "success": false, "error": "invite_expired" }
```

**Response 400 — already used:**
```json
{ "success": false, "error": "invite_already_used" }
```

---

### 6c. Update Membership

```
PUT /api/memberships/:id
Auth: Bearer <platform JWT> (owner or manager)
```

**Request:** `{ "role": "manager", "status": "active" }`

**Response 200:** `{ "success": true, "id": "...", "role": "...", "status": "...", "updated_at": "..." }`

---

## 7. Account Management

### 7a. Get Profile

```
GET /api/account/profile
Auth: Bearer <identity JWT>
```

**Response 200:** `{ "success": true, ...IdentityObject }`

---

### 7b. Update Profile

```
PUT /api/account/profile
Auth: Bearer <identity JWT>
```

**Request:** `{ "display_name": "Pradeep Kumar Sharma" }`

**Response 200:** `{ "success": true, "display_name": "Pradeep Kumar Sharma" }`

---

### 7c–7e. Password Reset *(emailPassword platforms only)*

```
POST /api/account/password-reset/request    { email }
POST /api/account/password-reset/confirm    { token, new_password }
POST /api/account/password                  { current_password, new_password }  (Auth required)
```

Not applicable to OTP-only platforms. OTP login IS the recovery path.

---

### 7f–7g. Account Deletion

```
POST   /api/account/delete    Auth: Bearer    → marks for deletion (60-day soft delete)
DELETE /api/account/delete    Auth: Bearer    → cancels pending deletion
```

---

## 8. Legacy Password-Based Auth  *(builtin only, emailPassword provider)*

```
POST /api/auth/register    { email, password, display_name, platform_code }
POST /api/auth/login       { email, password, platform_code }
```

New platforms should prefer OTP. Do NOT register on OTP-only platforms.
Both return the standard `TokenBundle` + `IdentityObject` + optional `MembershipObject`.

---

## 9. Route Applicability Matrix

| Route | builtin (PHP API) | external (auth server, direct) | external (PHP API only) |
|-------|------------------|-------------------------------|------------------------|
| `POST /api/auth/login/email/otp/send` | ✅ | ✅ | ❌ |
| `POST /api/auth/register/email/otp/send` | ✅ | ✅ | ❌ |
| `POST /api/auth/login/email/otp/verify` | ✅ | ✅ | ❌ |
| `POST /api/auth/register/email/otp/verify` | ✅ | ✅ | ❌ |
| `DELETE /api/auth/otp/pending` | ✅ | ✅ | ❌ |
| `POST /api/identity/login` | ✅ (non-OTP) | ✅ (non-OTP) | ❌ |
| `POST /api/identity/register` | ✅ (non-OTP) | ✅ (non-OTP) | ❌ |
| `POST /api/auth/oauth/initiate` | ✅ | ✅ | ❌ |
| `POST /api/auth/oauth/callback` | ✅ | ✅ | ❌ |
| `POST /api/auth/oauth/confirm-signup` | ✅ | ✅ | ❌ |
| `POST /api/oauth/promote` | ✅ | ✅ | ❌ |
| `DELETE /api/oauth/abandon` | ✅ | ✅ | ❌ |
| `POST /api/auth/refresh` | ✅ | ✅ | ❌ |
| `POST /api/auth/logout` | ✅ | ✅ | ❌ |
| `POST /api/auth/select-tenant` | ✅ | ✅ | ❌ |
| `GET /api/auth/memberships` | ✅ | ✅ | ❌ |
| `POST /api/auth/provision-tenant` | ✅ | ❌ | **✅ PHP API** |
| `POST /api/auth/exchange` | ❌ | ❌ | **✅ PHP API** |
| `POST /api/auth/accept-invite` | ✅ | ✅ | ❌ |
| `POST /api/memberships/invite` | ✅ | ✅ | ❌ |
| `PUT /api/memberships/:id` | ✅ | ✅ | ❌ |
| `GET /api/account/profile` | ✅ | ✅ | ❌ |
| `PUT /api/account/profile` | ✅ | ✅ | ❌ |
| `POST /api/account/password-reset/request` | ✅ | ✅ | ❌ |
| `POST /api/account/password-reset/confirm` | ✅ | ✅ | ❌ |
| `POST /api/account/password` | ✅ (emailPw) | ✅ (emailPw) | ❌ |
| `POST /api/account/delete` | ✅ | ✅ | ❌ |
| `DELETE /api/account/delete` | ✅ | ✅ | ❌ |
| `POST /api/auth/register` | ✅ (emailPw) | ❌ | ❌ |
| `POST /api/auth/login` | ✅ (emailPw) | ❌ | ❌ |

**Dead proxy rule:** In `external` mode, registering PHP API proxies for operations
that the frontend calls directly creates dead code. `ExternalAuthRoutes` MUST NOT
register any of these. Only `provision-tenant` and `exchange` belong on the PHP API.

**Migration note (unexpected_fields):** Before enabling strict field validation,
audit all clients for stray fields. The old API accepted `name_hint` on
register-send — any client still sending it will 400. Replace `name_hint` with
`display_name` at all call sites first.

---

## 10. Error Codes

| `error` value | HTTP status | Endpoint context |
|---------------|-------------|-----------------|
| `identity_not_found` | **400** at OTP login-send (terminal). **200** at `/api/identity/login` (flow branch — see §2a). HTTP status is endpoint-specific. | |
| `identity_exists` | 400 | Identity already registered. **Canonical name.** Live `otp.rs` verify handler currently emits `identity_already_exists` — code bug, must be fixed. |
| `display_name_required` | 400 | `display_name` missing from register OTP send |
| `unexpected_fields` | 400 | Request body contains undeclared fields. `fields[]` names them. |
| `invitation_pending` | 400 | Pending invite exists for this email — redirect to accept-invite |
| `no_account` | 200 | OAuth signin with no existing account — inline confirm path (§3b) |
| `otp_invalid` | 400 | Wrong code. `remaining_attempts` present. |
| `otp_expired` | 400 | Code TTL elapsed |
| `otp_rate_limited` | 429 | Too many wrong attempts |
| `otp_not_found` | 400 | No pending OTP for this identifier |
| `rate_limited` | 429 | OTP send rate limit. `retry_after` seconds present. |
| `invite_expired` | 400 | Invite token TTL elapsed |
| `invite_already_used` | 400 | Invite already accepted |
| `invite_not_found` | 404 | Token not recognised |
| `invalid_credentials` | 401 | Wrong email/password (emailPassword) |
| `refresh_token_invalid` | 401 | Refresh token revoked or expired |
| `invalid_identity_token` | 401 | Exchange: JWT invalid or missing tenant_id |
| `tenant_already_exists` | 409 | Provision: identity already has a tenant (no idempotency_key) |
| `invalid_tenant_selection` | 400 | select-tenant: not a member of this tenant |
| `tenant_already_selected` | 401 | select-tenant: JWT already has tenant_id |
| `reset_token_expired` | 400 | Password-reset token TTL elapsed |
| `reset_token_invalid` | 400 | Token not recognised or already used |
| `oauth_not_configured` | 400 | Provider not set up on server |
| `oauth_state_invalid` | 400 | CSRF state mismatch on callback |
| `oauth_pending_expired` | 400 | promote / abandon / confirm-signup: 1h TTL elapsed |

---

## 11. Deprecated / Do Not Implement

| Route | Why |
|-------|-----|
| `POST /auth/google` | → `POST /api/auth/oauth/initiate` + callback |
| `POST /user/access` | → OTP identity flow |
| `POST /user/refresh-access` | → `POST /api/auth/refresh` |
| `POST /user/signout` | → `POST /api/auth/logout` |
| `POST /user/change-password` | → `POST /api/account/password` |
| `POST /user/verify-email-code` | → `POST /api/auth/register/email/otp/verify` |
| `PUT /auth/current-tenant` | → `POST /api/auth/select-tenant` or `POST /api/auth/exchange` |
| `GET /auth/status` | Debug endpoint — remove in production |
| `GET /auth/my-tenants` | → `GET /api/auth/memberships` |
| `GET /auth/current-tenant` | JWT claims cover this |
| `GET /auth/tenant/:slug/info` | Included in login/provision responses |
| `GET /auth/database-status` | Internal infra — not part of auth API |
| `POST /auth/forgot-password` | → `POST /api/account/password-reset/request` |
| `POST /auth/reset-password` | → `POST /api/account/password-reset/confirm` |
| `POST /auth/resend-code` | → re-call the appropriate `otp/send` endpoint |
| `POST /auth/verify-email` | → `POST /api/auth/register/email/otp/verify` |
| `GET /auth/me` | → `GET /api/account/profile` |

---

## 12. Conformance Report

Evidence gathered from live code (`progalaxyelabs-auth` Rust server + `medstoreapp-platform`
PHP API). Status as of 2026-05-29.

### Conforms ✅

| Claim | Evidence |
|-------|---------|
| OTP code TTL = 300 s | `config.rs` line 257: `OTP_EXPIRY_SECONDS` default `"300"` |
| verified_token TTL = 600 s | `otp.rs` line ~957: `exp: now + 600` |
| access_token TTL = 3600 s | `jwt.rs` `ACCESS_TOKEN_TTL_SECONDS = 3600` |
| refresh_token TTL = 30 days | `jwt.rs` `REFRESH_TOKEN_TTL_SECONDS = 2_592_000` |
| oauth_state TTL = 1 h | `003_create_oauth_pending_connections.pssql` comment |
| exchange requires tenant_id in JWT | `TokenExchangeRoute.php` line ~75: `if (!$tenantId) return res_error(...)` |
| select-tenant stamps JWT | `TenantSelectComponent.ts` line 93: `this.auth.selectTenant(...)` internal call |
| provision-tenant returns platform JWT | `TokenExchangeRoute.php` called inside provision flow |
| oauth_pending refresh_token = null | `auth.rs` line ~2047: `refresh_token: None` |
| OTP send `identity_exists` error code | `error.rs` AppError::OtpSendAlreadyRegistered → `"identity_exists"` ✅ |

### Needs code change ❌

| Item | Current behaviour | Required by spec | File(s) |
|------|------------------|-----------------|---------|
| **D1** OTP verify returns `verified_token` only | `VerifyOtpResponse { verified_token, identity_created }` | Full `TokenBundle` + `IdentityObject` + `identity_was_created` | `otp.rs` handlers + `VerifyOtpResponse` struct |
| **D1** `name_hint` optional on register-send | `SendOtpRequest.name_hint: Option<String>` | Renamed to `display_name: String` (required) | `otp.rs` `SendOtpRequest` + DB function call |
| **D1** `identity_was_created` field naming | `identity_created: Option<bool>` | `identity_was_created: bool` (always present) | `otp.rs` `VerifyOtpResponse` |
| **D1** `identifier_is_new` field naming | `is_new_identifier` | `identifier_is_new` | `otp.rs` `SendOtpResponse` |
| **D2** OAuth `intent` field | Not accepted | `intent: "signin" \| "signup"` required on initiate | `auth.rs` `OAuthInitiateRequest` |
| **D2** OAuth callback matrix | Auto-creates identity for all new accounts regardless of intent | Intent × account matrix (P2) | `auth.rs` oauth callback handler |
| **D2** `confirm-signup` endpoint | Missing | New `POST /api/auth/oauth/confirm-signup` | New handler needed |
| **D2** `identity_was_created` naming in OAuth responses | `is_new_identity: bool` | `identity_was_created: bool` | `auth.rs` `LoginNewIdentityResponse` + callers |
| **S1** Path prefix | ExternalAuthRoutes: `/auth/` (no `/api/`) | `/api/auth/`, `/api/identity/`, etc. | `ExternalAuthRoutes.php` prefix default |
| **S10** `identity_already_exists` in OTP verify | `otp.rs` line 753: `message: "identity_already_exists"` | `identity_exists` (canonical) | `otp.rs` OtpVerifyMode::Register branch |
| **§1b** `invitation_pending` check | Not implemented | Block register-send when pending invite exists for email | `otp.rs` send handler + new DB function |
| **§1f** `invitation_pending` for OAuth signup | Not implemented | Same check in OAuth callback when `intent=signup` | `auth.rs` oauth callback |
| **§3c** `confirm-signup` endpoint + DB mechanics | Missing | New endpoint using `oauth_pending_connections` machinery | New handler + `auth_confirm_oauth_signup` DB function |
| **§3d** `oauth/promote` semantics | Currently creates identity | Under D2, identity created at callback; promote only finalises connection row | `auth.rs` promote handler + `auth_promote_oauth_pending` SQL |
| **§5a** `idempotency_key` on provision-tenant | Not present | Required field | `MedstoreProvisionTenantRoute.php` + auth server `register-tenant` |
| **Q3** Multi-tenant after OAuth login | Not verified in code | select-tenant → exchange sequence should hold | Verify in e2e test |

### Spec decisions resolved (open questions answered)

| Q | Decision |
|---|---------|
| Q1 | OTP verify returns **full bundle including `refresh_token`**. `verified_token` disappears from the OTP path. It remains an internal type for `/api/identity/*` non-OTP calls. |
| Q2 | `signup + existing account` OAuth → **silent login** (P3: logging in an existing user is always safe). `identity_was_created: false` in response. No visible notice required at MVP. |
| Q3 | Multi-tenant after OAuth login → same select-tenant → exchange sequence. **Confirmed** by `TenantSelectComponent` source code. |
| Q4 | Phone OTP → identical flow. `display_name` required at register-send for phone too. Endpoint detects type from identifier format. |
| Q5 | `invitation_pending` check runs for OAuth signup intent at callback, same as OTP register-send. |
| Q6 | Abandoned `confirm_handle` TTL = 1h (same sweeper as `oauth_pending_connections`). If expired, new Google auth required. |
| Q7 | `POST /api/oauth/promote` still exists under D2, but scope changes: identity is now created at callback/confirm time; promote only finalises the OAuth connection row. |
| Q8 | Logout: call `POST /api/auth/logout` with identity `refresh_token`. Platform JWT has no refresh token — discard locally. Platform JWT expires in 1h naturally. |
