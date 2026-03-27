# Changelog

## [0.3.0] - 2026-03-27

### Added
- Per-model `public` flag — set `"public": true` on a model to allow unauthenticated GET requests to its CRUD endpoints. Write operations always require auth.
- `GET /openapi.json` — live OpenAPI 3.1 spec auto-generated from registered models. Gated behind `API_DOCS=true`.
- `GET /docs` — Scalar API UI served from CDN, no build step or npm dependency. Gated behind `API_DOCS=true`.
- `columnExists()` on `Connection` and all three dialects — used by `SystemMigration` to add new columns to existing databases without re-running migrations.

### Fixed
- `SystemMigration` adds `is_public` column to existing `puwit_models` tables on upgrade without requiring a manual migration.

---

## [0.2.0] - 2026-03-21

### Added
- `SchemaBuilder::alterTable()` — `PATCH /admin/models/{name}` now runs real DDL: `ADD COLUMN` for new fields, `DROP COLUMN` for removed ones. Type changes are rejected with 422.
- Full integration test suite (107 tests across Auth, CRUD, and ModelLifecycle scenarios).
- `Kernel::process(Request): Response` — testable entry point without `send()`.

### Fixed
- CORS: `Access-Control-Allow-Credentials` is no longer set when `CORS_ORIGINS=*` (browser spec forbids wildcard + credentials).
- JWT: `iss` claim is now validated on decode — tokens from other systems sharing the same secret are rejected.
- Config: falsy env values (`'0'`, `''`) were incorrectly replaced with the default value.
- Config: `PUWIT_ROOT` constant was evaluated eagerly in `ConnectionFactory::sqlite()` even when `DB_PATH` was already set, crashing test environments.
- Request: malformed JSON body now returns 400 instead of being silently treated as an empty body.
- Kernel: `OPTIONS` requests to unregistered paths now reach `CorsMiddleware` instead of returning 404.
- QueryBuilder: `update()` and `delete()` throw `\LogicException` when called without a `where()` condition.
- CrudHandler: int, float, boolean, and relation fields are now type-validated on create/update.
- ModelValidator: model names and field names now enforce lowercase-only — prevents case-insensitive column collisions.
- ModelValidator: `default` values are now type-checked against the declared field type.
- UserController: login always calls `password_verify` regardless of whether the user exists (eliminates username enumeration via timing).
- UserController: username is now validated against `/^[a-zA-Z0-9_\-\.]{1,100}$/`.
- ApiKeyController / UserController: scope values are validated on both create and update (not just create).
- ApiKeyController: `expires_at` is validated via `strtotime()` on create and update.
- SchemaBuilder: all identifiers (table names, column names) are now quoted via `dialect()->quoteIdentifier()`.
- SchemaBuilder: `quoteDefault()` uses standard SQL single-quote escaping instead of `addslashes()`.
- RelationResolver: related model table name is now quoted.
- JwtGuard: expired blocklist entries are pruned before each new entry is inserted.
- JwtGuard: `issue()` rejects secrets shorter than 32 characters.
- ModelController: registry entry is deleted before dropping the table in `destroy()`.
- ModelRegistry: removed dead `invalidate()` method.
- Connection: `statement()` return type changed from `bool` to `void`.
- ConnectionFactory (PostgreSQL): added `ATTR_EMULATE_PREPARES => false`.
- SystemMigration: all `CREATE TABLE` statements changed to `CREATE TABLE IF NOT EXISTS` — migration is idempotent and survives partial crashes.

## [0.1.0] - 2026-03-20

Initial release.
