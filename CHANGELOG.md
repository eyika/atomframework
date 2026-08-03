# Changelog

All notable changes to **`eyika/atom-framework`** are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/); the project is **pre-1.0**, distributed as the
moving `dev-main` (and `dev`) branch — no semver tags yet. Entries reference the fixing commit.

## [Unreleased]

### Security

- **BREAKING — `Encrypter` application key.** `key:generate` writes `APP_KEY=base64:…`, but the
  `Encrypter` passed that string to `openssl_encrypt()` verbatim, which truncates it to the cipher's
  key length — so the effective AES-256 key literally began with the constant bytes `base64:`, and
  the remainder was base64 characters (~150 bits, not 256). The key is now decoded to raw bytes and
  its length asserted against the cipher, failing closed. `key:generate` now derives the key from
  `random_bytes(32)` instead of `Str::random(32)` (32 base64-alphabet characters, ~192 bits).
  (`434eef0`)

  There is **deliberately no fallback to the old key** — a fallback would keep the weak key alive
  and defeat the fix. Payloads written under the old behaviour are rejected with
  `The MAC is invalid.`

  **Passwords and JWTs are unaffected**: passwords are hashed (`password_verify`, never encrypted),
  and `JwtGuard` signs with `config('app.key')` directly rather than through the `Encrypter`.
  Remember-me cookies are invalidated but degrade safely — `recall()` catches the failure and the
  user simply logs in again. **If your app encrypts columns at rest you must re-encrypt them**, in
  this order: back up, upgrade the framework, *then* run a re-encryption pass using a self-contained
  legacy decrypt. Re-encrypting **before** upgrading is a silent no-op — `Encrypter::encrypt()`
  resolves to whatever is installed in `vendor/`, so it would re-encrypt with the same weak
  implementation and the upgrade would still break every value. See the key-rotation guide:
  <https://basttyydev.serv00.net/docs/beta/advanced/key-rotation>

### Added
- **Route model binding** — `Model::getRouteKeyName()` selects the column a URL segment binds
  against. It defaults to the model's `primaryKey`, so `/users/{user}` still binds by id; override
  it to bind by a human-readable column instead:

  ```php
  public function getRouteKeyName(): string { return 'slug'; }
  ```
  (`ddd5345`)
- **Validation** — wildcard rule keys: `items.*.name` applies a rule to every element of a
  collection, so repeated line-item payloads can be validated declaratively instead of by hand in
  the controller. Wildcards are expanded against the payload before any rule runs (so every rule,
  built-in or custom, works inside one) and nest left to right (`orders.*.lines.*.sku`). Errors are
  keyed by the concrete path — `items.1.name` — identifying the offending element. A wildcard over
  a missing or empty array expands to nothing, so pair it with `'items' => 'required|array'` when
  the collection is mandatory. (`5b458ee`)

- **Scheduler** — `dailyAt('HH:MM')`, `at()`, `hourlyAt(int $minute)`, and `withoutOverlapping()`
  (flock-based, auto-released if the runner dies). (`00b204d`)
- **Queue** — `queue:work` flags: `--daemon` (stay resident, sleep when idle), `--once`,
  `--max-jobs`, `--max-time`, `--sleep`, `--pipeline`, `--no-overlap-guard`. Adds a per-pipeline
  flock overlap guard and isolates a throwing job (bury + continue) instead of aborting the batch.
  Periodic drain-and-exit remains the default. (`00b204d`)
