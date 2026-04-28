# b-ggko's Inventory API

A Laravel 13 REST API for hardware inventory management ? ESP32 boards, sensors, jumpers, tools, and assembled circuits ? built with multi-tenant isolation, role-based access control, and a dual-mode gateway/backend architecture.

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Runtime Modes](#runtime-modes)
- [Multi-Tenant Isolation](#multi-tenant-isolation)
- [Roles and Permissions](#roles-and-permissions)
- [Security and Authentication](#security-and-authentication)
- [Route Reference](#route-reference)
- [Endpoint Details](#endpoint-details)
- [Validation Rules](#validation-rules)
- [Response and Error Patterns](#response-and-error-patterns)
- [Environment Variables](#environment-variables)
- [Local Setup](#local-setup)
- [Multi-Backend Scripts](#multi-backend-scripts)
- [Tests and Code Quality](#tests-and-code-quality)

---

## Overview

This is an API-only Laravel application with two primary responsibilities:

1. **HTTP Gateway** ? load-balanced proxy with round-robin client selection, health diagnostics, failover, and telemetry logging.
2. **Business API** ? authentication, user management, and a full hardware inventory domain.

The inventory domain supports:

- Dynamic item categories (scoped per company/tenant).
- Items with flexible key/value attributes (`mac_address`, `connector_type`, `color`, etc.).
- Per-location stock tracking with minimum quantity alerts.
- Assembled circuits that track which items are used, in what quantity, and with optional notes.
- Team member management for multi-user companies.

---

## Architecture

```
Client Request  (X-API-KEY + Bearer Token)
        |
  routes/api.php
        |
 BACKEND_SERVER_ROLE
        |                              |
  gateway mode                   backend mode
        |                              |
 api-gateway.php              api-backend.php
 /gateway-health              /login
 /inventory/*                 /register
 /proxy/{path?}               /users/{uuid}/profile
 /{path?} catch-all           /users/{uuid}/password
                              /users/{uuid}/admin
                              /inventory/*
```

Backend servers are configured via `BACKEND_URL_1` through `BACKEND_URL_5`. The gateway selects the next server via round-robin (stored in cache) and falls back on failures.

---

## Runtime Modes

Controlled by `BACKEND_SERVER_ROLE` in `.env`:

| Mode | Value | Loads |
|---|---|---|
| Gateway | `gateway` (default) | `routes/api-gateway.php` |
| Backend | `backend` | `routes/api-backend.php` |

In **gateway mode**, inventory routes are served locally. Auth and user routes are forwarded to a backend instance via proxy.

In **backend mode**, all routes ? auth, user management, and inventory ? are served locally.

---

## Multi-Tenant Isolation

Every user belongs to a `Company`. All inventory resources (categories, items, circuits) are scoped to the authenticated user's company:

- Resources from other companies are never returned ? they appear as `404`.
- Cross-company ID guessing is prevented at every read and write operation.
- On first inventory access, a company is automatically created for the user if one does not exist yet.

---

## Roles and Permissions

Inventory access is controlled by the `inventory_role` field on the user:

| Role | Value | Can view | Can create/edit/delete | Can manage team |
|---|---|---|---|---|
| Owner | `owner` | Yes | Yes | Yes |
| Purchasing | `purchasing` | Yes | No | No |
| Admin user | *(any)* | Yes | Yes | Yes |

- `owner` is assigned automatically on registration.
- `purchasing` users can only read inventory data.
- Admin users (`admin = true`) bypass role restrictions entirely.
- Attempting a write as `purchasing` returns `403 Forbidden`.

---

## Security and Authentication

### API Key

All `/api/*` routes require the `X-API-KEY` header:

```http
X-API-KEY: your-secret-api-key
```

Missing or invalid key response (`401`):

```json
{ "message": "Invalid API key." }
```

### Bearer Token (Sanctum)

Inventory routes and user management routes additionally require a Sanctum token:

```http
Authorization: Bearer 1|abc123...
```

The token is returned by the login endpoint.

---

## Route Reference

### Gateway Routes

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/gateway-health` | API key | Health report for all backend servers |
| GET | `/api/inventory/categories` | API key + Sanctum | List categories |
| POST | `/api/inventory/categories` | API key + Sanctum (owner) | Create category |
| GET | `/api/inventory/categories/{category}` | API key + Sanctum | Show category |
| PUT/PATCH | `/api/inventory/categories/{category}` | API key + Sanctum (owner) | Update category |
| DELETE | `/api/inventory/categories/{category}` | API key + Sanctum (owner) | Delete category |
| GET | `/api/inventory/items` | API key + Sanctum | List items |
| POST | `/api/inventory/items` | API key + Sanctum (owner) | Create item |
| GET | `/api/inventory/items/{item}` | API key + Sanctum | Show item |
| PUT/PATCH | `/api/inventory/items/{item}` | API key + Sanctum (owner) | Update item |
| DELETE | `/api/inventory/items/{item}` | API key + Sanctum (owner) | Delete item |
| GET | `/api/inventory/items/{item}/usage` | API key + Sanctum | Circuits using an item |
| GET | `/api/inventory/circuits` | API key + Sanctum | List circuits |
| POST | `/api/inventory/circuits` | API key + Sanctum (owner) | Create circuit |
| GET | `/api/inventory/circuits/{circuit}` | API key + Sanctum | Show circuit |
| PUT/PATCH | `/api/inventory/circuits/{circuit}` | API key + Sanctum (owner) | Update circuit |
| DELETE | `/api/inventory/circuits/{circuit}` | API key + Sanctum (owner) | Delete circuit |
| GET | `/api/inventory/low-stock` | API key + Sanctum | Items below minimum stock |
| GET | `/api/inventory/team-members` | API key + Sanctum (owner) | List company team members |
| POST | `/api/inventory/team-members` | API key + Sanctum (owner) | Add a team member |
| ANY | `/api/proxy/{path?}` | API key | Explicit proxy to backend |
| ANY | `/api/{path?}` | API key | Catch-all proxy to backend |

### Auth Routes (backend mode only)

| Method | Path | Description |
|---|---|---|
| POST | `/api/login` | Authenticate and receive token |
| POST | `/api/register` | Register new user (creates company, assigns owner) |

### User Management Routes (backend mode only)

| Method | Path | Description |
|---|---|---|
| PATCH | `/api/users/{uuid}/profile` | Update username, email, phone, cpf |
| PATCH | `/api/users/{uuid}/password` | Change password |
| PATCH | `/api/users/{uuid}/admin` | Toggle admin flag |

---

## Endpoint Details

### Gateway Health

**GET** `/api/gateway-health`

Returns live health diagnostics for all configured backend servers.

**200 response:**

```json
{
  "summary": {
    "status": "healthy",
    "healthy": 5,
    "unhealthy": 0,
    "total": 5
  },
  "backends": [
    {
      "url": "http://127.0.0.1:9001",
      "status": "healthy",
      "response_time_ms": 12
    }
  ]
}
```

---

### Proxy

**ANY** `/api/proxy/{path?}` and **ANY** `/api/{path?}`

Forwards the request to the next available backend via round-robin. Adds `X-Backend-Url` to the response headers.

**503 response** (all backends unavailable):

```json
{ "message": "No backend servers available." }
```

---

### Login

**POST** `/api/login`

`login` accepts either `email` or `username`.

**Request:**

```json
{
  "login": "giordanoberwig@proton.me",
  "password": "secret1234"
}
```

**200 response:**

```json
{
  "message": "Login successful.",
  "user": {
    "id": 1,
    "uuid": "c6eb3f84-7db8-41ea-889f-e7ae5d62ce75",
    "username": "giordanoberwig",
    "email": "giordanoberwig@proton.me",
    "phone": "11999999999",
    "cpf": "12345678901",
    "admin": true,
    "inventory_role": "owner"
  },
  "token_type": "Bearer",
  "access_token": "1|abc123..."
}
```

**401 response:**

```json
{ "message": "Invalid credentials." }
```

---

### Register

**POST** `/api/register`

Creates a new user, automatically creates a company for them, and assigns the `owner` inventory role.

**Request:**

```json
{
  "username": "newuser",
  "email": "newuser@example.com",
  "phone": "11999990000",
  "cpf": "12345678901",
  "password": "secret1234",
  "admin": false
}
```

**201 response:**

```json
{
  "message": "User registered successfully.",
  "user": {
    "id": 2,
    "uuid": "e3c21c70-7df4-49aa-8d80-3d6d24a6c6cf",
    "username": "newuser",
    "email": "newuser@example.com",
    "phone": "11999990000",
    "cpf": "12345678901",
    "admin": false,
    "inventory_role": "owner"
  }
}
```

---

### Update Profile

**PATCH** `/api/users/{user_uuid}/profile` ? all fields optional

**Request:**

```json
{
  "username": "updateduser",
  "email": "updated@example.com",
  "phone": "11999998888",
  "cpf": "10987654321"
}
```

**200 response:**

```json
{
  "message": "User profile updated successfully.",
  "user": {
    "id": 1,
    "uuid": "c6eb3f84-7db8-41ea-889f-e7ae5d62ce75",
    "username": "updateduser",
    "email": "updated@example.com"
  }
}
```

---

### Update Password

**PATCH** `/api/users/{user_uuid}/password`

**Request:** `{ "password": "newsecret1234" }`

**200 response:** `{ "message": "User password updated successfully.", "user": { "id": 1, "uuid": "..." } }`

---

### Update Admin Permission

**PATCH** `/api/users/{user_uuid}/admin`

**Request:** `{ "admin": true }`

**200 response:** `{ "message": "User admin status updated successfully.", "user": { "id": 1, "admin": true } }`

---

### Categories

All category endpoints are tenant-scoped. Categories from other companies always return `404`.

#### List Categories

**GET** `/api/inventory/categories`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Microcontrollers",
      "description": "Development boards and kits",
      "items_count": 3,
      "created_at": "2026-04-28T13:00:00.000000Z",
      "updated_at": "2026-04-28T13:00:00.000000Z"
    }
  ]
}
```

#### Create Category

**POST** `/api/inventory/categories` ? requires `owner` role or admin

**Request:**

```json
{ "name": "Sensors", "description": "Digital and analog sensors" }
```

**201 response:**

```json
{
  "message": "Category created successfully.",
  "data": {
    "id": 2,
    "name": "Sensors",
    "description": "Digital and analog sensors",
    "created_at": "2026-04-28T13:20:00.000000Z",
    "updated_at": "2026-04-28T13:20:00.000000Z"
  }
}
```

#### Show Category

**GET** `/api/inventory/categories/{category}`

```json
{ "data": { "id": 2, "name": "Sensors", "description": "...", "items_count": 0 } }
```

#### Update Category

**PUT/PATCH** `/api/inventory/categories/{category}` ? requires `owner` role or admin

**Request (partial):** `{ "description": "Sensors for IoT projects" }`

**200 response:** `{ "message": "Category updated successfully.", "data": { ... } }`

#### Delete Category

**DELETE** `/api/inventory/categories/{category}` ? requires `owner` role or admin

**204** ? empty body.

---

### Items

Eager loads on list/show: `category`, `details`, `inventories`, and `circuits` (show only).

#### List Items

**GET** `/api/inventory/items`

```json
{
  "data": [
    {
      "id": 1,
      "category_id": 1,
      "name": "ESP32-CAM",
      "brand": "Espressif",
      "model": "AI-Thinker",
      "description": "Camera module with Wi-Fi and Bluetooth",
      "category": { "id": 1, "name": "Microcontrollers" },
      "details": [
        { "id": 1, "key": "mac_address", "value": "AA:BB:CC:DD:EE:FF" },
        { "id": 2, "key": "connector_type", "value": "Macho-Femea" }
      ],
      "inventories": [
        { "id": 1, "location": "Drawer A1", "quantity": 5, "min_quantity": 2 }
      ]
    }
  ]
}
```

#### Create Item

**POST** `/api/inventory/items` ? requires `owner` role or admin

```json
{
  "category_id": 1,
  "name": "ESP32-CAM",
  "brand": "Espressif",
  "model": "AI-Thinker",
  "description": "Camera module with Wi-Fi and Bluetooth",
  "details": [
    { "key": "mac_address", "value": "AA:BB:CC:DD:EE:FF" },
    { "key": "connector_type", "value": "Macho-Femea" },
    { "key": "color", "value": "Black" }
  ],
  "inventories": [
    { "location": "Drawer A1", "quantity": 5, "min_quantity": 2 },
    { "location": "Shelf B2", "quantity": 1, "min_quantity": 3 }
  ]
}
```

**201 response:**

```json
{
  "message": "Item created successfully.",
  "data": {
    "id": 1,
    "name": "ESP32-CAM",
    "category": { "id": 1, "name": "Microcontrollers" },
    "details": [
      { "id": 1, "item_id": 1, "key": "mac_address", "value": "AA:BB:CC:DD:EE:FF" },
      { "id": 2, "item_id": 1, "key": "connector_type", "value": "Macho-Femea" },
      { "id": 3, "item_id": 1, "key": "color", "value": "Black" }
    ],
    "inventories": [
      { "id": 1, "item_id": 1, "location": "Drawer A1", "quantity": 5, "min_quantity": 2 },
      { "id": 2, "item_id": 1, "location": "Shelf B2", "quantity": 1, "min_quantity": 3 }
    ]
  }
}
```

#### Show Item

**GET** `/api/inventory/items/{item}`

Eager loads: `category`, `details`, `inventories`, `circuits` (id, name).

#### Update Item

**PUT/PATCH** `/api/inventory/items/{item}` ? requires `owner` role or admin

> **Important:** if `details` is provided, the existing set is **deleted and fully replaced**. Same for `inventories`.

**Request (partial):**

```json
{
  "name": "ESP32-CAM v2",
  "details": [
    { "key": "mac_address", "value": "AA:BB:CC:DD:EE:11" },
    { "key": "connector_type", "value": "Macho-Macho" }
  ]
}
```

**200 response:** `{ "message": "Item updated successfully.", "data": { ... } }`

#### Delete Item

**DELETE** `/api/inventory/items/{item}` ? requires `owner` role or admin

**204** ? empty body.

---

### Circuits

#### List Circuits

**GET** `/api/inventory/circuits`

Eager loads `itemUsages.item` (id, name, brand, model).

```json
{
  "data": [
    {
      "id": 1,
      "name": "Monitoring station",
      "description": "Temperature and humidity reading",
      "location": "Workbench 1",
      "assembled_at": "2026-04-28T10:30:00.000000Z",
      "item_usages": [
        {
          "id": 1,
          "item_id": 1,
          "quantity_used": 2,
          "notes": "Main environment sensor",
          "item": { "id": 1, "name": "BME280", "brand": "Bosch", "model": "BME280" }
        }
      ]
    }
  ]
}
```

#### Create Circuit

**POST** `/api/inventory/circuits` ? requires `owner` role or admin

```json
{
  "name": "Monitoring station",
  "description": "Temperature and humidity reading",
  "location": "Workbench 1",
  "assembled_at": "2026-04-28T10:30:00Z",
  "used_items": [
    { "item_id": 1, "quantity_used": 2, "notes": "Main environment sensor" },
    { "item_id": 3, "quantity_used": 1, "notes": "Controller board" }
  ]
}
```

**201 response:**

```json
{
  "message": "Circuit created successfully.",
  "data": {
    "id": 1,
    "name": "Monitoring station",
    "location": "Workbench 1",
    "assembled_at": "2026-04-28T10:30:00.000000Z",
    "item_usages": [
      {
        "id": 1,
        "item_id": 1,
        "quantity_used": 2,
        "notes": "Main environment sensor",
        "item": { "id": 1, "name": "BME280", "brand": "Bosch", "model": "BME280" }
      }
    ]
  }
}
```

#### Show Circuit

**GET** `/api/inventory/circuits/{circuit}`

#### Update Circuit

**PUT/PATCH** `/api/inventory/circuits/{circuit}` ? requires `owner` role or admin

> **Important:** if `used_items` is provided, the existing set is **deleted and fully replaced**.

**Request (partial):**

```json
{
  "location": "Lab 2",
  "used_items": [
    { "item_id": 1, "quantity_used": 3, "notes": "Updated for revision 2" }
  ]
}
```

**200 response:** `{ "message": "Circuit updated successfully.", "data": { ... } }`

#### Delete Circuit

**DELETE** `/api/inventory/circuits/{circuit}` ? requires `owner` role or admin

**204** ? empty body.

---

### Item Usage in Circuits

**GET** `/api/inventory/items/{item}/usage`

Returns all circuits that use the specified item.

```json
{
  "item": { "id": 1, "name": "BME280", "brand": "Bosch", "model": "BME280" },
  "used_in_circuits": [
    {
      "id": 1,
      "name": "Monitoring station",
      "location": "Workbench 1",
      "assembled_at": "2026-04-28T10:30:00.000000Z",
      "quantity_used": 2,
      "notes": "Main environment sensor"
    }
  ]
}
```

---

### Low Stock Items

**GET** `/api/inventory/low-stock`

Returns items where `quantity <= min_quantity` in at least one inventory location. Only locations in alert state are included in the `inventories` relation.

```json
{
  "data": [
    {
      "id": 1,
      "name": "ESP32-CAM",
      "category": { "id": 1, "name": "Microcontrollers" },
      "inventories": [
        { "id": 2, "location": "Shelf B2", "quantity": 1, "min_quantity": 3 }
      ]
    }
  ]
}
```

---

### Team Members

Only users with the `owner` role (or admin) can access these endpoints. Members are always scoped to the authenticated user's company.

#### List Team Members

**GET** `/api/inventory/team-members`

```json
{
  "data": [
    {
      "id": 2,
      "uuid": "d1e2f3a4-...",
      "name": "Maria",
      "username": "maria",
      "email": "maria@example.com",
      "phone": "11988880000",
      "cpf": "98765432100",
      "inventory_role": "purchasing",
      "created_at": "2026-04-28T15:00:00.000000Z"
    }
  ]
}
```

#### Add Team Member

**POST** `/api/inventory/team-members`

Creates a new user under the authenticated user's company. Default role is `purchasing` if not specified.

**Request:**

```json
{
  "username": "maria",
  "email": "maria@example.com",
  "phone": "11988880000",
  "cpf": "98765432100",
  "password": "secret1234",
  "inventory_role": "purchasing"
}
```

`inventory_role` accepts: `owner` or `purchasing`.

**201 response:**

```json
{
  "message": "Team member created successfully.",
  "data": {
    "id": 2,
    "uuid": "d1e2f3a4-...",
    "username": "maria",
    "email": "maria@example.com",
    "inventory_role": "purchasing"
  }
}
```

---

## Validation Rules

### Category

| Field | Rule |
|---|---|
| `name` | Required on create; unique per company; max 120 chars |
| `description` | Optional string |

### Item

| Field | Rule |
|---|---|
| `category_id` | Must exist in categories belonging to the authenticated company |
| `name` | Required on create |
| `brand` | Optional string |
| `model` | Optional string |
| `description` | Optional string |
| `details` | Optional array of `{ key, value }` objects |
| `details.*.key` | Required string |
| `details.*.value` | Required; special format rules apply (see below) |
| `inventories` | Optional array of `{ location, quantity, min_quantity }` |
| `inventories.*.location` | Required string |
| `inventories.*.quantity` | Required integer, min 0 |
| `inventories.*.min_quantity` | Required integer, min 0 |

**Special `details` value validations:**

| Key | Constraint |
|---|---|
| `mac_address` | Must match `AA:BB:CC:DD:EE:FF` (uppercase hex, colon-separated) |
| `connector_type` | Must be one of: `Macho-Macho`, `Macho-Femea`, `Femea-Femea` |

### Circuit

| Field | Rule |
|---|---|
| `name` | Required on create |
| `description` | Optional string |
| `location` | Optional string |
| `assembled_at` | Optional date |
| `used_items` | Optional array of `{ item_id, quantity_used, notes? }` |
| `used_items.*.item_id` | Must exist in items belonging to the authenticated company |
| `used_items.*.quantity_used` | Required integer, min 1 |
| `used_items.*.notes` | Optional string |

### Team Member

| Field | Rule |
|---|---|
| `username` | Required, unique |
| `email` | Required, valid email, unique |
| `phone` | Required string |
| `cpf` | Required string |
| `password` | Required, min 8 chars |
| `inventory_role` | Optional; must be `owner` or `purchasing` |

---

## Response and Error Patterns

### Success Codes

| Code | Meaning |
|---|---|
| 200 | Successful read or update |
| 201 | Resource created |
| 204 | Deleted ? empty body |

### Error Codes

| Code | Meaning |
|---|---|
| 401 | Missing or invalid `X-API-KEY` |
| 401 | Missing or invalid Bearer token on protected routes |
| 401 | Invalid credentials on login |
| 403 | Authenticated but insufficient role (e.g. `purchasing` on write) |
| 404 | Resource not found or belongs to another company |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Internal / misconfiguration error |
| 503 | All backend servers unavailable (gateway proxy) |

**422 example:**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "details.0.value": [
      "The MAC address must use format AA:BB:CC:DD:EE:FF."
    ]
  }
}
```

---

## Environment Variables

| Variable | Description | Default |
|---|---|---|
| `API_KEY` | Required header value for all API routes | ? |
| `BACKEND_SERVER_ROLE` | `gateway` or `backend` | `gateway` |
| `BACKEND_URL_1` ? `BACKEND_URL_5` | Backend server URLs | ? |
| `BACKEND_TIMEOUT` | Total request timeout (seconds) | `10` |
| `BACKEND_CONNECT_TIMEOUT` | Connection timeout (seconds) | `3` |
| `BACKEND_RETRIES` | Retry attempts per backend | `2` |
| `BACKEND_RETRY_SLEEP_MS` | Sleep between retries (ms) | `150` |
| `BACKEND_MAX_TOTAL_DURATION_MS` | Max total proxy duration (ms) | `25000` |
| `BACKEND_CACHE_STORE` | Cache store for round-robin/health | `file` |
| `BACKEND_AUTH_RATE_LIMIT_PER_MINUTE` | Auth endpoint rate limit | `30` prod / `300` dev |
| `BACKEND_WRITE_RATE_LIMIT_PER_MINUTE` | Write endpoint rate limit | `60` prod / `600` dev |
| `BACKEND_API_KEY` | API key forwarded to backend servers | ? |
| `BACKEND_FORWARD_CLIENT_API_KEY` | Forward client API key to backend | `false` |
| `BACKEND_HEALTH_PATH` | Path used for backend health checks | `/up` |
| `BACKEND_HEALTH_METHOD` | HTTP method for health checks | `GET` |
| `BACKEND_HEALTH_REPORT_CACHE_TTL_SECONDS` | Health report cache TTL (seconds) | `2` |
| `BACKEND_HEALTH_REPORT_CACHE_KEY` | Cache key for health reports | `backends:health:report` |
| `BACKEND_ROUND_ROBIN_CACHE_KEY` | Cache key for round-robin counter | `backends:round-robin:index` |
| `BACKEND_TELEMETRY_ENABLED` | Enable proxy telemetry logging | `true` |
| `BACKEND_TELEMETRY_SAMPLE_RATE_PERCENTAGE` | Telemetry sampling rate (%) | `100` |

**`.env` baseline example:**

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

API_KEY=your-secret-api-key

BACKEND_SERVER_ROLE=gateway
BACKEND_URL_1=http://127.0.0.1:9001
BACKEND_URL_2=http://127.0.0.1:9002
BACKEND_URL_3=http://127.0.0.1:9003
BACKEND_URL_4=http://127.0.0.1:9004
BACKEND_URL_5=http://127.0.0.1:9005

DB_CONNECTION=sqlite
```

---

## Local Setup

**1. Install dependencies**

```bash
composer install
```

**2. Create `.env` and generate app key**

```bash
cp .env.example .env
php artisan key:generate
```

**3. Run migrations**

```bash
php artisan migrate
```

**4. Start the application**

```bash
php artisan serve
```

**5. Register and get a token**

```bash
curl -s -X POST http://localhost:8000/api/register \
  -H "X-API-KEY: your-secret-api-key" \
  -H "Content-Type: application/json" \
  -d '{"username":"me","email":"me@example.com","phone":"11999990000","cpf":"12345678901","password":"secret1234"}'
```

Use the returned `access_token` as the Bearer token on all subsequent requests.

---

## Multi-Backend Scripts

Start and stop local backend instances for gateway mode testing.

**Windows:**

```bat
scripts\start-backends.bat -StopExistingOnPorts
scripts\stop-backends.bat
```

**Linux / macOS:**

```bash
chmod +x scripts/start-backends.sh scripts/stop-backends.sh
./scripts/start-backends.sh --stop-existing-on-ports
./scripts/stop-backends.sh --include-unknown-port-listeners
```

---

## Tests and Code Quality

### Run all tests

```bash
composer test
```

### Run only inventory tests (schema + API + authorization)

```bash
composer test:inventory
```

### Run the full-route smoke suite

Covers all 22 routes with `X-API-KEY` + authenticated Sanctum session:

```bash
composer test:smoke
```

The smoke suite (`tests/Feature/RouteRequests/ApiRoutesSmokeTest.php`) validates:

- Gateway health and proxy routes
- Category CRUD
- Item CRUD, usage, and low-stock
- Circuit CRUD
- Team member list and create

### Run a specific test by filter

```bash
php artisan test --compact --filter=it_creates_a_category
```

### Code formatting

```bash
vendor/bin/pint --dirty --format agent
```

### Composer scripts

| Script | Description |
|---|---|
| `composer test` | Full test suite |
| `composer test:smoke` | All-routes smoke check (API key + session) |
| `composer test:inventory` | Inventory schema + API + authorization tests |
| `composer dev` | Start local development server |
| `composer setup` | Install + generate key + migrate (first-time setup) |
