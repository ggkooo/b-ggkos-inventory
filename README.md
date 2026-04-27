# Inventory API

This project is a Laravel 13 API with a custom `API_KEY` middleware and modular authentication/user management endpoints.

All API routes are prefixed with `/api` and require the `X-API-KEY` header.

## Tech Stack

- PHP 8.5
- Laravel 13
- SQLite (default local database)
- Redis variables pre-configured in `.env` (cache can be switched later)

## Quick Start

1. Install dependencies:

```bash
composer install
```

2. Create environment file:

```bash
copy .env.example .env
```

3. Generate app key:

```bash
php artisan key:generate
```

4. Configure `.env` values:

```env
APP_URL=http://localhost:8000
API_KEY=your-secret-api-key
DB_CONNECTION=sqlite
```

5. Run migrations and seed default data:

```bash
php artisan migrate
php artisan db:seed
```

6. Start the server:

```bash
php artisan serve
```

## Authentication Model

This API uses a global API key middleware for every `/api` route.

Required header on every request:

```http
X-API-KEY: your-secret-api-key
Content-Type: application/json
```

If API key is missing or invalid:

- Status: `401 Unauthorized`
- Response:

```json
{
	"message": "Invalid API key."
}
```

If API key is not configured on the server:

- Status: `500 Internal Server Error`
- Response:

```json
{
	"message": "API key is not configured."
}
```

## Default Seeded User

Running `php artisan db:seed` creates or updates a default user:

- Email: `giordanoberwig@proton.me`
- Password: `12345678`

When the `users` table includes the optional columns, seeding also sets:

- Username: `giordanoberwig`
- Admin: `true`

## API Endpoints

Base URL (local):

```text
http://localhost:8000/api
```

### 1) Register User

- Method: `POST`
- URL: `/register`

Request payload:

```json
{
	"username": "giordanoberwig",
	"email": "giordanoberwig@proton.me",
	"phone": "11999999999",
	"cpf": "12345678901",
	"password": "12345678",
	"admin": false
}
```

Validation rules:

- `username`: required, string, min 3, max 50, unique
- `email`: required, valid email, max 255, unique
- `phone`: required, string, min 8, max 20, unique
- `cpf`: required, string, exactly 11 chars, unique
- `password`: required, string, min 8
- `admin`: required, boolean

Success response:

- Status: `201 Created`

```json
{
	"message": "User registered successfully.",
	"user": {
		"id": 1,
		"name": "giordanoberwig",
		"username": "giordanoberwig",
		"email": "giordanoberwig@proton.me",
		"phone": "11999999999",
		"cpf": "12345678901",
		"admin": false,
		"created_at": "...",
		"updated_at": "..."
	}
}
```

### 2) Login

- Method: `POST`
- URL: `/login`

Request payload:

```json
{
	"login": "giordanoberwig@proton.me",
	"password": "12345678"
}
```

Notes:

- `login` accepts either `email` or `username`.

Success response:

- Status: `200 OK`

```json
{
	"message": "Login successful.",
	"user": {
		"id": 1,
		"name": "giordanoberwig",
		"username": "giordanoberwig",
		"email": "giordanoberwig@proton.me",
		"phone": "11999999999",
		"cpf": "12345678901",
		"admin": true,
		"created_at": "...",
		"updated_at": "..."
	}
}
```

Invalid credentials response:

- Status: `401 Unauthorized`

```json
{
	"message": "Invalid credentials."
}
```

### 3) Update User Profile

- Method: `PATCH`
- URL: `/users/{user}/profile`

Request payload (any combination of fields):

```json
{
	"username": "new_username",
	"email": "new@email.com",
	"phone": "11911111111",
	"cpf": "11122233344"
}
```

Validation rules:

- Every field is optional (`sometimes`) but validated if provided.
- Unique fields ignore the current user ID.

Success response:

- Status: `200 OK`

```json
{
	"message": "User profile updated successfully.",
	"user": {
		"id": 1,
		"name": "new_username",
		"username": "new_username",
		"email": "new@email.com",
		"phone": "11911111111",
		"cpf": "11122233344",
		"admin": false,
		"created_at": "...",
		"updated_at": "..."
	}
}
```

### 4) Update User Password

- Method: `PATCH`
- URL: `/users/{user}/password`

Request payload:

```json
{
	"password": "newStrongPassword123"
}
```

Validation rules:

- `password`: required, string, min 8

Success response:

- Status: `200 OK`
- Message: `User password updated successfully.`

### 5) Update User Admin Status

- Method: `PATCH`
- URL: `/users/{user}/admin`

Request payload:

```json
{
	"admin": true
}
```

Validation rules:

- `admin`: required, boolean

Success response:

- Status: `200 OK`
- Message: `User admin status updated successfully.`

## Validation Error Format

When validation fails, Laravel returns `422 Unprocessable Entity`:

```json
{
	"message": "The given data was invalid.",
	"errors": {
		"email": [
			"The email has already been taken."
		]
	}
}
```

## cURL Examples

### Register

```bash
curl -X POST http://localhost:8000/api/register \
	-H "X-API-KEY: your-secret-api-key" \
	-H "Content-Type: application/json" \
	-d '{"username":"giordanoberwig","email":"giordanoberwig@proton.me","phone":"11999999999","cpf":"12345678901","password":"12345678","admin":false}'
```

### Login

```bash
curl -X POST http://localhost:8000/api/login \
	-H "X-API-KEY: your-secret-api-key" \
	-H "Content-Type: application/json" \
	-d '{"login":"giordanoberwig@proton.me","password":"12345678"}'
```

### Update Profile

```bash
curl -X PATCH http://localhost:8000/api/users/1/profile \
	-H "X-API-KEY: your-secret-api-key" \
	-H "Content-Type: application/json" \
	-d '{"username":"new_username","phone":"11911111111"}'
```

### Update Password

```bash
curl -X PATCH http://localhost:8000/api/users/1/password \
	-H "X-API-KEY: your-secret-api-key" \
	-H "Content-Type: application/json" \
	-d '{"password":"newStrongPassword123"}'
```

### Update Admin

```bash
curl -X PATCH http://localhost:8000/api/users/1/admin \
	-H "X-API-KEY: your-secret-api-key" \
	-H "Content-Type: application/json" \
	-d '{"admin":true}'
```

## Testing

Run API feature tests:

```bash
php artisan test --compact tests/Feature/AuthApiTest.php
```

## Notes

- This API currently uses `API_KEY` header-based protection for all routes.
- Login returns user data but does not issue JWT/Sanctum tokens yet.
- Any client that has the API key can call user update endpoints by user ID.
- There is no per-user authorization policy on update endpoints yet.
- If you want token-based auth next, add Laravel Sanctum and protect update routes with authenticated user context.
