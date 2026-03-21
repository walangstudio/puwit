<div align="center">

# PUWIT

**PHP Universal Web Integration Toolkit**

[![Version](https://img.shields.io/badge/version-0.2.0-blue?style=flat-square)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-22c55e?style=flat-square)](LICENSE)
[![Composer](https://img.shields.io/badge/Composer-PSR--4-F28D1A?style=flat-square&logo=composer&logoColor=white)](https://getcomposer.org)
[![PHPUnit](https://img.shields.io/badge/Tests-PHPUnit%2010-4F46E5?style=flat-square&logo=php&logoColor=white)](https://phpunit.de)

[![MySQL](https://img.shields.io/badge/MySQL-supported-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-supported-336791?style=flat-square&logo=postgresql&logoColor=white)](https://postgresql.org)
[![SQLite](https://img.shields.io/badge/SQLite-supported-003B57?style=flat-square&logo=sqlite&logoColor=white)](https://sqlite.org)

</div>

---

PUWIT lets you define a data model via JSON and get a working REST API back immediately. Post a schema, get a table and six endpoints. No code generation, no restarts.

It has no ORM dependency (dynamic models can't use static class mappings), no third-party router, and no PSR-7 stack. Just PDO, a small trie router, and a middleware pipeline.

---

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+, PostgreSQL 12+, or SQLite 3
- Apache with `mod_rewrite` enabled (standard on most shared hosts)

Composer is only needed on your local machine to install dependencies before uploading.

---

## Installation

### Shared hosting (cPanel, Plesk, etc.)

Most shared hosts give you a `public_html` folder as the web root. The recommended layout is to keep PUWIT's source files outside `public_html` and only expose the `public/` folder:

```
/home/youruser/
├── puwit/              <-- project root (not public)
│   ├── src/
│   ├── vendor/
│   ├── .env
│   └── ...
└── public_html/        <-- or a subdomain folder
    └── (contents of puwit/public/ go here)
```

**Steps:**

1. Clone or download the repository on your local machine:

```bash
git clone https://github.com/walangstudio/puwit.git
cd puwit
composer install --no-dev
```

2. Upload the entire project to your server, for example to `/home/youruser/puwit/`.

3. Copy the contents of the `public/` folder into your `public_html/` directory (or the folder your domain points to). Do not upload `public/` as a subfolder -- its files should sit directly in the web root.

4. Open `public/index.php` and update the path to match where you uploaded the project:

```php
define('PUWIT_ROOT', '/home/youruser/puwit');
```

5. Copy `.env.example` to `.env` inside the project root and fill in your database credentials. Your host's cPanel or Plesk dashboard is where you create the database and get these values.

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=youruser_puwit
DB_USER=youruser_dbuser
DB_PASS=yourpassword

JWT_SECRET=a-random-string-at-least-32-characters-long
PUWIT_ADMIN_KEY=my-bootstrap-key
```

6. Visit your domain. The first request creates all system tables automatically.

`PUWIT_ADMIN_KEY` is a temporary admin key used to create the first real API key. Remove it from `.env` once you have one.

### Local development

```bash
git clone https://github.com/walangstudio/puwit.git
cd puwit
composer install
cp .env.example .env
php -S localhost:8000 -t public/
```

### Composer SSL errors (AMPPS, XAMPP, WAMP, Laragon)

Bundled PHP stacks often ship without a CA certificate bundle, causing `composer install` to fail with an SSL error. Fix it by downloading the Mozilla CA bundle from https://curl.se/ca/cacert.pem, saving it somewhere on your machine, then pointing PHP at it in `php.ini`:

```ini
curl.cainfo = "C:\php\cacert.pem"
openssl.cafile = "C:\php\cacert.pem"
```

Run `php --ini` to find which `php.ini` is active. This is a one-time setup per machine.

---

## Quick start

Replace `https://yourdomain.com` with your actual domain or `http://localhost:8000` if running locally.

**1. Create an admin API key**

```bash
curl -X POST https://yourdomain.com/admin/keys \
  -H "X-API-Key: my-bootstrap-key" \
  -H "Content-Type: application/json" \
  -d '{"label": "dev", "scopes": ["admin"]}'
```

```json
{
  "data": {
    "id": 1,
    "label": "dev",
    "key": "a1b2c3...",
    "scopes": ["admin"]
  }
}
```

The raw key is shown once. Save it.

**2. Define a model**

```bash
curl -X POST https://yourdomain.com/admin/models \
  -H "X-API-Key: a1b2c3..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "product",
    "fields": [
      { "name": "title",    "type": "string",  "nullable": false },
      { "name": "price",    "type": "float",   "nullable": false },
      { "name": "in_stock", "type": "boolean", "nullable": true, "default": true },
      { "name": "notes",    "type": "text",    "nullable": true }
    ]
  }'
```

This creates the `puwit_m_product` table and registers the CRUD routes in the running process.

**3. Use the API**

```bash
# create
curl -X POST https://yourdomain.com/api/product \
  -H "X-API-Key: a1b2c3..." \
  -H "Content-Type: application/json" \
  -d '{"title": "Widget", "price": 9.99}'

# list with pagination and filters
curl "https://yourdomain.com/api/product?page=1&per_page=20&filter[in_stock]=1" \
  -H "X-API-Key: a1b2c3..."

# get one
curl https://yourdomain.com/api/product/1 -H "X-API-Key: a1b2c3..."

# partial update
curl -X PATCH https://yourdomain.com/api/product/1 \
  -H "X-API-Key: a1b2c3..." \
  -H "Content-Type: application/json" \
  -d '{"price": 7.49}'

# delete
curl -X DELETE https://yourdomain.com/api/product/1 -H "X-API-Key: a1b2c3..."
```

---

## Field types

| Type | MySQL | PostgreSQL | SQLite |
|------|-------|------------|--------|
| `string` | VARCHAR(255) | VARCHAR(255) | TEXT |
| `int` | INT | INTEGER | INTEGER |
| `float` | DECIMAL(10,4) | NUMERIC(10,4) | REAL |
| `boolean` | TINYINT(1) | BOOLEAN | INTEGER |
| `text` | LONGTEXT | TEXT | TEXT |
| `datetime` | DATETIME | TIMESTAMP | TEXT |
| `json` | JSON | JSONB | TEXT |
| `relation` | INT UNSIGNED + FK | INTEGER + FK | INTEGER + FK |

Every generated table gets `id`, `created_at`, and `updated_at` automatically.

---

## Relationships

Set a field's type to `relation` and point it at another model:

```json
{
  "name": "review",
  "fields": [
    { "name": "body",       "type": "text",     "nullable": false },
    { "name": "rating",     "type": "int",      "nullable": false },
    { "name": "product_id", "type": "relation", "nullable": false, "relation": "product" }
  ]
}
```

A real foreign key is added to the table. To include the related record in the response, pass `?with=` the relation name (without `_id`):

```bash
curl "https://yourdomain.com/api/review/1?with=product" -H "X-API-Key: a1b2c3..."
```

```json
{
  "data": {
    "id": 1,
    "body": "Great widget",
    "rating": 5,
    "product_id": 1,
    "product": {
      "id": 1,
      "title": "Widget",
      "price": "9.9900"
    }
  }
}
```

---

## Authentication

### API keys

Include the key in every request:

```
X-API-Key: <your-key>
```

Keys are stored as SHA-256 hashes. The raw value is returned once at creation. You can set an expiry date or revoke a key at any time through the admin endpoints.

### JWT

```bash
# log in
curl -X POST https://yourdomain.com/admin/users/login \
  -H "Content-Type: application/json" \
  -d '{"username": "alice", "password": "s3cret123"}'

# use the token
Authorization: Bearer <token>

# log out (invalidates the token server-side)
curl -X POST https://yourdomain.com/admin/users/logout \
  -H "Authorization: Bearer <token>"
```

### Scopes

There are three scopes and they stack:

- `read` -- GET requests on `/api/*`
- `write` -- everything `read` covers, plus POST / PUT / PATCH / DELETE on `/api/*`
- `admin` -- everything `write` covers, plus all `/admin/*` endpoints

---

## Endpoints

**Models**

| Method | Path | Scope |
|--------|------|-------|
| GET | `/admin/models` | admin |
| POST | `/admin/models` | admin |
| GET | `/admin/models/{name}` | admin |
| PATCH | `/admin/models/{name}` | admin |
| DELETE | `/admin/models/{name}` | admin |

**API keys**

| Method | Path | Scope |
|--------|------|-------|
| GET | `/admin/keys` | admin |
| POST | `/admin/keys` | admin |
| GET | `/admin/keys/{id}` | admin |
| PATCH | `/admin/keys/{id}` | admin |
| DELETE | `/admin/keys/{id}` | admin |

**Users**

| Method | Path | Scope |
|--------|------|-------|
| GET | `/admin/users` | admin |
| POST | `/admin/users` | admin |
| GET | `/admin/users/{id}` | admin |
| PATCH | `/admin/users/{id}` | admin |
| DELETE | `/admin/users/{id}` | admin |
| POST | `/admin/users/login` | public |
| POST | `/admin/users/logout` | authenticated (any scope) |

**CRUD (per model)**

| Method | Path | Scope |
|--------|------|-------|
| GET | `/api/{model}` | read |
| POST | `/api/{model}` | write |
| GET | `/api/{model}/{id}` | read |
| PUT | `/api/{model}/{id}` | write |
| PATCH | `/api/{model}/{id}` | write |
| DELETE | `/api/{model}/{id}` | write |

Query parameters for list endpoints:

| Parameter | Default | Notes |
|-----------|---------|-------|
| `page` | 1 | |
| `per_page` | 20 | max 100 |
| `sort` | id | any column |
| `order` | asc | asc or desc |
| `filter[field]` | | exact match |
| `with` | | comma-separated relation names to inline (e.g. `?with=product,category`) |

### Response format

```json
{
  "data": { "id": 1, "title": "Widget" },
  "meta": {
    "model": "product",
    "timestamp": "2026-03-20T12:00:00+00:00",
    "total": 42,
    "page": 1,
    "per_page": 20
  }
}
```

Errors return the same shape without `data`:

```json
{ "error": "Validation failed", "errors": ["price is required"] }
```

---

## Project structure

```
puwit/
├── public/index.php
├── src/
│   ├── Kernel.php
│   ├── Config/Config.php
│   ├── Http/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Router.php
│   ├── Middleware/
│   │   ├── Pipeline.php
│   │   ├── CorsMiddleware.php
│   │   ├── AuthMiddleware.php
│   │   └── ScopeMiddleware.php
│   ├── Auth/
│   │   ├── TokenContext.php
│   │   ├── ApiKeyGuard.php
│   │   └── JwtGuard.php
│   ├── Database/
│   │   ├── Connection.php
│   │   ├── QueryBuilder.php
│   │   ├── SchemaBuilder.php
│   │   ├── Dialects/
│   │   └── Migrations/SystemMigration.php
│   ├── Model/
│   │   ├── FieldDefinition.php
│   │   ├── ModelDefinition.php
│   │   ├── ModelRegistry.php
│   │   ├── ModelValidator.php
│   │   └── RelationResolver.php
│   ├── Crud/
│   │   ├── CrudHandler.php
│   │   └── CrudRouter.php
│   └── Admin/
│       ├── ModelController.php
│       ├── ApiKeyController.php
│       └── UserController.php
└── tests/
    ├── Unit/
    └── Integration/
```

---

## System tables

These are created on first boot and should not be modified directly.

| Table | Contents |
|-------|----------|
| `puwit_models` | model name, table name, fields and relations as JSON |
| `puwit_api_keys` | key hash, scopes, expiry, revoked flag |
| `puwit_users` | username, bcrypt password hash, scopes, active flag |
| `puwit_jwt_blocklist` | invalidated token IDs and their expiry times |

User-created tables are prefixed `puwit_m_`.

---

## Tests

```bash
./vendor/bin/phpunit --testdox
```

---

## Intended use

PUWIT is designed for **personal projects and small internal tools** where you control all API consumers. It is not suitable for:

- **Multi-tenant applications** — there is no row-level ownership. Any client with `write` scope can edit or delete any record, regardless of who created it.
- **Public-facing user-generated content** — PUWIT has no concept of end-user identity tied to records. `puwit_users` is for admin accounts only, not application end users.
- **Fine-grained access control** — scopes are global (`read`/`write`/`admin`). There is no per-model, per-record, or per-user permission system.

If you need any of the above, put a backend in front of PUWIT that enforces your ownership and access rules, and use PUWIT purely as the data layer.

---

## Security notes

- `JWT_SECRET` must be at least 32 characters. firebase/php-jwt will reject shorter keys.
- Remove `PUWIT_ADMIN_KEY` from `.env` once you have a real admin key in the database.
- Set `APP_DEBUG=false` in production. When debug is on, stack traces are included in error responses.
- Run over HTTPS in production. Both API keys and JWTs are bearer credentials and should not travel over plain HTTP.
- Model names and field names must be lowercase (`/^[a-z][a-z0-9_]+$/`). This prevents case-insensitive column collisions on MySQL.
- The `puwit_jwt_blocklist` table is pruned automatically on every logout call (expired entries are deleted before the new one is inserted).

---

## License

MIT
