# StoneScriptPHP Server — API Contract Specification

This document defines the API contract for services built with the StoneScriptPHP framework. It covers the response envelope, CRUD conventions, pagination, error handling, authentication, and validation rules. It is intended for API consumers and frontend developers — no knowledge of PHP or the framework internals is required.

---

## Table of Contents

1. [Response Envelope](#1-response-envelope)
2. [HTTP Methods & CRUD Conventions](#2-http-methods--crud-conventions)
3. [URL Naming Conventions](#3-url-naming-conventions)
4. [Request Format](#4-request-format)
5. [Pagination](#5-pagination)
6. [Errors & Status Codes](#6-errors--status-codes)
7. [Validation Errors](#7-validation-errors)
8. [Authentication](#8-authentication)
9. [CORS](#9-cors)

---

## 1. Response Envelope

Every response — success or failure — is wrapped in the same three-field envelope:

```json
{
  "status": "ok | not ok | error",
  "message": "Human-readable description",
  "data": null | {} | []
}
```

| Field     | Type              | Description |
|-----------|-------------------|-------------|
| `status`  | string            | `"ok"` on success, `"not ok"` on a client error, `"error"` on a server or validation error |
| `message` | string            | Short, human-readable description of the outcome |
| `data`    | object, array, or null | The response payload; `null` when there is nothing to return |

### Success example

```json
{
  "status": "ok",
  "message": "User retrieved",
  "data": {
    "user_id": 42,
    "email": "alice@example.com",
    "display_name": "Alice"
  }
}
```

### Empty-data success example (e.g., DELETE)

```json
{
  "status": "ok",
  "message": "User deleted",
  "data": null
}
```

---

## 2. HTTP Methods & CRUD Conventions

| Method   | Purpose                        | Typical HTTP status on success |
|----------|--------------------------------|-------------------------------|
| `GET`    | Retrieve one or many resources | `200 OK`                      |
| `POST`   | Create a resource              | `200 OK` or `201 Created`     |
| `PUT`    | Full replacement of a resource | `200 OK`                      |
| `PATCH`  | Partial update of a resource   | `200 OK`                      |
| `DELETE` | Remove a resource              | `200 OK` or `204 No Content`  |

`OPTIONS` requests are handled automatically for CORS preflight.

---

## 3. URL Naming Conventions

- Use **plural nouns** for collections: `/users`, `/products`, `/orders`
- Use **lowercase with hyphens**: `/user-profiles`, not `/userProfiles`
- **No verbs** in URLs: `/users` not `/get-users`
- Sub-resources follow the parent: `/users/{id}/orders`, `/products/{id}/reviews`

---

## 4. Request Format

### GET requests

Pass parameters in the **query string**:

```
GET /users?page=1&limit=20&status=active
```

### POST / PUT / PATCH requests

Send a JSON body with `Content-Type: application/json`:

```http
POST /users
Content-Type: application/json

{
  "email": "alice@example.com",
  "password": "secret123",
  "display_name": "Alice"
}
```

> **Note:** `Content-Type: application/json` is **required** for all non-GET requests. Requests missing this header will be rejected.

---

## 5. Pagination

### Request parameters

| Parameter | Type    | Default | Description |
|-----------|---------|---------|-------------|
| `page`    | integer | `1`     | 1-based page number |
| `limit`   | integer | `20`    | Items per page (max `100`) |
| `sort`    | string  | —       | Field name to sort by |
| `order`   | string  | —       | `asc` or `desc` |
| `q`       | string  | —       | Optional full-text search query |

Example:
```
GET /users?page=2&limit=25&sort=created_at&order=desc
```

### Response format

Paginated responses nest the collection and a `pagination` object inside `data`:

```json
{
  "status": "ok",
  "message": "Users retrieved",
  "data": {
    "users": [
      { "user_id": 1, "email": "alice@example.com" },
      { "user_id": 2, "email": "bob@example.com" }
    ],
    "pagination": {
      "page": 2,
      "limit": 25,
      "total": 150,
      "pages": 6
    }
  }
}
```

| Field              | Description |
|--------------------|-------------|
| `pagination.page`  | Current page number |
| `pagination.limit` | Items per page |
| `pagination.total` | Total number of matching records |
| `pagination.pages` | Total number of pages (`ceil(total / limit)`) |

---

## 6. Errors & Status Codes

### HTTP status codes

| Code | Meaning |
|------|---------|
| `200` | Success |
| `201` | Resource created |
| `204` | Success, no body |
| `400` | Bad request — malformed input or failed validation |
| `401` | Unauthenticated — missing or invalid token |
| `403` | Forbidden — authenticated but not authorized |
| `404` | Resource not found |
| `405` | Method not allowed |
| `409` | Conflict — e.g., duplicate email |
| `422` | Unprocessable entity — semantic validation failure |
| `429` | Rate limit exceeded |
| `500` | Internal server error |
| `503` | Service temporarily unavailable |

### Status field mapping

| HTTP range | `status` field value | Meaning |
|------------|----------------------|---------|
| 2xx        | `"ok"`               | Request succeeded |
| 4xx        | `"not ok"`           | Client error |
| 5xx        | `"error"`            | Server or framework error |

### Not-found error

```json
HTTP 404

{
  "status": "not ok",
  "message": "User with ID 99 not found",
  "data": null
}
```

### Unauthorized error

```json
HTTP 401

{
  "status": "not ok",
  "message": "Unauthorized",
  "data": null
}
```

### Conflict error

```json
HTTP 409

{
  "status": "not ok",
  "message": "An account with this email already exists",
  "data": null
}
```

### Server error

In production, the `data` field is intentionally empty to avoid leaking internals:

```json
HTTP 500

{
  "status": "error",
  "message": "Server error",
  "data": {}
}
```

---

## 7. Validation Errors

When request input fails validation the API returns `HTTP 400`. The `data` field is a map of field names to arrays of error messages.

```json
HTTP 400

{
  "status": "error",
  "message": "Validation failed",
  "data": {
    "email": [
      "The email field is required.",
      "The email must be a valid email address."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

Each key in `data` is the name of the request field that failed. Each value is an array of one or more human-readable error strings.

---

## 8. Authentication

Protected endpoints require a **JWT Bearer token** in the `Authorization` header:

```
Authorization: Bearer <token>
```

### Obtaining tokens

Tokens are issued by the StoneScriptDB Gateway's auth endpoints (not by the PHP API itself). The PHP API validates incoming JWTs but does not issue them. Typical flows:

- **OTP login**: `POST /auth/login` → sends OTP to email → `POST /auth/verify-otp` → returns access + refresh tokens
- **OAuth login**: `POST /auth/oauth/initiate` → redirect → `POST /auth/oauth/callback` → returns tokens
- **Token refresh**: `POST /auth/refresh` → exchanges refresh token for new access + refresh tokens

The access token is a JWT signed with RS256. The PHP framework validates it using the gateway's JWKS endpoint (`GET /auth/jwks`).

### JWT payload claims

| Claim           | Type           | Description |
|-----------------|----------------|-------------|
| `identity_id`   | string (UUID)  | User's identity UUID in the auth system |
| `tenant_id`     | string (UUID)  | Tenant the token is scoped to |
| `tenant_slug`   | string         | Human-readable tenant slug (e.g., `"acme"`) |
| `platform_code` | string         | Platform identifier (e.g., `"medstoreapp"`) |
| `role`          | string         | User's role in this tenant (e.g., `"admin"`, `"staff"`) |
| `local_user_id` | string \| null | Platform-specific user record ID (null if not yet linked) |
| `iat`           | integer        | Issued-at timestamp (Unix) |
| `exp`           | integer        | Expiry timestamp (Unix) |

### Token expiry

When a token expires the API returns:

```json
HTTP 401

{
  "status": "not ok",
  "message": "Token expired",
  "data": null
}
```

Clients should refresh the token and retry the request.

### Supported auth methods

The gateway supports the following authentication mechanisms (enabled per-platform via configuration):

- Email + OTP (passwordless)
- Email + password
- Google OAuth

---

## 9. CORS

The API returns `Access-Control-Allow-Origin` only for origins in the server's allow-list. Preflight `OPTIONS` requests are handled automatically.

**Response headers (matching origin):**

```
Access-Control-Allow-Origin: https://app.example.com
Access-Control-Allow-Methods: POST, GET, OPTIONS
Access-Control-Allow-Headers: Alt-Used, Content-Type, Authorization
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 900
Vary: Origin
```

Requests from origins **not** in the allow-list receive no `Access-Control-Allow-Origin` header and will be blocked by the browser.

---

*This specification describes the StoneScriptPHP Server contract as of April 2026. For framework internals and contributor documentation, see the source code and inline PHPDoc.*
