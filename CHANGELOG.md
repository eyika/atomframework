# Changelog

All notable changes to **`eyika/atom-framework`** are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/); the project is **pre-1.0**, distributed as the
moving `dev-main` (and `dev`) branch — no semver tags yet. Entries reference the fixing commit.

## [Unreleased]

### Added
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

### Changed
- **Scheduler** — cron matching now uses `config('app.timezone')` (default `UTC`) instead of the
  CLI's php.ini timezone, so `dailyAt('05:00')` fires at the intended app time. (`00b204d`)

### Fixed
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
