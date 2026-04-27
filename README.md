# Inventory API

Laravel 13 API that works as a load-balancing gateway in front of multiple backend instances.

## Table of Contents
- Architecture Overview
- Runtime Modes
- Security
- Headers
- API Endpoints (Gateway Mode)
- API Endpoints (Backend Mode)
- Request / Response Patterns
- Validation Rules
- Error Responses
- Environment Variables
- Development Setup
- Running Local Backends
- Testing and Quality Checks
- Production Readiness
- Troubleshooting

## Architecture Overview
This project runs in two roles:
- Gateway role: receives client traffic and forwards requests using round-robin + failover.
- Backend role: processes the actual business endpoints (`/api/login`, `/api/register`, `/api/users/...`).

Core behavior:
- Round-robin across 5 backend URLs.
- Automatic failover on unavailable backends and server errors.
- Correlation with `X-Request-Id`.
- Backend traceability with `X-Backend-Url` and `processed_by_server`.
- Detailed health diagnostics at `/api/gateway-health`.

## Runtime Modes
The route file dispatcher is role-based:
- `BACKEND_SERVER_ROLE=gateway` loads gateway routes.
- `BACKEND_SERVER_ROLE=backend` loads backend business routes.

Route files:
- `routes/api.php` (role dispatcher)
- `routes/api-gateway.php` (gateway endpoints)
- `routes/api-backend.php` (backend endpoints)

## Security
All `/api` routes require API key middleware.

Required header:
- `X-API-KEY: <your-api-key>`

If API key is missing/invalid:
- Status `401`
- Body:

```json
{
  "message": "Invalid API key."
}
```

If API key is not configured server-side:
- Status `500`
- Body:

```json
{
  "message": "API key is not configured."
}
```

## Headers
### Request Headers
- `X-API-KEY` (required)
- `Content-Type: application/json` (for JSON body requests)
- `X-Request-Id` (optional; if missing, gateway generates a UUID)

### Gateway Response Headers
For proxied requests, gateway includes:
- `X-Backend-Url`: backend that processed the request
- `X-Request-Id`: correlation ID used for this request

## API Endpoints (Gateway Mode)
Base URL example:
- `http://localhost:8000`

### 1) Gateway Health
- Method: `GET`
- Path: `/api/gateway-health`
- Route name: `gateway.health`
- Auth: API key required

Purpose:
- Returns gateway diagnostics, backend probe results, dependency checks, and summary status.

Possible statuses:
- `200`: healthy or degraded
- `503`: all backends down
- `500`: gateway misconfigured (for example, no backends configured)

Example request:

```bash
curl -X GET http://localhost:8000/api/gateway-health \
  -H "X-API-KEY: your-secret-api-key"
```

Example response (`200`):

```json
{
  "timestamp": "2026-04-27T13:36:03+00:00",
  "application": {
    "name": "Laravel",
    "environment": "local",
    "php_version": "8.5.3",
    "laravel_version": "13.6.0",
    "timezone": "UTC",
    "cache_store": "database",
    "queue_connection": "database",
    "database_connection": "sqlite"
  },
  "round_robin": {
    "cache_key": "backends:round-robin:index",
    "cache_store": "file",
    "counter": 45,
    "next_index": 1,
    "configured_servers": 5
  },
  "dependencies": {
    "database": {
      "ok": true,
      "response_time_ms": 3,
      "error": null
    },
    "cache": {
      "ok": true,
      "response_time_ms": 11,
      "error": null
    }
  },
  "summary": {
    "status": "healthy",
    "configured_servers": 5,
    "healthy_servers": 5,
    "unhealthy_servers": 0,
    "health_percentage": 100,
    "api_dependencies_healthy": true,
    "avg_response_time_ms": 14.2,
    "min_response_time_ms": 13,
    "max_response_time_ms": 18,
    "overall_check_time_ms": 86
  },
  "servers": [
    {
      "server": "http://127.0.0.1:9001",
      "probe_url": "http://127.0.0.1:9001/up",
      "ok": true,
      "http_status": 200,
      "response_time_ms": 18,
      "error": null,
      "response_excerpt": "{\"status\":\"up\"}"
    }
  ]
}
```

### 2) Explicit Proxy Endpoint
- Method: `ANY`
- Path: `/api/proxy/{path?}`
- Route name: `gateway.proxy.explicit`
- Auth: API key required

