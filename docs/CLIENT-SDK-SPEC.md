# StoneScriptPHP — Generated Client SDK Specification

**Status:** DRAFT — awaiting review  
**Author:** ProGalaxy eLabs dev team  
**Date:** 2026-06-13  
**Scope:** `php stone generate client` command, URL route conventions, relationship with `ngx-stonescriptphp-client`

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
9. [Tenant Scoping — setStore()](#9-tenant-scoping--setstore)
10. [Request / Response Contract](#10-request--response-contract)
11. [Error Handling](#11-error-handling)
12. [ngx-stonescriptphp-client — Angular Wrapper Role](#12-ngx-stonescriptphp-client--angular-wrapper-role)
13. [Migration Path](#13-migration-path)
14. [Open Questions](#14-open-questions)

---

## 1. Problem Statement

The current `php stone generate client` command emits a TypeScript `ApiClient` class that:

1. **Depends on `ngx-stonescriptphp-client`** for its HTTP connection (`ApiConnectionService`). This means:
   - The generated client cannot be used outside Angular.
   - npm installs a nested copy of `ngx-stonescriptphp-client` inside the generated package, causing TypeScript to see two separate declarations of the same class — breaking `new ApiClient(connection)` with TS2345.
   - The generated client is useless if the consumer doesn't also set up the Angular library.

2. **Groups all routes under a single property named after the business entity** (e.g. `api.stores.*`). A platform with 200 routes ends up with 200 methods under one namespace. `api.stores.postPortalInventoryItems(storeId, data)` communicates neither the module nor the intent — `storeId` leaks into every call signature even though the consumer sets their store once per session.

3. **Delegates token management to the Angular library**, which then offers configurable storage strategies (localStorage, IndexedDB, cookie). This is unnecessary complexity — the choice is not meaningful for the target use case and creates decision fatigue and divergent implementations across platforms.

4. **Does not own the URL convention** — the generated URL paths (e.g. `/stores/{storeId}/portal/inventory/items`) were driven by a PHP route change rather than a considered client SDK design. The service discriminator (`portal`) ends up buried after the tenant scoper (`stores/{storeId}`), inverting the natural hierarchy.

The net effect: every platform that consumes the generated client needs a hand-written Angular service (`ApiClientService`) that re-maps all 200 generated methods back to raw `apiConnection.get/post` calls. The generator is producing dead weight.

---

## 2. Design Goals

| Goal | Description |
|------|-------------|
| **Self-contained** | The generated package has zero runtime dependencies. No Angular, no axios, no external HTTP lib. |
| **Framework-agnostic** | The generated `ApiClient` works in Angular, React, Vue, Svelte, or vanilla JS. |
| **Module-grouped** | Routes are grouped by functional module, not by business entity: `api.inventory.create()`, `api.billing.createBill()`, `api.auth.login()`. |
| **Store-context-once** | The consumer calls `api.setStore(id)` once after tenant selection. All subsequent portal calls use that context silently. No storeId in every method signature. |
| **Frozen token management** | localStorage, fixed key names, no choices. The framework decides. |
| **Angular wrapper is thin** | `ngx-stonescriptphp-client` wraps the generated client for Angular-specific concerns (signals, route guards, UI components). It does not own HTTP or tokens. |
| **Source of truth is the API** | The generated client mirrors `routes.php` exactly. When routes change, regenerate the client. No hand-maintained URL strings anywhere in Angular code. |

---

## 3. Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│  Generated Package  (docker/api/client/{service}/)               │
│  Produced by: php stone generate client                          │
│                                                                  │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │  MinimalHttp    │  │  TokenStore     │  │  DTOs / types   │ │
│  │  (fetch-based)  │  │  (localStorage) │  │  (from PHP DTOs)│ │
│  └────────┬────────┘  └────────┬────────┘  └─────────────────┘ │
│           │                    │                                  │
│  ┌────────▼────────────────────▼───────────────────────────────┐ │
│  │  ApiClient                                                   │ │
│  │  - setStore(id)                                              │ │
│  │  - auth.login()  auth.myTenants()  auth.selectTenant()      │ │
│  │  - inventory.list()  inventory.create()  inventory.update()  │ │
│  │  - billing.createBill()  billing.list()                     │ │
│  │  - reports.stock()  reports.salesDaily()                    │ │
│  │  - settings.getBusiness()  settings.update()                │ │
│  │  - onboarding.status()  onboarding.bulkCreate()             │ │
│  │  - ...one readonly property per module, one fn per route    │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  Zero external dependencies.  Plain Promises.  No Angular.       │
└──────────────────────────────┬───────────────────────────────────┘
                               │  wraps
┌──────────────────────────────▼───────────────────────────────────┐
│  ngx-stonescriptphp-client  (Angular library)                    │
│                                                                  │
│  - provideNgxStoneScriptPhpClient(apiUrl)                        │
│    → constructs ApiClient, provides via Angular DI               │
│  - AuthService: isAuthenticated signal, currentUser signal       │
│  - Route guards (reads ApiClient token state)                    │
│  - <lib-tenant-login> component                                  │
│  - Error interceptor → maps ApiError to user-facing message      │
│  - Loading/skeleton directives                                   │
│                                                                  │
│  Pure Angular concerns.  No HTTP, no tokens, no URL strings.     │
└──────────────────────────────────────────────────────────────────┘
```

---

## 4. Generated Package Structure

`php stone generate client` writes to `docker/api/client/{service}/`:

```
client/
└── portal/
    ├── package.json          # zero dependencies, no peerDependencies
    ├── tsconfig.json
    ├── src/
    │   ├── http.ts           # MinimalHttp — emitted verbatim (not generated)
    │   ├── tokens.ts         # TokenStore — emitted verbatim (not generated)
    │   ├── errors.ts         # ApiError class — emitted verbatim
    │   ├── types.ts          # All DTOs — generated from PHP DTO classes
    │   └── client.ts         # ApiClient — generated from routes.php
    └── dist/
        ├── index.js
        └── index.d.ts
```

`package.json`:

```json
{
  "name": "@stonescript/api-client",
  "version": "0.0.0",
  "description": "Auto-generated API client for medstoreapp portal",
  "main": "dist/index.js",
  "types": "dist/index.d.ts",
  "dependencies": {},
  "peerDependencies": {},
  "devDependencies": {
    "typescript": "^5.0.0"
  }
}
```

No external runtime dependencies. The `file:` reference in the Angular service's `package.json` points here.

---

## 5. MinimalHttp — Generated Connection Layer

Emitted verbatim (not generated from routes). Included in every client output.

```typescript
// src/http.ts — emitted verbatim by php stone generate client

import { TokenStore } from './tokens';

export interface HttpParams {
  [key: string]: string | number | boolean | null | undefined;
}

export class MinimalHttp {
  constructor(
    private readonly baseUrl: string,
    private readonly tokens: TokenStore,
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

  async delete<T = unknown>(path: string): Promise<T> {
    return this.request<T>('DELETE', path);
  }

  private async request<T>(
    method: string,
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

    const res = await fetch(url.toString(), {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    // 401 → attempt one token refresh, then retry
    if (res.status === 401 && !isRetry) {
      const refreshed = await this.attemptRefresh();
      if (refreshed) {
        return this.request<T>(method, path, body, params, true);
      }
      this.tokens.clear();
      throw new ApiError('Session expired. Please log in again.', 401, null);
    }

    const data = await res.json();

    // StoneScriptPHP error envelope: HTTP 200 with status "error" or "not ok"
    if (data?.status && data.status !== 'ok') {
      throw new ApiError(data.message ?? 'Request failed', res.status, data);
    }

    // Unwrap the data envelope — callers receive data.data, not the full envelope
    return (data?.data ?? data) as T;
  }

  private async attemptRefresh(): Promise<boolean> {
    const refresh = this.tokens.getRefresh();
    if (!refresh) return false;

    try {
      const res = await fetch(this.baseUrl + this.refreshEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refresh }),
      });

      if (!res.ok) return false;

      const data = await res.json();
      if (data?.access_token) {
        this.tokens.set(data.access_token);
        if (data?.refresh_token) this.tokens.setRefresh(data.refresh_token);
        return true;
      }
    } catch {
      // network failure during refresh → treat as not refreshed
    }

    return false;
  }
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly httpStatus: number,
    public readonly response: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
```

---

## 6. TokenStore — Frozen Token Management

Emitted verbatim. Not configurable. Not injectable. The framework decides the storage strategy.

```typescript
// src/tokens.ts — emitted verbatim by php stone generate client

const ACCESS_KEY  = 'ssp_access_token';
const REFRESH_KEY = 'ssp_refresh_token';

export class TokenStore {
  get(): string | null {
    return typeof localStorage !== 'undefined'
      ? localStorage.getItem(ACCESS_KEY)
      : null;
  }

  set(token: string): void {
    localStorage.setItem(ACCESS_KEY, token);
  }

  getRefresh(): string | null {
    return typeof localStorage !== 'undefined'
      ? localStorage.getItem(REFRESH_KEY)
      : null;
  }

  setRefresh(token: string): void {
    localStorage.setItem(REFRESH_KEY, token);
  }

  clear(): void {
    localStorage.removeItem(ACCESS_KEY);
    localStorage.removeItem(REFRESH_KEY);
  }

  hasToken(): boolean {
    return !!this.get();
  }
}
```

**Rationale for freezing:**
- The target use case is a browser SPA. localStorage is appropriate.
- IndexedDB is async and overkill for a JWT string.
- HttpOnly cookies require server-side session handling — a different auth model entirely.
- Making storage configurable adds complexity with no practical benefit. Platforms that genuinely need a different strategy can fork the generated output.

---

## 7. ApiClient — Module-Grouped Route Methods

The generator reads `routes.php` and groups routes by their functional path segment. The grouping key is the first non-scoping path segment after the service prefix and storeId.

**Grouping rule:**

| Route path | Module group |
|------------|-------------|
| `/portal/store/{storeId}/inventory/*` | `inventory` |
| `/portal/store/{storeId}/billing/*` | `billing` |
| `/portal/store/{storeId}/reports/*` | `reports` |
| `/portal/store/{storeId}/settings/*` | `settings` |
| `/portal/store/{storeId}/users/*` | `users` |
| `/portal/store/{storeId}/onboarding/*` | `onboarding` |
| `/portal/store/{storeId}/stock-reconciliation/*` | `stockReconciliation` |
| `/portal/store/{storeId}/payments/*` | `payments` |
| `/api/auth/*` | `auth` |
| `/api/devices/*` | `devices` |

**Generated output shape:**

```typescript
// src/client.ts — generated from routes.php

import { MinimalHttp } from './http';
import { TokenStore }  from './tokens';
import * as T from './types';

export class ApiClient {
  readonly tokens: TokenStore;        // exposed so ngx wrapper can read auth state
  private readonly http: MinimalHttp;
  private _storeId: string | number | null = null;

  constructor(baseUrl: string) {
    this.tokens = new TokenStore();
    this.http   = new MinimalHttp(baseUrl, this.tokens);
  }

  /**
   * Set the active store (tenant) context.
   * Must be called once after the user selects a tenant.
   * All portal module calls will use this storeId in the URL path.
   */
  setStore(id: string | number): this {
    this._storeId = id;
    return this;
  }

  private get s(): string {
    if (this._storeId === null) {
      throw new Error('[ApiClient] Store context not set. Call setStore(id) after tenant selection.');
    }
    return `/portal/store/${this._storeId}`;
  }

  // ─────────────────────────────────────────────────────────────
  // auth — no store context required
  // ─────────────────────────────────────────────────────────────
  readonly auth = {
    login:          (d: T.LoginRequest)           => this.http.post<T.LoginResponse>('/api/auth/login', d),
    myTenants:      ()                            => this.http.get<T.Tenant[]>('/api/auth/my-tenants'),
    selectTenant:   (d: T.SelectTenantRequest)    => this.http.post<T.TokenResponse>('/api/auth/select-tenant', d),
    registerTenant: (d: T.RegisterTenantRequest)  => this.http.post<T.Tenant>('/api/auth/register-tenant', d),
    currentTenant:  ()                            => this.http.get<T.Tenant>('/api/auth/current-tenant'),
    profile:        ()                            => this.http.get<T.UserProfile>('/api/auth/profile'),
    provisionTenant:(d: T.ProvisionTenantRequest) => this.http.post<T.Tenant>('/api/auth/provision-tenant', d),
    acceptInvite:   (d: T.AcceptInviteRequest)    => this.http.post<T.TokenResponse>('/api/auth/accept-invite', d),
  };

  // ─────────────────────────────────────────────────────────────
  // inventory — store context required
  // ─────────────────────────────────────────────────────────────
  readonly inventory = {
    list:        (p?: T.ListItemsParams)          => this.http.get<T.InventoryListResponse>(`${this.s}/inventory/items`, p),
    create:      (d: T.CreateItemRequest)         => this.http.post<T.Item>(`${this.s}/inventory/items`, d),
    update:      (id: number, d: T.UpdateItemRequest) => this.http.put<T.Item>(`${this.s}/inventory/item/${id}`, d),
    delete:      (id: number)                     => this.http.delete(`${this.s}/inventory/item/${id}`),
    getById:     (id: number)                     => this.http.get<T.ItemDetail>(`${this.s}/inventory/item/${id}/details`),
    search:      (p: T.SearchItemsParams)         => this.http.get<T.Item[]>(`${this.s}/inventory/items/search`, p),
    lowStock:    ()                               => this.http.get<T.Item[]>(`${this.s}/inventory/low-stock`),
    expiring:    ()                               => this.http.get<T.Item[]>(`${this.s}/inventory/items/expiring`),
    vendors:     ()                               => this.http.get<T.Vendor[]>(`${this.s}/inventory/vendors`),
    createVendor:(d: T.CreateVendorRequest)       => this.http.post<T.Vendor>(`${this.s}/inventory/vendors`, d),
    distributors:()                               => this.http.get<T.Distributor[]>(`${this.s}/inventory/distributors`),
    createDistributor: (d: T.CreateDistributorRequest) => this.http.post<T.Distributor>(`${this.s}/inventory/distributors`, d),
    manufacturers:()                              => this.http.get<T.Manufacturer[]>(`${this.s}/inventory/manufacturers`),
    locations:   ()                               => this.http.get<T.Location[]>(`${this.s}/inventory/item_locations`),
    createItemPrice: (d: T.CreateItemPriceRequest)=> this.http.post<T.ItemPrice>(`${this.s}/inventory/item_prices`, d),
    adjust:      (id: number, d: T.AdjustRequest) => this.http.post(`${this.s}/inventory/item/${id}/adjust`, d),
  };

  // ─────────────────────────────────────────────────────────────
  // billing — store context required
  // ─────────────────────────────────────────────────────────────
  readonly billing = {
    list:        ()                               => this.http.get<T.BillListResponse>(`${this.s}/billing/bills`),
    create:      (d: T.CreateBillRequest)         => this.http.post<T.Bill>(`${this.s}/billing/bills`, d),
    getById:     (id: number)                     => this.http.get<T.BillDetail>(`${this.s}/billing/bills/${id}`),
    update:      (id: number, d: T.UpdateBillRequest) => this.http.put<T.Bill>(`${this.s}/billing/bills/${id}`, d),
    delete:      (id: number)                     => this.http.delete(`${this.s}/billing/bills/${id}`),
    dailySummary:()                               => this.http.get(`${this.s}/billing/daily-summary`),
    reserve:     (d: T.BillingReserveRequest)     => this.http.post(`${this.s}/billing/reserve`, d),
    clearReserve:(billNumber: string, itemPriceId?: number) =>
                   this.http.delete(`${this.s}/billing/reserve/${billNumber}${itemPriceId ? '/' + itemPriceId : ''}`),
  };

  // ─────────────────────────────────────────────────────────────
  // invoices (distributor purchase invoices)
  // ─────────────────────────────────────────────────────────────
  readonly invoices = {
    list:        (p?: T.ListInvoicesParams)       => this.http.get<T.InvoiceListResponse>(`${this.s}/invoices`, p),
    create:      (d: T.CreateInvoiceRequest)      => this.http.post<T.Invoice>(`${this.s}/invoices`, d),
    getById:     (id: number)                     => this.http.get<T.InvoiceDetail>(`${this.s}/invoices/${id}`),
    update:      (id: number, d: T.UpdateInvoiceRequest) => this.http.put<T.Invoice>(`${this.s}/invoices/${id}`, d),
    delete:      (id: number)                     => this.http.delete(`${this.s}/invoices/${id}`),
    attachments: (id: number)                     => this.http.get<T.Attachment[]>(`${this.s}/invoices/${id}/attachments`),
    addAttachment: (id: number, d: T.AddAttachmentRequest) => this.http.post(`${this.s}/invoices/${id}/attachments`, d),
    deleteAttachment: (id: number, attachmentId: number)   => this.http.delete(`${this.s}/invoices/${id}/attachments/${attachmentId}`),
    submitDistributor: (d: T.DistributorInvoiceRequest) => this.http.post(`${this.s}/distributor-invoice/submit`, d),
    updateDistributor: (id: number, d: T.DistributorInvoiceRequest) => this.http.put(`${this.s}/distributor-invoice/${id}`, d),
    getDistributorById:(id: number)               => this.http.get(`${this.s}/distributor-invoice/${id}`),
  };

  // ─────────────────────────────────────────────────────────────
  // dashboard
  // ─────────────────────────────────────────────────────────────
  readonly dashboard = {
    summary:     ()                               => this.http.get<T.DashboardResponse>(`${this.s}/dashboard`),
    stats:       ()                               => this.http.get<T.DashboardStats>(`${this.s}/dashboard/stats`),
    activities:  (p?: T.ActivitiesParams)         => this.http.get(`${this.s}/dashboard/activities`, p),
    sales:       (p?: T.SalesParams)              => this.http.get(`${this.s}/dashboard/sales`, p),
  };

  // ─────────────────────────────────────────────────────────────
  // reports
  // ─────────────────────────────────────────────────────────────
  readonly reports = {
    stock:           ()                           => this.http.get<T.StockReportResponse>(`${this.s}/reports/stock`),
    salesDaily:      (date?: string)              => this.http.get(`${this.s}/reports/sales/daily`, { date }),
    salesMonthly:    (month?: number, year?: number) => this.http.get(`${this.s}/reports/sales/monthly`, { month, year }),
    cashDaily:       (date?: string)              => this.http.get(`${this.s}/reports/cash/daily`, { date }),
    cashMonthly:     (month?: number, year?: number) => this.http.get(`${this.s}/reports/cash/monthly`, { month, year }),
    profitLossDaily: (date?: string)              => this.http.get(`${this.s}/reports/profit-loss/daily`, { date }),
    profitLossMonthly:(month?: number, year?: number) => this.http.get(`${this.s}/reports/profit-loss/monthly`, { month, year }),
    caPackage:       (month: number, year: number, format: 'json' | 'pdf') =>
                       this.http.get(`${this.s}/reports/ca-package`, { month, year, format }),
  };

  // ─────────────────────────────────────────────────────────────
  // settings
  // ─────────────────────────────────────────────────────────────
  readonly settings = {
    getBusiness:   ()                             => this.http.get<T.BusinessSettings>(`${this.s}/settings/business`),
    updateBusiness:(d: T.UpdateBusinessSettings)  => this.http.put<T.BusinessSettings>(`${this.s}/settings/business`, d),
    list:          ()                             => this.http.get(`${this.s}/settings`),
    getByKey:      (key: string)                  => this.http.get(`${this.s}/settings/${key}`),
    set:           (d: T.SetSettingRequest)       => this.http.post(`${this.s}/settings`, d),
    delete:        (key: string)                  => this.http.delete(`${this.s}/settings/${key}`),
  };

  // ─────────────────────────────────────────────────────────────
  // users (store staff / members)
  // ─────────────────────────────────────────────────────────────
  readonly users = {
    list:          ()                             => this.http.get<T.User[]>(`${this.s}/users`),
    invite:        (d: T.InviteUserRequest)       => this.http.post<T.User>(`${this.s}/users`, d),
    getById:       (id: number)                   => this.http.get<T.User>(`${this.s}/users/${id}`),
    update:        (id: number, d: T.UpdateUserRequest) => this.http.put<T.User>(`${this.s}/users/${id}`, d),
    delete:        (id: number)                   => this.http.delete(`${this.s}/users/${id}`),
    profile:       ()                             => this.http.get<T.UserProfile>(`${this.s}/users/profile`),
    updateProfile: (d: T.UpdateProfileRequest)    => this.http.put<T.UserProfile>(`${this.s}/users/profile`, d),
  };

  // ─────────────────────────────────────────────────────────────
  // onboarding
  // ─────────────────────────────────────────────────────────────
  readonly onboarding = {
    status:        ()                             => this.http.get(`${this.s}/onboarding/status`),
    medicineCategories: ()                        => this.http.get(`${this.s}/onboarding/medicines/categories`),
    searchMedicines:(p: T.SearchMedicinesParams)  => this.http.get(`${this.s}/onboarding/medicines`, p),
    bulkCreate:    (d: T.OnboardingBulkCreateRequest) => this.http.post(`${this.s}/onboarding/bulk-create`, d),
    establishment: (d: T.OnboardingEstablishmentRequest) => this.http.post(`${this.s}/onboarding/establishment`, d),
  };

  // ─────────────────────────────────────────────────────────────
  // stockReconciliation
  // ─────────────────────────────────────────────────────────────
  readonly stockReconciliation = {
    list:          ()                             => this.http.get<T.StockReconciliationListResponse>(`${this.s}/stock-reconciliation`),
    create:        (d: T.CreateStockReconciliationRequest) => this.http.post(`${this.s}/stock-reconciliation`, d),
    getById:       (id: number)                   => this.http.get(`${this.s}/stock-reconciliation/${id}`),
    update:        (id: number, d: T.UpdateStockReconciliationRequest) => this.http.put(`${this.s}/stock-reconciliation/${id}`, d),
    delete:        (id: number)                   => this.http.delete(`${this.s}/stock-reconciliation/${id}`),
    summary:       (id: number)                   => this.http.get(`${this.s}/stock-reconciliation/${id}/summary`),
    start:         (id: number)                   => this.http.post(`${this.s}/stock-reconciliation/${id}/start`, {}),
    approve:       (id: number)                   => this.http.post(`${this.s}/stock-reconciliation/${id}/approve`, {}),
    complete:      (id: number)                   => this.http.post(`${this.s}/stock-reconciliation/${id}/complete`, {}),
  };

  // ─────────────────────────────────────────────────────────────
  // payments
  // ─────────────────────────────────────────────────────────────
  readonly payments = {
    list:          ()                             => this.http.get(`${this.s}/payments`),
    create:        (d: T.CreatePaymentRequest)    => this.http.post(`${this.s}/payments`, d),
    getById:       (id: number)                   => this.http.get(`${this.s}/payments/${id}`),
    update:        (id: number, d: T.UpdatePaymentRequest) => this.http.put(`${this.s}/payments/${id}`, d),
    delete:        (id: number)                   => this.http.delete(`${this.s}/payments/${id}`),
    byReference:   (type: string, id: number)     => this.http.get(`${this.s}/payments/reference/${type}/${id}`),
  };

  // ─────────────────────────────────────────────────────────────
  // subscription (cross-cutting — no storeId in URL, uses auth token)
  // ─────────────────────────────────────────────────────────────
  readonly subscription = {
    status:        ()                             => this.http.get('/subscription/status'),
    plans:         ()                             => this.http.get('/subscription/plans'),
    createOrder:   (d: T.CreateOrderRequest)      => this.http.post('/subscription/create-order', d),
    verifyPayment: (d: T.VerifyPaymentRequest)    => this.http.post('/subscription/verify-payment', d),
  };

  // ─────────────────────────────────────────────────────────────
  // utility (dropdown data, states — shared lookups)
  // ─────────────────────────────────────────────────────────────
  readonly utility = {
    dropdownData:  (table: string, field: string, p?: Record<string, string>) =>
                     this.http.get(`${this.s}/dropdown-data/${table}/${field}`, p),
    states:        ()                             => this.http.get('/portal/states'),
    medicines:     (q: string)                    => this.http.get(`${this.s}/medicines/search`, { q }),
  };
}
```

---

## 8. URL Route Convention

### Current (problematic)

```
GET  /stores/{storeId}/portal/inventory/items
POST /stores/{storeId}/portal/billing/bills
GET  /api/auth/my-tenants
```

`stores/{storeId}` precedes the service discriminator (`portal`). The API router must read the path deeply before knowing which service context it is in.

### Proposed

```
GET  /portal/store/{storeId}/inventory/items
POST /portal/store/{storeId}/billing/bills
GET  /api/auth/my-tenants
```

**Hierarchy:**

```
/{service}/{storeId-scope?}/{module}/{resource}

/portal/store/42/inventory/items    → portal service, store 42, inventory module
/admin/inventory/items              → admin service (no store scope — admin sees all)
/api/auth/login                     → auth service (platform-wide, no store)
/public/medicines/search            → public service (no auth)
```

**Why this order:**

1. **Service first** (`portal`, `admin`, `public`, `api`) — the API router's first decision is which handler group owns this request. Early return on wrong service.
2. **Tenant scope second** (`store/{storeId}`) — within portal context, scope to a specific store. The PHP router extracts `storeId` here and passes it to the gateway as the tenant context.
3. **Module third** (`inventory`, `billing`, etc.) — functional grouping within the service+tenant.
4. **Resource last** (`items`, `bills`, `items/{id}`) — specific resource or sub-resource.

### PHP route definition pattern

```php
// routes.php

// Portal routes — store-scoped
$router->group('/portal/store/{storeId}', function() {
    $router->get('/inventory/items',           ListInventoryRoute::class);
    $router->post('/inventory/items',          CreateItemRoute::class);
    $router->put('/inventory/item/{id}',       UpdateItemRoute::class);
    $router->delete('/inventory/item/{id}',    DeleteItemRoute::class);
    $router->post('/billing/bills',            CreateBillRoute::class);
    // ...
});

// Auth routes — no store scope
$router->group('/api/auth', function() {
    $router->post('/login',                    AuthLoginRoute::class);
    $router->get('/my-tenants',               GetMyTenantsRoute::class);
    // ...
});
```

The `{storeId}` path parameter is extracted by the router and made available to every portal route handler as `$this->storeId` — injected by the framework, not read from the request body.

---

## 9. Tenant Scoping — setStore()

```typescript
// Consumer code (Angular component or any JS)

const api = new ApiClient('https://api.medstoreapp.in');

// After OTP login:
const tenants = await api.auth.myTenants();
await api.auth.selectTenant({ tenant_id: tenants[0].id });

// After tenant selection — set context once:
api.setStore(tenants[0].id);

// All subsequent portal calls use store context silently:
const items    = await api.inventory.list();          // GET /portal/store/42/inventory/items
const bill     = await api.billing.create(billData);  // POST /portal/store/42/billing/bills
const dashboard= await api.dashboard.summary();       // GET /portal/store/42/dashboard
```

No storeId in any call signature after `setStore()`. The Angular library calls `api.setStore(id)` inside its `AuthService.selectTenant()` method — the Angular developer never touches it directly.

**Edge case:** If a portal method is called before `setStore()`, `ApiClient` throws synchronously:

```
Error: [ApiClient] Store context not set. Call setStore(id) after tenant selection.
```

This is a programming error, not a runtime error — caught immediately in development.

---

## 10. Request / Response Contract

The generated DTOs mirror the PHP DTO classes. The generator reads `src/App/DTO/*.php` and emits TypeScript interfaces.

All API responses follow the StoneScriptPHP envelope:

```json
{ "status": "ok", "message": "", "data": { ... } }
{ "status": "error", "message": "Human-readable error", "data": null }
```

`MinimalHttp` unwraps this envelope. Callers receive `data` directly on success and an `ApiError` on failure. Callers never see the envelope.

---

## 11. Error Handling

```typescript
// Consumer code
try {
  const bill = await api.billing.create(billData);
  // bill is the unwrapped data object
} catch (e) {
  if (e instanceof ApiError) {
    console.error(e.message);       // "Item out of stock"
    console.error(e.httpStatus);    // 200 (StoneScriptPHP error envelope) or 400/401/503
    console.error(e.response);      // full response body for debugging
  }
}
```

The Angular library (`ngx-stonescriptphp-client`) catches `ApiError` at a higher level and maps it to user-facing messages, toasts, or retry prompts. The generated client does not depend on any UI framework to do this.

---

## 12. ngx-stonescriptphp-client — Angular Wrapper Role

After this redesign, the Angular library's responsibility shrinks significantly and becomes clearly defined:

**Keeps:**
- `provideNgxStoneScriptPhpClient(apiUrl)` — constructs `ApiClient`, registers it in Angular DI
- `AuthService` — wraps `api.auth.*` calls, manages `isAuthenticated` and `currentUser` signals, calls `api.setStore(id)` after tenant selection
- Route guards (`authGuard`, `tenantGuard`)
- `<lib-tenant-login>` component — the login/OTP/OAuth UI
- Error handler — catches `ApiError`, maps to user-facing toast/alert
- Loading state directives (optional)

**Removes:**
- `ApiConnectionService` — no longer needed; `MinimalHttp` replaces it
- Token management code — lives in generated `TokenStore`
- HTTP interceptors — `MinimalHttp` handles auth headers and refresh
- Storage strategy configuration — frozen in `TokenStore`

**The test:** if you can describe what `ngx-stonescriptphp-client` does without mentioning HTTP, tokens, or URL paths — the boundary is correct.

---

## 13. Migration Path

### Phase 1 — Generator (framework change)

1. Update `php stone generate client` to emit `MinimalHttp`, `TokenStore`, `ApiError` verbatim into every generated package.
2. Update the grouping logic: group by functional module path segment, not by business entity.
3. Add `setStore(id)` to `ApiClient`.
4. Remove the `ngx-stonescriptphp-client` dependency from the generated `package.json`.
5. Update all generated method names to follow `module.verb(params)` convention (no `Portal` prefix, no HTTP verb prefix).

### Phase 2 — URL restructure (all platforms)

1. Update PHP route definitions from `/stores/{storeId}/portal/*` to `/portal/store/{storeId}/*`.
2. Regenerate clients for all 11 platforms.
3. Update nginx routing if the service prefix (`/portal`, `/admin`) is handled at the nginx level rather than in PHP.

### Phase 3 — Angular library slim-down

1. Remove `ApiConnectionService` from `ngx-stonescriptphp-client`.
2. Remove token management code.
3. Update `provideNgxStoneScriptPhpClient` to accept `apiUrl: string` instead of a connection config object — it constructs `ApiClient(apiUrl)` internally.
4. Update `AuthService` to call `api.setStore(id)` after tenant selection.
5. Remove storage strategy options from `provideNgxStoneScriptPhpClient` config.

### Phase 4 — Platform updates (all 11)

1. Remove `ApiClientService` hand-written wrappers from all platforms.
2. Components inject `ApiClient` directly (via Angular DI) and call `api.inventory.list()` etc.
3. Or: thin platform-specific service that wraps `ApiClient` for business logic (grouping, caching) — not for URL mapping.

### Short-term (unblock medstoreapp QC)

Revert medstoreapp URL structure to `/portal/*` (no storeId in path), re-run `php stone generate client` with the old generator so the old grouped interface comes back, medstoreapp portal compiles. Run QC. Then do phases 1–4 as framework work.

---

## 14. Open Questions

1. **SSR / Server-side rendering**: `TokenStore` uses `localStorage` which is not available in Node.js. Should `TokenStore` emit an SSR guard (`typeof localStorage !== 'undefined'`), or is SSR out of scope for StoneScriptPHP platforms?

2. **Multiple stores**: Can a user switch stores without a page reload? If yes, `setStore(id)` must be callable multiple times. Is this the intended UX or does store switching always trigger a full reload?

3. **Admin service**: Admin routes don't have a storeId scope. Should `ApiClient` have a separate `admin.*` module group without the `this.s` prefix, or is admin a separate generated client entirely?

4. **Offline / PWA**: Some platforms (medstoreapp portal-offline) need offline support. Does the frozen `TokenStore` (localStorage) conflict with PWA service worker strategies? Or is offline handled at the PWA layer independently of the client?

5. **Generator phasing**: Should Phase 1 (generator) and Phase 2 (URL restructure) ship together (one breaking change across all platforms) or separately (generator change that preserves old URL structure, then URL restructure separately)?

6. **`ngx-stonescriptphp-pay`**: The payment library currently depends on `ApiConnectionService`. After this redesign it should depend on `ApiClient` directly. Does this change the `ngx-stonescriptphp-pay` API surface?

---

*This spec is a design proposal. Implementation begins after review sign-off.*
