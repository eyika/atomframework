# Atom Framework — Claude B

> **Scope note.** The workspace root `backtestfx/CLAUDE.md` also loads, and it opens *"You are
> Claude A, owner of this whole workspace **except `eyika/`**"*. **This file is the exception it
> refers to.** Inside `eyika/` you are **Claude B** and this file wins.
>
> It lives here (rather than at `eyika/`) so it is version-controlled and survives a re-clone —
> `eyika/` itself is gitignored by the parent repo, so a loose file there is backed up nowhere.
> It governs **all** of `eyika/`, not just this repo; if you are working in `eyika/website` or
> `eyika/atom`, it will not auto-load, so read it first.

Paths below are written relative to the **workspace root** (`backtestfx/`) unless stated. This repo
is `backtestfx/eyika/atomframework`.

## Scope

You own **`eyika/` only** — the Atom PHP framework, its companion packages, and its docs site.

- **Claude A** owns the rest of `backtestfx/` (FxTester, fx-data-server, live-engine).
- **Claude C** owns `vendra/`, a sibling of `backtestfx/`.

Don't edit *or read* their files — including their `.env`s. Watch relative paths: `../` from this
repo is `eyika/`, and `../../fx-data-server` is **Claude A's**, not an unrelated copy. When a real
app build surfaces a framework bug it comes to you; when your change needs an app-side migration,
hand that to the owning Claude rather than doing it yourself.

Atom is Laravel-*inspired* — **similar to Laravel, but not Laravel**. See Gotchas.

## The repos (all separate git repos on `github.com/eyika`, all under `eyika/`)

| Dir | Package | What it is | Branches |
|---|---|---|---|
| `atomframework` | `eyika/atom-framework` | **The core framework — this repo, your main codebase.** | `db/driver-grammars` (working), `main`, `dev` |
| `atom` | `eyika/atom` | Starter skeleton (`composer create-project eyika/atom`). Pins `dev-main`. | `main` |
| `atom-octane` | `eyika/atom-octane` | Octane-style persistent worker (boot once, serve many). | `main` |
| `atom-reverb` | `eyika/atom-reverb` | Dependency-free WebSocket broadcast server. | `main` |
| `website` | built on `eyika/atom` | Docs site → <https://basttyydev.serv00.net> | `main` |

`eyika/` itself is **not** a git repo (the parent ignores it), so these live side by side but
version independently. `atom-octane` and `atom-reverb` have **no tests of their own** — theirs live
in this repo's suite (`OctaneWorkerTest`, `ReverbProtocolTest`, `ReverbProductionTest`).

## Branch invariant — read before touching the framework

Pre-1.0: no tags, shipped as **moving branches**. Consumers pin one:

- **`dev-main` → `main`** — the `atom` skeleton, `website`, and Vendra (`vendra/api`).
- **`dev-dev` → `dev`** — fx-data-server (`backtestfx/fx-data-server`).

**Every framework change must land on BOTH `main` and `dev`**, or whichever consumer pins the other
branch silently never receives it. Workflow:

1. Author + test on **`db/driver-grammars`**, commit.
2. `git fetch origin`; for each of `main` and `dev`:
   `git reset --hard origin/<b>` → `git cherry-pick <sha…>` → `git push origin <b>`.
3. Push `db/driver-grammars` too.
4. `composer update eyika/atom-framework` in the consumers you own.

No PRs for these. Cherry-picking means the three branches have **different SHAs for the same
change** — so verify sync by comparing `git rev-parse <branch>^{tree}`, not commit ids.

Before any `reset --hard`, confirm the branch has no local-only commits
(`git rev-list --count origin/<b>..<b>`). And commit with `git commit -F <file>`: PowerShell
here-strings mangle messages containing quotes.

## Standing rules

1. **Contracts & facades stay in sync.** A new public method on a framework class updates its
   `Contract` interface *and* the Facade `@method` docblocks in the same change.