Forwarding behavior:
- Sends request to backend path exactly as provided in `{path}`.
- Does not auto-prepend `api/`.

Example:

```bash
curl -X GET http://localhost:8000/api/proxy/up \
  -H "X-API-KEY: your-secret-api-key"
```

### 3) Catch-All Proxy Endpoint
- Method: `ANY`
- Path: `/api/{path?}`
- Route name: `gateway.proxy.catch_all`
- Auth: API key required

Forwarding behavior:
- Automatically prepends `api/` when forwarding.
- Example: `/api/login` -> backend receives `/api/login`.

This is the normal client entrypoint for business APIs.

## API Endpoints (Backend Mode)
Base URL examples:
- `http://127.0.0.1:9001`
- `http://127.0.0.1:9002`
- `http://127.0.0.1:9003`
- `http://127.0.0.1:9004`
- `http://127.0.0.1:9005`

These are usually called by the gateway, not directly by external clients.

### 1) Login
- Method: `POST`
- Path: `/api/login`
- Route name: `backend.login`
- Middleware: `throttle:backend-auth`

Request body:

```json
{
  "login": "giordanoberwig@proton.me",
  "password": "12345678"
}
```

Success (`200`):

```json
{
  "message": "Login successful.",
  "user": {
    "id": 1,
    "name": "giordanoberwig",
    "email": "giordanoberwig@proton.me",
    "email_verified_at": null,
    "created_at": "2026-04-27T13:35:02.000000Z",
    "updated_at": "2026-04-27T13:35:33.000000Z",
    "username": "giordanoberwig",
    "phone": "11999999999",
    "cpf": "12345678901",
    "admin": true
  }
}
```

Notes:
- `access_token` is a Sanctum personal access token.
- Use it as `Authorization: Bearer <access_token>` in protected backend update endpoints.

Invalid credentials (`401`):

```json
{
  "message": "Invalid credentials."
}
```

### 2) Register User
- Method: `POST`
- Path: `/api/register`
- Route name: `backend.register`
- Middleware: `throttle:backend-auth`

Request body:

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

Success (`201`):

```json
{
  "message": "User registered successfully.",
  "user": {
    "id": 2,
    "name": "newuser",
    "username": "newuser",
    "email": "newuser@example.com",
    "phone": "11999990000",
    "cpf": "12345678901",
    "admin": false
  }
}
```

### 3) Update User Profile
- Method: `PATCH`
- Path: `/api/users/{user_uuid}/profile`
- Route name: `backend.users.profile.update`
- Middleware: `auth:sanctum` + `throttle:backend-write`

Request body (partial update):

```json
{
  "username": "updateduser",
  "email": "updated@example.com",
  "phone": "11999998888",
  "cpf": "10987654321"
}
```

Success (`200`):

```json
{
  "message": "User profile updated successfully.",
  "user": {
    "id": 1,
    "username": "updateduser",
    "email": "updated@example.com",
    "phone": "11999998888",
    "cpf": "10987654321"
  }
}
```

### 4) Update User Password
- Method: `PATCH`
- Path: `/api/users/{user_uuid}/password`
- Route name: `backend.users.password.update`
- Middleware: `auth:sanctum` + `throttle:backend-write`

Request body:

```json
{
  "password": "newsecret1234"
}
```

Success (`200`):

```json
{
  "message": "User password updated successfully.",
  "user": {
    "id": 1
  }
}
```

### 5) Update User Admin Flag
- Method: `PATCH`
- Path: `/api/users/{user_uuid}/admin`
- Route name: `backend.users.admin.update`
- Middleware: `auth:sanctum` + `throttle:backend-write`

Request body:

```json
{
  "admin": true
}
```

Success (`200`):

```json
{
  "message": "User admin status updated successfully.",
  "user": {
    "id": 1,
    "admin": true
  }
}
```

## Request / Response Patterns
### Proxied JSON Responses
When gateway proxies a JSON response, it injects:
- `processed_by_server`
- `request_id`

Example gateway login response:

```json
{
  "message": "Login successful.",
  "user": {
    "id": 1,
    "username": "giordanoberwig"
  },
  "processed_by_server": "http://127.0.0.1:9001",
  "request_id": "cc46f748-66a5-4509-b913-198368310c67"
}
```