- **Database** — pluggable SQL grammars via `GrammarFactory::extend()` and
  `config('database.grammars')`; SQLite support, including `PRAGMA foreign_keys` applied per
  connection (honours the connection's `foreign_key_constraints`). (`4b2a6cc`, `e063388`)
- **Auth** — provider drivers resolve the guard provider's own model
  (`config('auth.providers.<provider>.model')`, falling back to the global `auth.user.model`), so
  multiple guards/providers with different user classes work. (`ba98484`)
- **Testing** — `NamespaceHelper::getProjectNamespace()` resolves the application namespace
  independent of test-mode (tries `app/`, then `src/`, then the first psr-4 entry). (`e65d31a`)
- **Migrations** — `migrate --pretend` and `migrate:rollback --pretend` are now implemented: each
  prints the ordered list of migrations that would run / roll back and returns without any
  `up()`/`down()` or migrations-table write (previously the flag was a no-op and silently ran the
  real, destructive operation). (`2ae39bd`)

### Changed
- **Models — `guarded` no longer restricts what is read.** It is an *output* filter (its own
  docblock: "what database attributes of the model can be exposed outside the application"), but it
  was also subtracted from the SELECT column list. A guarded column was therefore never loaded and
  the model's own property was `null`, so application logic could not read its own data — a service
  reading `created_at` off a plain `get()` silently got `null` and computed the wrong result. This
  protected nothing extra: `toArray()` guards by default and is what the JSON response path calls.
  Exposure is unchanged; only hydration is fixed.

  Read paths also now drop `deleted_at` for models with `softdeletes = false`, since the default
  `fillable` lists it for the soft-delete case.

  **Compatibility:** if a model lists a column in `fillable` that does not exist in its table and
  relied on `guarded` to keep it out of the SELECT, reads will now fail with `Unknown column`.
  Correct the `fillable` list — that column was never actually being read. (`879f54e`)
- **Query builder** — a multi-result read that matches **nothing** now returns an empty
  `Collection` instead of `false`. `_all()` bailed on any falsy `fetch()` result, but `fetch()`
  already distinguishes `false` (the cursor failed) from `[]` (ran, matched nothing) — collapsing
  them made an empty read indistinguishable from an error and broke the documented "multi-result
  reads return a Collection" contract exactly when it mattered, so `count()`/`assertCount()` on an
  empty `get()` raised a `TypeError`. A genuine failure still returns `false`.
  **Audit `if (!$rows)` checks**: an empty `Collection` is an object and therefore truthy, so code
  using falsiness to mean "nothing found" must switch to `count($rows) === 0` or `$rows->isEmpty()`.
  `foreach` and `$rows ?: []` are unaffected. (`5b458ee`)
- **Scheduler** — cron matching now uses `config('app.timezone')` (default `UTC`) instead of the
  CLI's php.ini timezone, so `dailyAt('05:00')` fires at the intended app time. (`00b204d`)
- **Migrations** — removed the unimplemented `--force` flag from the `migrate` signature (there was
  no confirmation prompt for it to bypass). (`2ae39bd`)

### Removed
- **Phinx** — the abandoned Phinx integration is gone: the `make:migrations` and `make:seed`
  commands, the `phinxCommander` runner and the `atom_phinx` bin. `robmorgan/phinx` was already
  absent from the dependency list, so both commands were dead on arrival — they shelled out to a
  bin that loads a package that is not installed. Use the framework's own **`make:migration`** and
  **`make:seeder`**, which generate `Schema`/`Blueprint` migrations and `Seeder` classes for the
  built-in migration engine. (`5b458ee`)

### Fixed
- **Migrations — package migration directories were only half-honoured.** `migrate` ran migrations
  from directories registered with `ServiceProvider::loadMigrationsFrom()`, but its siblings did
  not: `migrate:rollback` resolved each migration as
  `base_path("database/migrations/{$class}.php")`, so a package migration that `migrate` had itself
  applied could never be rolled back — it failed with *"Migration file not found"* — and
  `migrate:status` globbed only the app directory, so package migrations never appeared at all.
  Discovery now lives in one place (`Console\Concerns\ResolvesMigrationPaths`) shared by `migrate`,
  `rollback` and `migrate:status`; `migrate:reset`/`migrate:refresh` inherit it via `rollback`. The
  app's directory is still searched first, so ordering is unchanged. (`93ff152`)
- **Query builder / `orderBy()`** — two silent ordering defects, in both the model builder and the
  static `DB` builder. Successive calls **replaced** each other rather than accumulating, so
  `orderBy('is_default', 'DESC')->orderBy('currency')` sorted by `currency` alone; and a comma list
  appended one direction after the whole list, so `orderBy('a,b', 'DESC')` emitted
  `ORDER BY a, b DESC` — i.e. `a` ascending. Terms now accumulate and each column carries its own
  direction. Neither case errored, so a multi-key sort just came back in the wrong order.
  (`aaae9d6`)
- **Schema / indexes** — `dropUnique(['col'])` and `dropIndex(['col'])` could not work on any
  driver but MySQL, for two independent reasons:
  - Index-name resolution ran a hard-coded `INFORMATION_SCHEMA.STATISTICS` query. It is now
    delegated to the grammar — `Grammar::indexNameForColumns()`, with SQLite overriding it to use
    `PRAGMA index_list`/`index_info`.
  - Column-level `->unique()` compiled to an **inline** `UNIQUE` for every grammar. On SQLite that
    creates an implicit `sqlite_autoindex_*`, which SQLite refuses to drop — so the constraint was
    unremovable by any later migration. Where indexes are emitted separately (sqlite/pgsql) a
    column-level unique is now promoted to a named `CREATE UNIQUE INDEX`.

  **MySQL output is unchanged** (it still inlines). On SQLite, `CREATE TABLE` now emits an extra
  `CREATE UNIQUE INDEX "unique_<column>"` statement per column-level unique; uniqueness is
  enforced exactly as before. (`dc7817f`)
- **Route model binding** — four defects on one path, which together meant binding worked only for
  an existing numeric id and failed silently otherwise:
  - Only values passing `is_numeric()` were bound, so a slug or UUID segment reached the controller
    as a raw string — including a model whose **primary key** is a UUID. Every parameter naming a
    model is now resolved against its **route key** (see `getRouteKeyName()` under Added).
  - A genuinely missing row was **silently skipped instead of raising**. `find()`/`first()` return
    `null` on a miss, and `resolveModel()` also used `null` for "this parameter names no model", so
    the not-found branch never ran. The two cases are now distinct.
  - `ModelNotFoundException::__construct()` declared a **required** `array $errors` after an
    optional `$message`, so the middleware's one-argument throw raised `ArgumentCountError` rather
    than the exception (and PHP deprecated the signature). `$errors` now defaults to `[]`.
  - `SubstituteBindings` threw `UnexpectedValueException` when `app/Models` did not exist, so an
    app without that directory **500'd on any route with a parameter**. It now yields an empty map.
  (`ddd5345`)
- **Console** — `artisan test` (and `serve`) built their subprocess command without quoting, so a
  project path containing a space — `C:\Users\Some Name\…` — was split by the shell and PHP reported
  `Could not open input file: C:\Users\Some`. The interpreter, script path and every argument are
  now quoted, and the interpreter is `PHP_BINARY` so the child cannot pick up a different `php`
  from `PATH`. `serve`'s router script was also concatenated onto the option list with no
  separator. (`5b458ee`)
- **Models / casts** — a column cast to `'object'` could not be written. `fill()` runs
  `castAttribute()` on writes as well as reads, so the payload is decoded back into PHP before
  reaching the DB writer and `serializeCastedValues()` re-encodes it just in time — but it only
  handled `is_array()` values, and the `'object'` cast decodes to a `stdClass`. Both `create()` and
  `update()` therefore failed with *"Object of class stdClass could not be converted to string"*.
  Arrays and objects are now both re-encoded. (`6fe90c0`)
- **Error handling** — `ErrorHandler::handleError()` carried four leftover
  `logger()->info('got here now …')` debug calls, the first of them *before* the
  `error_reporting()` check. As PHP's registered error handler this ran on every notice, warning
  and deprecation — and on every `@`-suppressed operation — building a Monolog logger, reading
  config and writing to `storage/logs` each time. Because `logger()` calls `config()`, an error
  raised before config was loadable also fataled inside the handler itself. All four removed.
  (`426aaa9`)
- **Testing** — `Support\Testing\TestCase` (and the internal `IntegrationTestCase`) pointed the
  global facade application at their own booted container and never restored it, so a test running
  after one had `App::make()`/facades — and thus `$this->app->instance($fake)` overrides — resolving
  from the wrong container (order-dependent; passed in isolation). The base now restores the prior
  facade app on teardown; `Facade::setFacadeApplication()` accepts `null` so a "none set" prior
  state restores exactly. (`6f68c86`)
- **Migrations / Schema** — MySQL `CREATE TABLE` emitted no table options, so on a latin1-default
  server (e.g. MariaDB) tables inherited latin1 and rejected multi-byte UTF-8 (`1366 Incorrect
  string value`). A new `tableOptions()` grammar hook now appends
  `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` on MySQL (overridable via
  `database.connections.mysql.{engine,charset,collation}`); sqlite/pgsql emit nothing. (`2ae39bd`)
- **Migrations** — `migrate:status` showed every migration as not-migrated: it compared a name
  string against migration *rows* (`['migration' => name]`). The row set is now flattened to a
  name list before the membership check. (`2ae39bd`)
- **Migrations** — the bootstrap `CreateMigrationsTable` emitted invalid MySQL 8 DDL (a leftover
  debug column chain), making the very first `php artisan migrate` fatal on a fresh database.
  (`c0f2a41`)
- **Testing** — `DatabaseTestCase` / the test-mode `Application` resolved the project namespace
  against `src/` and threw for standard apps whose code lives in `app/`. (`e65d31a`)
- **Auth** — `EloquentDriver`/`DatabaseDriver` discarded the guard's provider and hardcoded the
  global user model, silently authenticating a second provider against the wrong model. (`ba98484`)
- **Query builder** — SELECT column-list identifiers were not quoted, so a column named with a SQL
  reserved word (e.g. `values`) produced invalid SQL / a broken `SELECT  FROM`. `*`, expressions
  (`count(*)`) and aliases still pass through; an empty list falls back to `*`. (`dfcd2c6`)
- **Query builder** — instance `update()`/`delete()` on a model hydrated from a multi-row `get()`
  reused the source query's `WHERE` instead of the row's primary key, silently writing **every**
  matching row. Instance writes are now scoped to the primary key; bulk `Model::where(...)->update/
  delete()` still affects all matching rows. (`2baa046`)

---

*Older history predates this changelog; see the git log.*