2. **Changelog convention.** Every *consumer-visible* change updates **both** `CHANGELOG.md`
   (Keep-a-Changelog, under `[Unreleased]`, referencing the fixing commit sha) **and**
   `../website/app/docs/beta/changelog.md`. Commit the fix first, then a `docs(changelog)` commit
   citing its sha. Purely internal changes (this repo's own tests, tracker edits) don't need an
   entry — say so rather than inventing one.
3. **Beta → v1.0.0 — standing authorization.** The user has said: *"when it's time to bump this
   framework from Beta to v1.0.0 or so, don't hesitate to suggest it or do it."* Readiness = real
   app builds stop surfacing new framework bugs and the suite stays green. Bump mechanics: tag
   `v1.0.0` on `main`, move `[Unreleased]` → `[1.0.0] - <date>`, let apps pin `^1.0`, consider the
   skeleton's `minimum-stability` beta→stable, announce on the docs changelog page.
4. **Self-documenting code is ground truth.** Docblocks and descriptive names carry intent; external
   docs only for what no single file shows. Match the surrounding style.
5. **Only change docs when a doc made a false claim.** A bug the docs described correctly needs no
   doc edit; a bug they mis-described gets a correction alongside the fix.
6. **Verify before fixing.** `FRAMEWORK_HARDENING.md` entries are audit notes, and several have
   turned out to be stale or misdiagnosed. Re-read the code first; if it's already fixed, tick it
   with the commit that did it. If the diagnosis is wrong, correct the entry as part of the fix.

## Testing

```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Feature
vendor/bin/phpunit --filter <Name>
```

**Read the skip count, not the exit status** — a suite full of skips still prints `OK`. Both suites
should report **zero skips**; anything else means the environment is wrong, not that the tests
passed. Run both after every change; nothing lands red.

- **Unit** includes in-memory SQLite — needs **`pdo_sqlite`**, or those tests skip silently.
- **Feature** needs a **live MySQL**, database `allshare`, user `root`, empty password (fixture
  default in `tests/Fixtures/app/config/database.php`; bootstrap `tests/bootstrap.php` points
  `$GLOBALS['base_path']` at `tests/Fixtures/app`).
- Requires **PHP 8.4**.

## Framework gotchas (Laravel-like, but NOT Laravel)

- Model query methods work **directly** — `getBuilder()` is optional. `DB::table(...)` returns
  **arrays**; models return **Collections**.
- **Reads that match nothing return an empty `Collection`**, not `false`. `false` now means the
  query genuinely failed. An empty Collection is an object and therefore **truthy** — use
  `count($rows) === 0` / `$rows->isEmpty()`, never `if (!$rows)`.
- **`find()`/`first()`/`firstWhere()` return `null` on a miss.** The static `DB::first()` still
  returns `false` — the two builders differ here.
- **`guarded` is an OUTPUT filter only** (applied by `toArray()`), and does not restrict what is
  read. Don't reintroduce it into a SELECT column list.
- **No auto constructor DI** — resolve with `App::make(...)`.
- **No** `FormRequest` / `Gate` / `Policy` / `Resource` base classes.
- **Cache is PSR-6** (no `remember()`).
- **Queue is MySQL-only, drain-and-exit** — run via cron (`queue:work`); flags `--daemon`, `--once`,
  `--max-jobs`, `--max-time`, `--sleep`, `--pipeline`, `--no-overlap-guard`.
- **Don't call `config()` inside files under `config/`.**
- **`env()` reads `$_ENV` only**, and php.ini ships `variables_order="GPCS"` (no `E`) — exported
  shell vars never reach config. **No `.env` is loaded during tests at all**: `Application` skips
  dotenv when `$isRunningInTestEnv`, and every test path passes `true`. To give the Feature suite
  non-default credentials use PHPUnit's `<env>` element, not `.env` and not shell exports.
- **Hashing vs encryption vs `getHash()`** — three different things. `Hash::make()` is a one-way
  password hash, independent of `APP_KEY`. `Encrypter` is reversible and keyed by `APP_KEY`.
  `getHash()` is a keyed HMAC for the lookup replicas that make encrypted columns queryable —
  changing `APP_KEY` invalidates those replicas **silently**.
- **Testing base classes:** `Support\Testing\DatabaseTestCase` is a **minimal boot** (DB Connection
  only, no providers). If a DB test needs Cache/Storage/Auth facades, call
  `$this->app->registerProviders()` in `setUp()`, or extend `Support\Testing\TestCase`.

## Docs website (`eyika/website` → basttyydev.serv00.net)

**Push to `main` IS the deploy** — treat it as an outward-facing action and confirm before pushing.
The chain (don't assume it's "just a `git pull`"):

1. GitHub webhook → `app/Http/Controllers/DeployController.php`, which **cannot** run git/composer
   (serv00 disables PHP `exec`). It only writes the flag `storage/deploy.request`.
2. A serv00 cron runs **`deploy.sh`** (tracked, repo root) each minute: no-ops without the flag,
   takes a PID lock, consumes the flag, `git pull --ff-only origin main`.
3. It runs **`composer install` only when `composer.json`/`composer.lock` changed** in that pull,
   then `artisan vendor:publish --tag=atom-assets --force`.

So a **framework bump does reach production** — `vendor/` is gitignored and never pulled, but it is
rebuilt from the lock on the server. Markdown-only pushes skip Composer by design. `deploy.sh`
forces PHP 8.4 from `~/bin` (cron's PATH resolves to 8.3). Logs: `storage/logs/deploy.log`.

serv00 SSH is **2FA-gated**; git-ftp and GitHub Actions don't work. `composer.lock` **is** tracked
there (the only consumer where it is). Content lives in `app/docs/beta/*.md`, nav order in
`config/navigation.php`, rendered by `app/Helpers/CustomParsedown.php`.

## Consumers to refresh after a framework change

All four are yours: `composer update eyika/atom-framework`. `atom` and `website` pin `dev-main`;
`atom-octane` and `atom-reverb` pin `*`. Only `website` tracks its lock (→ a commit, → a deploy);
the others gitignore it and leave no commit.

**Check how far each has drifted, not just whether it has your latest change** — consumers have
been found ~40 commits behind, missing security fixes nobody noticed were absent.

## New machine

1. **PHP 8.4** + a **current Composer** (`composer self-update` first — an old Composer rewrites a
   tracked `composer.lock`'s metadata *backwards* and it then ping-pongs between machines).
2. Enable **`pdo_sqlite`** and **`pdo_mysql`**; verify with
   `php -r "print_r(PDO::getAvailableDrivers());"` — expect `mysql` **and** `sqlite`.
3. A local **MySQL** — and actually start it after a reboot (Laragon doesn't autostart).
4. `composer install` in each `eyika/*` repo you work in, then
   `composer update eyika/atom-framework`.

**Diagnose MySQL errors, don't assume auth:** `ERROR 1045 access denied` = server up, credentials
wrong; `ERROR 2003 can't connect` = server down. Opposite fixes. And the client resolving
`localhost` to IPv6 gives a misleading `2003` while the server listens happily on IPv4 — try
`-h 127.0.0.1` before concluding anything.

## Where the live state lives

Don't record status here; it goes stale — that is what rotted the file this replaced. Instead:

- **`CHANGELOG.md`** — what changed and why, per commit sha.
- **`FRAMEWORK_HARDENING.md`** — the audit tracker: every SEC-/BUG-/PERF-/PKG-/WRK- item, its
  verdict, and the reasoning behind anything deliberately deferred.
- **`git log`** — branch heads.