### Proxied Non-JSON Responses
- Body is forwarded as-is.
- `Content-Type` is preserved.
- `X-Backend-Url` and `X-Request-Id` headers are still added.

### Correlation ID Behavior
- If client sends `X-Request-Id`, gateway preserves it.
- If not sent, gateway generates a UUID.

## Validation Rules
### Login
- `login`: required, string
- `password`: required, string

### Register
- `username`: required, string, min 3, max 50, unique
- `email`: required, string, email, max 255, unique
- `phone`: required, string, min 8, max 20, unique
- `cpf`: required, string, size 11, unique
- `password`: required, string, min 8
- `admin`: required, boolean

### Update Profile
All optional (`sometimes`):
- `username`: string, min 3, max 50, unique ignoring current user
- `email`: string, email, max 255, unique ignoring current user
- `phone`: string, min 8, max 20, unique ignoring current user
- `cpf`: string, size 11, unique ignoring current user

### Update Password
- `password`: required, string, min 8

### Update Admin
- `admin`: required, boolean

Validation errors follow Laravel JSON validation format (`422`) with `message` and `errors`.

## Error Responses
### Gateway-generated errors
No backend configured (`500`):

```json
{
  "message": "No backend servers configured.",
  "request_id": "..."
}
```

All backends unavailable (`503`):

```json
{
  "message": "All backend servers are unavailable.",
  "request_id": "..."
}
```

### Throttle errors (`429`)
Backend endpoints with limiter middleware can return `429 Too Many Requests`.

Applied limiters:
- `backend-auth` for `/api/login`, `/api/register`
- `backend-write` for `/api/users/{user_uuid}/...` patch endpoints

## Environment Variables
Core gateway and backend settings:
- `API_KEY`
- `BACKEND_URL_1` ... `BACKEND_URL_5`
- `BACKEND_TIMEOUT`
- `BACKEND_CONNECT_TIMEOUT`
- `BACKEND_RETRIES`
- `BACKEND_RETRY_SLEEP_MS`
- `BACKEND_MAX_TOTAL_DURATION_MS`
- `BACKEND_CACHE_STORE`
- `BACKEND_SERVER_ROLE`
- `BACKEND_AUTH_RATE_LIMIT_PER_MINUTE`
- `BACKEND_WRITE_RATE_LIMIT_PER_MINUTE`
- `BACKEND_API_KEY`
- `BACKEND_FORWARD_CLIENT_API_KEY`
- `BACKEND_ROUND_ROBIN_CACHE_KEY`
- `BACKEND_HEALTH_PATH`
- `BACKEND_HEALTH_METHOD`
- `BACKEND_HEALTH_REPORT_CACHE_TTL_SECONDS`
- `BACKEND_HEALTH_REPORT_CACHE_KEY`
- `BACKEND_TELEMETRY_ENABLED`
- `BACKEND_TELEMETRY_SAMPLE_RATE_PERCENTAGE`

Recommended profile (development):

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
API_KEY=your-secret-api-key

BACKEND_URL_1=http://127.0.0.1:9001
BACKEND_URL_2=http://127.0.0.1:9002
BACKEND_URL_3=http://127.0.0.1:9003
BACKEND_URL_4=http://127.0.0.1:9004
BACKEND_URL_5=http://127.0.0.1:9005

BACKEND_TIMEOUT=10
BACKEND_CONNECT_TIMEOUT=3
BACKEND_RETRIES=1
BACKEND_RETRY_SLEEP_MS=100
BACKEND_MAX_TOTAL_DURATION_MS=25000
BACKEND_CACHE_STORE=file
BACKEND_SERVER_ROLE=gateway
BACKEND_AUTH_RATE_LIMIT_PER_MINUTE=300
BACKEND_WRITE_RATE_LIMIT_PER_MINUTE=600
BACKEND_API_KEY=
BACKEND_FORWARD_CLIENT_API_KEY=false
BACKEND_ROUND_ROBIN_CACHE_KEY=backends:round-robin:index:dev

BACKEND_HEALTH_PATH=/up
BACKEND_HEALTH_METHOD=GET
BACKEND_HEALTH_REPORT_CACHE_TTL_SECONDS=0
BACKEND_HEALTH_REPORT_CACHE_KEY=backends:health:report:dev
BACKEND_TELEMETRY_ENABLED=false
BACKEND_TELEMETRY_SAMPLE_RATE_PERCENTAGE=0
```

Recommended profile (production baseline):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-gateway.example.com

BACKEND_URL_1=https://backend1.example.com
BACKEND_URL_2=https://backend2.example.com
BACKEND_URL_3=https://backend3.example.com
BACKEND_URL_4=https://backend4.example.com
BACKEND_URL_5=https://backend5.example.com

BACKEND_TIMEOUT=8
BACKEND_CONNECT_TIMEOUT=2
BACKEND_RETRIES=2
BACKEND_RETRY_SLEEP_MS=150
BACKEND_MAX_TOTAL_DURATION_MS=25000
BACKEND_CACHE_STORE=redis
BACKEND_SERVER_ROLE=gateway
BACKEND_AUTH_RATE_LIMIT_PER_MINUTE=30
BACKEND_WRITE_RATE_LIMIT_PER_MINUTE=60
BACKEND_API_KEY=your-backend-api-key
BACKEND_FORWARD_CLIENT_API_KEY=false
BACKEND_ROUND_ROBIN_CACHE_KEY=backends:round-robin:index:prod

BACKEND_HEALTH_PATH=/up
BACKEND_HEALTH_METHOD=GET
BACKEND_HEALTH_REPORT_CACHE_TTL_SECONDS=2
BACKEND_HEALTH_REPORT_CACHE_KEY=backends:health:report:prod
BACKEND_TELEMETRY_ENABLED=true
BACKEND_TELEMETRY_SAMPLE_RATE_PERCENTAGE=100
```

## Development Setup
1. Install dependencies.

```bash
composer install
```

2. Create env file and app key.

```bash
copy .env.example .env
php artisan key:generate
```

3. Prepare database and seed.

```bash
php artisan migrate
php artisan db:seed
```

4. Start gateway.

```bash
php artisan serve
```

## Running Local Backends
### Windows
Start all backend instances in background:

```bat
scripts\start-backends.bat -StopExistingOnPorts
```

Stop all backend instances:

```bat
scripts\stop-backends.bat
```

### macOS / Linux
First time:

```bash
chmod +x scripts/start-backends.sh scripts/stop-backends.sh
```

Start:

```bash
./scripts/start-backends.sh --stop-existing-on-ports
```

Stop:

```bash
./scripts/stop-backends.sh --include-unknown-port-listeners
```

Script artifacts:
- State file: `storage/framework/backend-servers/servers.json`
- Stdout logs: `storage/framework/backend-servers/backend-<port>.stdout.log`
- Stderr logs: `storage/framework/backend-servers/backend-<port>.stderr.log`

## Testing and Quality Checks
Run focused tests:

```bash
php artisan test --compact --filter=BackendProxyRoundRobinTest
php artisan test --compact --filter=AuthApiTest
php artisan test --compact --filter=BackendHealthApiTest
php artisan test --compact --filter=ApiKeyMiddlewareTest
```

Formatting:

```bash
vendor/bin/pint --dirty --format agent
```

## Production Readiness
Pre-deploy checks:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Optimization and cache warmup:

```bash
composer run deploy:optimize
```

Clear optimization caches (rollback/troubleshooting):

```bash
composer run deploy:clear
```

## Troubleshooting
### "All backend servers are unavailable"
- Ensure all backend processes are running (`9001..9005` in local setup).
- Verify backend URLs in env and health path (`/up` by default).
- Check backend stdout/stderr logs under `storage/framework/backend-servers/`.

### "No query results for model [App\\Models\\User] 1"
- User update routes are UUID-based.
- Do not send numeric `id` in `/api/users/{user_uuid}/...`.
- Use `user_uuid` returned by login response.

### Backend receives invalid API key
- Set `BACKEND_API_KEY` if backend expects a dedicated key.
- Or set `BACKEND_FORWARD_CLIENT_API_KEY=true` to forward client key.

### SQLite lock errors in local multi-backend runs
- Keep `BACKEND_CACHE_STORE=file` for local multi-process setup.

### PowerShell `%%` command errors
- `%%` is cmd alias syntax, not PowerShell syntax.
- Use full PowerShell commands or `%` in appropriate shell context.

## Default Seeded User
`php artisan db:seed` creates/updates:
- Email: `giordanoberwig@proton.me`
- Password: `12345678`
- Username: `giordanoberwig`
- Admin: `true`
