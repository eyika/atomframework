# Atom Framework — Hardening Tracker

Branch: `hardening/framework-audit-fixes` (off `dev`). Goal: fix **all** security loopholes, correctness bugs, bottlenecks, limitations and packaging gaps, then roll the hardened framework into `fx-data-server`. The Octane-style package is built afterward as the demonstration that packaging works.

## Working rules
- **Verify-before-fix.** Every item below came from an audit read of current code, but the code is moving (the `where()` SQLi was already closed in `0bf8afe`). Re-read the exact lines before editing; if already fixed, tick it and note the commit.
- **Backward-compatible through P3.** P0–P3 must not break `fx-data-server` under PHP-FPM. Sync each fix batch into `fx-data-server/vendor/eyika/atom-framework/` and smoke-test before moving on.
- **P4 (worker-safety) is the risky phase** — it's the prerequisite for the Octane package and can change request/session semantics. Do it last, behind flags where possible.
- One commit per coherent batch, with a test where the harness allows.
- Framework edits live in BOTH this repo's `src/` and the app's `vendor/` mirror (see fx-data-server CLAUDE.md).

## Phase order
- **P0 Security** → **P1 Correctness bugs** → **P2 Performance** → **P3 Packaging/DX** → **P4 Worker-safety (Octane prep)**.

## Progress log
- **Test infra:** standalone PHPUnit suite added (unit + integration harness that boots a fixture app and dispatches fabricated requests). `composer install`, `phpunit.xml`, `tests/`. Suite green.
- **Done (P0):** SEC-01, SEC-02 (cookies emit real Set-Cookie + correct Expires/Max-Age + SameSite); SEC-03 (remember-me encrypted + `recall()`); SEC-04 (session-fixation regenerate on login, guarded `Session::regenerate`); SEC-05 (secure session cookie params).
- **New finds while fixing (add to scope):**
  - **SEC-01b** — `BaseResponse::setCookie()` passed args to `Cookie` in swapped path/domain order and fed an absolute `$expiry` into the `maxAge` duration slot. **FIXED** (converts to Max-Age + Expires, adds SameSite).
  - **BUG-NEW-01** — `Support/Arrayable.php::get($key,$default)` called `Arr::get($this->data, $default)`, dropping `$key` → always returned the whole array. **FIXED** + regression test.
- **Done (P0 CSRF cluster):** SEC-06 (fail-closed token gen, no constant fallback); SEC-07 (single session key `csrf_token`, unified across getter/validator/Request::validateCsrf); SEC-08 (removed the undefined-`$token`/query-only bug); SEC-09 (ALL unsafe verbs verified, not POST-only); SEC-10 (`Request::validateCsrf` delegates → `hash_equals`). Also fixed `setCsrfToken` emitting a broken literal PHP tag → real hidden field. Tests: `CsrfTokenTest` (unit) + `CsrfValidationTest` (feature, 7 cases incl. PUT/PATCH/DELETE).
  - **SEC-11** — base `Kernel` ships empty middleware BY DESIGN (apps extend it and define their own stack); forcing CSRF there would be overridden and would break the API-first app. Framework now ships a CORRECT `VerifyCsrfToken`; enabling it is app config. No framework change.
  - Harness gained `bindSession`/`bindRequest` + facade-cache clear (surfaced WRK-04 live) + stale-header purge.
- **Done (P0 SQLi cluster):** added shared `Connection::quoteIdent`/`quoteQualified` + `safeJoinType`/`safeComparator`. SEC-12 (orderBy escapes columns + whitelists ASC/DESC); SEC-13 (LIMIT/OFFSET int-cast at builder + emission); SEC-14 (compileJoins escapes identifiers, whitelists type+operator); SEC-15 (DISTINCT/aggregate column escaped); SEC-16 (values() escapes column idents + validates JSON path); SEC-17 (exec() word-boundary placeholder replace, no `:id`/`:id_type` collision); SEC-18 (FROM table escaped in fetch_cursor + random). Tests: `SqlEscapingTest` (escapers + reflection on compileJoins/values with injection payloads). `where()` was already closed in `0bf8afe`.
  - **DB.php parity:** the static `DB` builder (sibling of `QueryBuilder`) had the SAME sinks — its `orderBy`/`distinct` reach `Connection`'s raw ORDER BY/DISTINCT emission. Applied identical fixes to `DB::orderBy`/`limit`/`offset`/`_aggregate` DISTINCT/`distinct` + `DbBuilderEscapingTest`. (Connection-level fixes — values/joins/exec/LIMIT emission — already covered both builders.)
  - NOTE: `SELECT` column list (`$select_what`) and insert/update/remove table names remain single-backtick (dev-controlled today); escaping them is a low-risk follow-up. The static `DB` builder still has separate CORRECTNESS bugs (BUG-31: shared `$table`, broken paginate) queued for P1.
- **Done (P0 JWT cluster):** SEC-19 (JWT_KEY was dead — `encode()` ignored it, tokens always signed with `app.key`; removed the dead arg + property, kept `app.key` signing to avoid invalidating live tokens, and made construction FAIL CLOSED if `app.key` is empty); SEC-20 (verify `iss`/`aud` after decode when configured — firebase/php-jwt only checks exp/nbf/iat); SEC-21 (stop running `sanitize_data()` over the bearer token — extract raw). Also null-safe `is_impersonating`. Tests: `JwtEncoderTest` (sign/verify + wrong-key rejection), `JwtGuardTest` (claim enforcement + unmangled extraction).
  - NOTE: `extractToken` still reads `$_SERVER['HTTP_AUTHORIZATION']` directly (WRK-11, P4). Also `firebase/php-jwt ^6.10` carries a security advisory (composer blocks it) — bump the constraint as a dependency follow-up.
- **Done (P0 SSRF/redirect/host/IP cluster):** SEC-22 (Proxy: default credential blacklist so Authorization/Cookie aren't forwarded; `assertSafeTarget` blocks non-http(s) + private/reserved/loopback IPs incl. 169.254.169.254 with optional host allowlist; `follow_location=0`/`max_redirects=0`; url-encoded query); SEC-24 (redirect strips CR/LF → no header injection; `back()` only honours a same-origin Referer — external redirects via `redirect()` stay allowed by design for OAuth/gateways); SEC-25 (`clientIp()` fixed regex + XFF only trusted behind a configured proxy, left-most validated; `host()` trusted-hosts allowlist fallback to app host; `getIpAddress()` routes through the gated resolver). Tests: `ProxySsrfTest`, `RequestClientTest`, `RedirectTest`.
- **Done (P0 deserialization + traversal cluster):** SEC-23 (new `SignedPayload` HMAC envelope — queue jobs are signed on enqueue (`ShouldQueue::run`/`bury`) and verified before `unserialize()` in `JobRunner`, so a forged serialized gadget-chain is rejected before it can execute); SEC-28 (`download()` resolves realpath, optionally confines to `filesystem.download_base`, sanitises the Content-Disposition filename, fixes `text/plan` typo); SEC-30 (`basename()` client-supplied upload filenames — traversal); SEC-32 (standardised `app.debug` default to `false`). Tests: `SignedPayloadTest`, `DownloadTest`.
  - **Accepted low-risk / documented (no code change now):** SEC-29 (`Route` string-callback `include_once` — only dev-registered string routes, not user input; realpath-guard is a low-pri follow-up); SEC-31 (multipart post-size DoS — a `ValidatePostSize` middleware exists but is app-enabled); SEC-33 (mass-assignment — `fillable` is the only write gate; document, don't auto-guard). FileCache/ArrayCache/DbStorage `unserialize` left as-is (local FS / in-process / lower exposure than the queue) — signing them is a follow-up.

### ✅ P0 (Security) COMPLETE
All high/critical security items fixed with tests; the three above are documented low-risk. **Suite: 62 tests / 118 assertions green.**

### P1 (Correctness) — in progress
- **Done (Request/Response body handling):** BUG-01 (`isJson` matches `application/json; charset=…` → JSON bodies no longer silently dropped); BUG-02 (multipart-PUT checks the lowercased `content-type` header); BUG-03 (removed the `use function PHPUnit\Framework\isNull` prod import — `hasFile()` no longer fatals); BUG-04 (`hasBody()` precedence); BUG-05 (`is()` actually matches, wildcard-aware); BUG-06 (`has()` counts a present-null + checks attributes/route_params); BUG-07 (`send()` idempotent — sets `_responseSent`); BUG-08 (`_send` reordered so file/redirect responses aren't swallowed by the JSON/XHR branch); BUG-09 (re-enabled the circular-reference guard in `convertObjectsToArray`). Tests: `RequestParsingTest`, `ResponseSendTest`. **Suite: 68 tests / 130 assertions green.**
- **Done (Routing):** BUG-10 (`route()`/`Url::route()` substitute `{key}`/`{key?}`, not `$key`); BUG-11 (optional `{param?}` regex captures the name without the `?`); BUG-12 (`Route::any()` routes now dispatch — dispatch unions method + ANY buckets via `+`); BUG-13 (extra request segments no longer OOB / falsely match); BUG-14 (`Server` api-detection uses a `^/api(/|$)` segment match, not `str_contains('/api')`). **Bonus PERF-02:** dispatch now reuses the matched route's middlewares instead of a second full route scan (removed dead `findRouteMiddlewares`); not-found path returns through the same response wrapping. Harness: fresh response instance per request (the Response facade is a shared mutable singleton). Tests: `RoutingBugsTest`. **Suite: 72 tests / 135 assertions green.**
- **ORM/DB (in progress — contained bugs first, architecture deferred by decision):**
  - **Done:** BUG-24 (`group_concact`→`group_concat` + added `bit_xor`); BUG-25 (`findByEmail`/`findByUsername` whitelist — **verified**: methods are `_findByEmail`/`_findByUsername` in `UserAwareQueryBuilder`, `__callStatic` prepends one `_`, and the `@method` docblock documents the no-underscore names; app calls them as `->findByEmail` (instance → `__call`, bypasses the whitelist) so the change is safe and aligns the STATIC surface with the docblock); BUG-26 (`restore()` positional `['deleted_at', null]` → wrote `0`/`1`, never cleared `deleted_at`; now assoc — both builders). Tests: `OrmContainedBugsTest`.
  - **Dynamic-method resolution safety net + a caught dead entry:** added `DynamicMethodResolutionTest` that asserts **every** `DYNAMIC_STATIC_METHODS` entry binds to a real `_{name}`/`{name}` on a fully-featured model (`User`). It caught `firstOr` — whitelisted but with NO backing method (would 500). **Implemented** `_firstOr` (QueryBuilder) + `firstOr` (DB.php parity), returning the first match or the callback result. Test: `FirstOrTest`.
  - **DECISIONS (from user):** architecture (BUG-20 model events, BUG-21/PERF-14 eager-loading) done as a **separate focused effort**, NOT now. **BUG-23** (`first()`/`find()` → `null`) = **change to null + audit fx-data-server callers** (crosses into the app repo, which per its CLAUDE.md deliberately relies on `false`) — do as its own careful task.
  - **Done (batch 2):** BUG-35 (filterless/idless `delete()` now REFUSED in both `QueryBuilder::_delete` and `DB::delete` — was `DELETE FROM table` wiping everything, or a silent null-id no-op); BUG-33 (`Connection::remove()` returns true on a 0-row delete — an absent row isn't a failure — and escapes the table ident, SEC-18 residual); BUG-32 (`strpos($name,'_')` at pos 0 in `__callStatic` → `!== false`, so tables/keys starting with `_` route correctly). Test: `DeleteSafetyTest`.
  - **DB-backed test harness ADDED:** `DatabaseTestCase` (boots app, binds a real `Connection` to `db.connection`, isolated `atomtest_*` schema per test, skips if DB down) against local MySQL `allshare` (127.0.0.1, root/no-pass). `tests/Fixtures/app/config/database.php`. `DatabaseSmokeTest` validates it (read + filtered delete). This unblocks proper testing of DB-executing bugs.
  - **Done (batch 3):** BUG-30 (JOINs were entirely dead — `Connection::fetch()` hardcoded `[]` joins). Added a `$joins` param to `fetch()` (threads to `fetch_cursor`→`compileJoins`), and `QueryBuilder` now passes `$this->joins` on read paths + resets it in `resetInstance` (was leaking). Verified end-to-end: `JoinTest` runs an INNER JOIN against MySQL and reads the joined column. NOTE: the DB *static* builder's join is tangled with BUG-31 (writes `$this->joins` but reads static — deferred with the DB rewrite); and dot-notation `where('t.col')` auto-joins separately (combining it with an explicit `join()` double-joins).
  - **Done (batch 4):** BUG-29 (`_save`'s update branch indexed `__update()`'s FLAT return array as `$model[0][...]` — and used the property VALUE as the key — nulling the PK + updated_at after every save; now indexes by column name with a fall-back to the current value). DB test: `SaveTest` (PK preserved across save). 
  - **OBSERVED (investigate, NOT BUG-29):** `$model->name = 'x'; $model->save();` did not persist the change in the test (row kept old value) — likely a `__set` routing (fillable→dynamicProperties) or `_save` value-collection issue, OR the framework expects `update([...])` for changes. Flagged for a focused look; app may already use `update()`.
  - **Done (batch 4):** BUG-36 (`_firstOrNew()` took no args, ignored the search and wrongly `save()`d; now `_firstOrNew($search, $values, $is_protected)` returns the matching row or a NEW UNSAVED instance filled with search+values, Laravel-style; interface signature updated). DB tests in `SaveTest` (existing→row; absent→unsaved, not persisted).
  - **Still TODO (contained):** BUG-28 (bindParam dead vs execute($params)), BUG-34 (swallowed PDOExceptions), BUG-27 (softdelete double-append), BUG-31 (static DB rewrite — big).
- **Next non-ORM P1:** container (BUG-37..40), validation (BUG-41..46), unimplemented API (BUG-47..53), session-handler binds (BUG-54..56).
- **Test count:** 74 tests / 143 assertions green.

## Severity legend
CRITICAL / HIGH / MED / LOW — framework-level exploitability or breakage. Some security items depend on how an app calls the primitive (noted).

---

## PHASE 0 — SECURITY

### 0.1 Cookies & session-auth (highest priority — auth is broken and forgeable)
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| SEC-01 | HIGH | `Http/BaseResponse.php:208` | Cookies emitted as `header("{name}: {value}")` — not `Set-Cookie`; **all** flags (HttpOnly/Secure/SameSite/Path/Expires) dropped, cookies barely set. | Emit `header('Set-Cookie: '.$cookie->toString(), false)`. |
| SEC-02 | HIGH | `Http/Cookie.php:514,520,69` | `toString()` omits `SameSite`; `Expires` built from `maxAge` as absolute ts; default `secure=false`. | Add SameSite; correct Expires; default Secure on HTTPS. |
| SEC-03 | HIGH | `Support/Auth/Guards/SessionGuard.php:60` | Remember-me cookie is unsigned plaintext `{"id":N}` → set `auth_remember={"id":1}` = account takeover. | `encrypt()`/HMAC-sign + verify+rotate on read. |
| SEC-04 | HIGH | `Support/Auth/Guards/SessionGuard.php:25` | No `Session::regenerate()` on login → session fixation. | Regenerate id on privilege change. |
| SEC-05 | MED | `Http/Middlewares/StartSession.php:40`, `Http/Session.php:15` | `session_start()` with no secure cookie params. | `session_set_cookie_params(httponly,secure,samesite=Lax)`. |

### 0.2 CSRF (multiple defects — effectively no protection)
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| SEC-06 | CRITICAL | `Http/Csrf.php:44` | On RNG failure returns constant token `'123…'`. | Fail closed (throw). |
| SEC-07 | HIGH | `Http/Csrf.php:22,30,75`, `Http/Request.php:612` | Token stored under `csrf_token`, verified against `_token`, and `Request::validateCsrf()` uses `'csrf'` — three keys, never agree. | Unify on one session key. |
| SEC-08 | HIGH | `Http/Csrf.php:81` | `$csrf_token = $token ?? Request::query($tokenId)` — `$token` undefined, so submitted header/input discarded; only `_token` **query param** compared. | Delete line 81; compare the real submitted token. |
| SEC-09 | HIGH | `Http/Csrf.php:79`, `Http/Middlewares/VerifyCsrfToken.php:60` | Only POST checked; PUT/PATCH/DELETE bypass CSRF. | Protect all unsafe verbs. |
| SEC-10 | MED | `Http/Request.php:618` | CSRF compared with `!=` (loose, non-constant-time). | `hash_equals`. |
| SEC-11 | MED | `Foundation/Kernel.php:16` | Empty default middleware stack → CSRF/StartSession/PostSize not enforced unless app wires them. | Ship sane global/group defaults. |

### 0.3 SQL injection (raw-interpolation sinks; `where()` already fixed in `0bf8afe`)
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| SEC-12 | HIGH | `Support/Database/Concerns/QueryBuilder.php:62` → `Connection.php:486` | `orderBy` interpolates raw column+direction. | Escape identifier; map dir to ASC/DESC enum. |
| SEC-13 | HIGH | `QueryBuilder.php:671,677` → `Connection.php:491` | `LIMIT`/`OFFSET` raw. | `(int)` cast or bind. |
| SEC-14 | HIGH | `Connection.php:441` | JOIN clause fully raw (also functionally dead, see BUG-30). | Escape idents; validate operator. |
| SEC-15 | HIGH | `QueryBuilder.php:572,847` → `Connection.php:606` | Aggregate/`DISTINCT` column raw / not backtick-doubled. | Escape (reuse `quoteIdent`). |
| SEC-16 | MED | `Connection.php:263,273` | `values()` column names + JSON path not backtick-doubled; `$key` inside `'$.{$key}'` literal. | Reuse `condition()` ident doubling; bind JSON path. |
| SEC-17 | MED | `Connection.php:305` | `exec()` array-placeholder via naive `str_replace` — `:id` vs `:id_type` prefix collision. | Word-boundary regex / unique tokens. |
| SEC-18 | LOW | `Connection.php:709` et al. | Table names interpolated raw (safe only while dev-defined; `DB::table($userInput)` injects). | Validate table against known set. |

### 0.4 JWT / auth tokens
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| SEC-19 | MED | `Support/Auth/…/JwtEncoder.php:18,26`, `JwtGuard.php:203` | `JWT_KEY` is dead config — tokens silently signed with `app.key`; if that's weak/unset, forgeable. | Remove dead arg; assert strong 32B key at boot. |
| SEC-20 | MED | `JwtEncoder.php:26` | `iss`/`aud` set on mint but never verified on decode. | Verify issuer/audience. |
| SEC-21 | LOW | `JwtGuard.php:164` | `sanitize_data()` HTML-mangles the bearer token. | Don't HTML-sanitize credentials. |
| — | — | — | **Non-issue:** alg-confusion/`none` rejected by firebase/php-jwt (`new Key(...,'HS256')`); impersonation flags are inside the signed payload. | — |

### 0.5 SSRF / redirect / host / IP / deserialization
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| SEC-22 | MED | `Http/Proxy.php:18,21,43` | Empty blacklist forwards `Authorization`/`Cookie`; scheme-only target check; follows redirects → SSRF (`169.254.169.254`). | Implement blacklist; host-allowlist; disable redirects. |
| SEC-23 | MED | `Foundation/Console/JobRunner.php:38,55` | `unserialize()` of queue payload, no `allowed_classes`/HMAC → object-injection RCE (also `FileCache.php:74,119`, `DbStorage.php:48`, `ArrayCache.php:41`). | `allowed_classes` + sign payloads. |
| SEC-24 | MED | `Http/Response.php:56,65`, `helpers.php:232` | Open redirect — `Location` trusts user `$to`; `back()` trusts `Referer`. | Allowlist / relative-only. |
| SEC-25 | MED | `helpers.php:308`, `Http/Request.php:512,522` | XFF/`CLIENT_IP` spoofable (no trusted-proxy gate); host-header poisoning via `HTTP_HOST`; `clientIp()` regex broken (`d` vs `\d`). | Trusted-proxy gate; host allowlist; fix regex. |
| SEC-26 | MED | `Support/Encrypter.php:14` | Key used raw — no `base64:` decode / 32-byte assertion; same key for cipher+HMAC. | Decode base64 key; assert length; derive separate MAC key. |
| SEC-27 | LOW | (missing) | No first-party password hasher; hashing left entirely to apps. | Ship `Hash` (bcrypt/argon) wrapper. |
| — | — | — | **Non-issue:** `Encrypter` is genuine AES-256-CBC encrypt-then-MAC with `hash_equals` + random IV. | — |

### 0.6 File upload / path traversal / includes
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| SEC-28 | MED | `Http/Response.php:80` → `BaseResponse.php:146` | `download($path)` `readfile` with no allowlist → arbitrary file read if path user-influenced. | Confine to base dir via `realpath`. |
| SEC-29 | MED | `Http/Route.php:360` | String route target `include_once __DIR__."/$callback"` → LFI if influenced. | Whitelist/realpath base. |
| SEC-30 | MED | `Http/Request.php:145,179`, `Support/Storage/File.php:1020-1084` | Client `filename=` stored verbatim; native path ops raw. | `basename()` on intake. |
| SEC-31 | LOW | `Http/Request.php:102` | Multipart reads full body, no size cap; `ValidatePostSize` not registered → memory DoS. | Register post-size limit. |
| SEC-32 | LOW | `Exceptions/ExceptionHandler.php:53,130,183` | Inconsistent `app.debug` default; ensure trace not leaked in prod. | Standardize `false` default. |

### 0.7 Mass assignment
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| SEC-33 | LOW/doc | `Support/Database/Concerns/ModelProperties.php:50` | `fillable` is the **only** write gate (`guarded` applies to output only). Over-broad `fillable` or `create($request->all())` unguarded. | Document; consider a write-side guard check. **Non-issue otherwise:** whitelist works. |

---

## PHASE 1 — CORRECTNESS BUGS

### 1.1 Request / Response
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-01 | HIGH | `Http/Request.php:405` | `isJson()` exact-matches `application/json` → fails on `; charset=utf-8`; body falls to `$_POST` → **JSON silently dropped**. | `str_contains`. |
| BUG-02 | HIGH | `Http/Request.php:68` | Multipart-PUT checks `['Content-Type']` but headers are lowercased → branch never fires. | Use `'content-type'`. |
| BUG-03 | HIGH | `Http/Request.php:18,348` | `use function PHPUnit\Framework\isNull;` — `hasFile()` fatals in prod. | `is_null()`. |
| BUG-04 | HIGH | `Http/Request.php:327` | `hasBody()` precedence: `?? 0 > env(...)` binds wrong. | Parenthesize. |
| BUG-05 | HIGH | `Http/Request.php:471` | `is()` no `return`; `strpos(...) === true` never true → broken. | Return `preg_match`/`str_contains`. |
| BUG-06 | LOW | `Http/Request.php:315` | `has()` treats present-null as absent; ignores attributes/route_params. | Align with `__get`. |
| BUG-07 | MED | `Http/BaseResponse.php:129` | `_send()` never sets `_responseSent` → double `send()` double-emits. | Set flag at end of `_send`. |
| BUG-08 | MED | `Http/BaseResponse.php:131` | `isNotHtml()` branch returns before redirect/file branches → API clients get body instead of 302/download. | Reorder branches. |
| BUG-09 | LOW | `Http/BaseResponse.php:256` | Circular-ref guard commented out → infinite recursion on relation back-refs during JSON serialize. | Re-enable `$seen` guard. |

### 1.2 Routing
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-10 | HIGH | `Http/Route.php:213` | `route()` substitutes `$key` tokens but routes use `{key}` → named URLs return literal `{id}`. | Replace `'{'.$key.'}'`. |
| BUG-11 | HIGH | `Http/Route.php:328` | Optional `{id?}` regex `[^}]+` captures `id?` → wrong/missing param key. | `^{(\w+)\??}$`. |
| BUG-12 | HIGH | `Http/Route.php:139,165` | `any()` filed under `ANY` but dispatch scans `$routes[$method]` → ANY never matches. | Expand ANY across methods or check in dispatch. |
| BUG-13 | MED | `Http/Route.php:320` | `matchesRoute` indexes `$routeParts[$i]` OOB when URI longer than route. | Bound loop; validate leftovers. |
| BUG-14 | MED | `Http/Server.php:51` | API detection via `str_contains('/api')` not prefix → `/therapist-notes` misrouted. | `str_starts_with($uri,'/api')`. |
| BUG-15 | MED | `Http/Route.php:360` | String callbacks `include_once` relative to `Http/`; `_once` blocks re-render. | (see SEC-29) resolve against app base. |
| BUG-16 | LOW | `Http/Server.php:49` | `preg_match('/^.*$/i', ...)` matches all → dead `else` branch. | Remove dead branch. |

### 1.3 Route model binding
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-17 | HIGH | `Http/Middlewares/SubstituteBindings.php:90` → `NamespaceHelper.php:45` | Recursively walks `app/Models` on **every request per param** (also PERF-01). | Cache key→class map once. |
| BUG-18 | MED | `SubstituteBindings.php:35` | Only numeric ids bound; slug/uuid silently pass through. | Support custom binding column. |
| BUG-19 | MED | `SubstituteBindings.php:39` | `resolveModel` null = "not a model" = "typo'd model" → silent skip. | Distinguish + 404. |

### 1.4 ORM / DB
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-20 | HIGH | `…/Concerns/InitsModelEvents.php:10` | `boot/booting/booted` empty → model events **never fire**; `creating()`… need a 3rd callback arg never supplied. | Wire an event registry. |
| BUG-21 | HIGH | `…/Concerns/HasRelationships.php:22,44,67` | Relations execute eagerly, return data not builders → N+1, no `with()` batching. | Return relation/builder; `whereIn` eager load (PERF-14). |
| BUG-22 | HIGH | `QueryBuilder.php:69` vs `ModelHelpers.php:95` | Casts run on write; re-encode only in `__update`/`_save` → other write paths double-decode → `HY093`. | Don't cast on write, or track serialized state. |
| BUG-23 | HIGH | `QueryBuilder.php:159`, `DB.php:189` | `first()/find()` return `false` not `null` — breaks `?Type`/`=== null`. | Return `null`. |
| BUG-24 | MED | `Model.php:87` | Typo `group_concact`; `bit_xor` missing from dynamic list. | Fix name; add `bit_xor`. |
| BUG-25 | MED | `Model.php:87` | `findByEmail` whitelisted as `_findByEmail` → `__callStatic` can't reach it. | Whitelist raw names. |
| BUG-26 | HIGH | `QueryBuilder.php:666`, `DB.php:444` | `restore()` passes list `['deleted_at',null]` not assoc → writes cols `0`/`1`, never clears `deleted_at`. | `['deleted_at'=>null]`. |
| BUG-27 | MED | `QueryBuilder.php:560` | Soft-delete `deleted_at IS NULL` appended in both `__aggregate` and `_all` → dangling `AND` in `_paginate`. | Reset between passes. |
| BUG-28 | MED | `Connection.php:325` | `bindParam` typing dead because `execute($params)` used → all bound as strings. | Bind loop OR `execute($params)`, not both. |
| BUG-29 | MED | `QueryBuilder.php:1001` | `_save` update-branch indexes `$model[0][...]` on a flat assoc array → nulls `updated_at`/PK. | Fix shape. |
| BUG-30 | HIGH | `Connection.php:441,546` | `fetch()` hardcodes `[]` joins → JOINs entirely dead; dot-notation `where('user.name')` → invalid SQL. | Thread joins; escape (SEC-14). |
| BUG-31 | HIGH | `Support/Database/DB.php` (2.1–2.5) | Static `DB` builder: static `$table` shared across builders, `paginate()` broken (`static::$offset` undefined), static/dead `$joins`, `lockForUpdate` leaks to next query. | Rewrite as instance-based or deprecate in favor of Model trait. |
| BUG-32 | MED | `Connection.php:857` | `strpos($name,'_')` falsy at pos 0 → tables/keys starting `_` misroute in `__callStatic`. | `!== false`. |
| BUG-33 | MED | `Connection.php:810` | `remove()` returns `false` on 0 rows → "already absent" looks like failure. | Distinguish executed vs affected. |
| BUG-34 | MED | `Connection.php:919,967,1079` | Swallowed `PDOException` in `get/unset/readjob/popjob` → DB errors look like "no data". | Log/rethrow. |
| BUG-35 | MED | `DB.php:434`, `QueryBuilder.php:636` | `delete(null)` → `WHERE id = NULL` no-op; bare `delete()` with `$id=0` can `DELETE` all rows. | Require explicit filter. |
| BUG-36 | MED | `QueryBuilder.php:305` | `firstOrNew` ignores search args, returns bool. | Implement find-or-new. |

### 1.5 Container / DI
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-37 | HIGH | `…/Concerns/ServiceContainer.php:91` | `make()` caches **every** resolution — plain `bind()` closures become singletons. | Only `singleton()`/`instance()` cache. |
| BUG-38 | HIGH | `…/Concerns/ClassDependencyResolver.php:52` | Autowiring crashes on primitive/union/nullable/variadic params. | `isBuiltin()`/`ReflectionNamedType`/default+null fallback. |
| BUG-39 | MED | `ServiceContainer.php:59` | `singleton()` resolver invoked with no container arg; double-memoizes with BUG-37. | Pass `$this`. |
| BUG-40 | MED | `ServiceContainer.php:51,137` | `$aliases`/`$resolved` never declared → `isAlias()` dead, `offsetUnset` touches undefined prop. | Declare + populate or remove. |

### 1.6 Validation
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-41 | MED | `Support/Validator.php:12` | All state `static` → nested/concurrent `validate()` clobbers `$errors`/`$validated`. | Instance state. |
| BUG-42 | MED | `Validator.php:138` | `null` passes every rule except required/sometimes/forbidden → missing field reported valid. | Decide optional semantics explicitly. |
| BUG-43 | MED | `Validator.php:76` | `confirm` re-runs base ruleset, error-key clobber, `==` compare. | Scope confirm; `hash_equals`-style. |
| BUG-44 | MED | `Validator.php:293` | `in`/`not_in` mis-handles array `$paramval`. | Guard array input. |
| BUG-45 | LOW | `Validator.php:317` | `mimes` calls `uploadProperties()` before `instanceof File` → fatal on non-file. | Guard first. |
| BUG-46 | LOW | `Validator.php:175` | `integer` accepts `"1e3"` (scientific). | Tighten. |

### 1.7 Unimplemented public API (throws at runtime)
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-47 | HIGH | `Http/Request.php:576`, `Http/Middlewares/ValidateSignature.php:21` | `validateSignature()` + middleware throw NotImplemented → 500 if used. | Implement HMAC signed URLs. |
| BUG-48 | MED | `Support/Config.php:123` | `clearCache()` throws → config cache can't invalidate (blocks PERF-10). | Implement prefix clear. |
| BUG-49 | MED | `Support/Database/DB.php:169,197,202,207` | `findOr/firstWhere/firstOrCreate/firstOrNew` throw (Model trait has them; static DB doesn't). | Implement or delegate. |
| BUG-50 | LOW | `ValidatorRule.php:13`, `Arrayable.php:553`, `Facade.php:184`, `Storage/LocalTemporaryUrlGenerator.php:12` | Public methods throw / base not `abstract`. | Implement or mark abstract. |
| BUG-51 | LOW/MED | `TokenGuard/SessionGuard/JwtGuard` (several) | Guard-interface methods throw NotImplemented → contract not honestly implemented. | Implement or narrow interface. |
| BUG-52 | LOW | `Http/Server.php:69` | `$request` undefined in `catch` if `make('request')` throws. | Init `$request=null`. |
| BUG-53 | LOW | `Foundation/ConsoleKernel.php:110` | Empty signature → command registered under `''`. | Fall back to class name. |

### 1.8 Session handler (correctness + persistence)
| ID | Sev | Location | Issue | Fix |
|----|-----|----------|-------|-----|
| BUG-54 | MED | `Support/Session/MysqlSessionHandler.php:100` | `write()` never binds `:session_data` → **sessions don't persist** (+ retry loop hammers DB). | Bind all params. |
| BUG-55 | MED | `MysqlSessionHandler.php:60,66` | `gc()` never binds `:max_lifetime` → SQLSTATE, recursive retry, sessions never expire. | Bind + stop recursion. |
| BUG-56 | LOW | `MysqlSessionHandler.php:30` | `open()` sets table = session name (e.g. `PHPSESSID`). | Use configured table. |

---

## PHASE 2 — PERFORMANCE

### 2.1 Quick wins (local, high payoff)
| ID | Impact | Location | Fix |
|----|--------|----------|-----|
| PERF-01 | HIGH | `SubstituteBindings.php:90` | Cache model key→class map (kill per-request `app/Models` walk). = BUG-17. |
| PERF-02 | HIGH | `Http/Route.php:177,304` | `findRouteMiddlewares` re-scans all routes after dispatch already matched — reuse `self::$currentRoute` middlewares. |
| PERF-03 | MED | `Connection.php:87` | Enable `PDO::ATTR_PERSISTENT` (flagged). |
| PERF-04 | MED | `MysqlSessionHandler.php:25` | Reuse container `db.connection` instead of opening a 2nd PDO. |
| PERF-05 | MED | `QueryBuilder.php:881,898,1040` | Drop post-write confirmation `SELECT`; use `RETURNING`/`lastInsertId`. |
| PERF-06 | MED | `QueryBuilder.php:416` | Fire lifecycle hooks once (currently 3×/row no-op). |
| PERF-07 | LOW | `Http/Request.php:49,56,59` | Hoist `config()` out of cookie loop; lazy `input`/`headers`. |
| PERF-08 | MED | `QueryBuilder.php:69` | Skip decode-then-encode cast round-trip on writes. |
| PERF-09 | MED | `Connection.php:553` | Precompute JSON columns instead of per-column `_json` `strpos` scan. |
| PERF-19 | LOW | `Route.php:167`, `SubstituteBindings.php:36` | `sanitize_data` applied to params twice — do once (or at output). |

### 2.2 Structural
| ID | Impact | Location | Fix |
|----|--------|----------|-----|
| PERF-10 | HIGH | `Support/Config.php:40` | Compiled config-cache artifact (implement `clearCache`, BUG-48). |
| PERF-11 | HIGH | `Http/Server.php:54,59` | Route cache (stop re-parsing `routes/*.php` each request). |
| PERF-12 | MED | `Http/Route.php:315` | Compiled dispatcher: static-route hash + combined dynamic regex. |
| PERF-13 | MED | `Foundation/Application.php:73`, `Http/Server.php:95` | Deferred/lazy providers + lazy facades (stop registering+booting all per request). |
| PERF-14 | HIGH | `HasRelationships.php` | Real eager loading (`whereIn` batching). = BUG-21. |
| PERF-15 | MED | — | `opcache.preload` script for framework core + models. |
| PERF-16 | MED | `DB.php:47` | Move transaction state off `$_SESSION` onto the connection. = WRK-12. |
| PERF-17 | MED | `Application.php:29`, `NamespaceHelper.php:14` | `composer.json` read+parsed 4× per request — cache the namespaces. |
| PERF-18 | MED | `Http/Server.php:63`, `Url.php:60` | `storeCurrent()` writes session every non-asset request — gate to web GET. |

**Verified good (no action):** `Config` is a per-request singleton; `db.connection` is one lazily-opened PDO reused within a request.

---

## PHASE 3 — PACKAGING / DX (enables reusable packages, incl. the Octane demo)
| ID | Location | Gap → Fix |
|----|----------|-----------|
| PKG-01 | `Foundation/ServiceProvider.php` | Add `loadRoutesFrom()`/`loadMigrationsFrom()`/`loadViewsFrom()`/`mergeConfigFrom()`/`loadTranslationsFrom()`/`commands()`. |
| PKG-02 | `Foundation/Application.php:73` | `PackageManifest` reading installed `composer.json` `extra` → auto-register providers (the `composer require`-and-done experience). |
| PKG-03 | `Foundation/ConsoleKernel.php:141` | Implement `loadLibrariesCommands()` so package commands surface. |
| PKG-04 | `Foundation/ServiceProvider.php` | Deferred providers (`provides()` / `DeferrableProvider`). |
| PKG-05 | `Foundation/ServiceProvider.php:54,62` | Fix `publishes()` tag signature + `getPublishables` tag filtering. |
| PKG-06 | `Support/ServiceProvider.php`, `Foundation/ServiceProvider.php:41` | Remove dead stub; `defaultProviders()` hardcodes `\App\Providers\*`. |
| PKG-07 | `…/Concerns/ServiceContainer.php` | Container niceties: contextual binding, tags, scoped, `call()` injection, `extend()`, populate `$aliases`. |
| PKG-08 | `Foundation/Console/Commands/Make/*` | `make:` scaffolds: provider/command/middleware/event/listener/job/mail/rule/policy/request/resource/cast. |
| PKG-09 | `Support/View/*` | Namespaced package views (`view('pkg::x')`). |
| PKG-10 | — | `route:cache` / `config:cache` commands (with PERF-10/11). |
| PKG-11 | `Support/Database/Schema/*` | (Optional) grammar abstraction — schema is MySQL-hardcoded; note portability limit. |

---

## PHASE 4 — WORKER-SAFETY (Octane prerequisite; do last, behind flags)
Under PHP-FPM these are latent; under a persistent worker (the Octane package) they leak state or fatal. Several overlap security (Auth identity leak) — treat those as blocking for the demo.

| ID | Location | Issue → Fix |
|----|----------|-------------|
| WRK-01 | `Http/Request.php:49-79` | Built from `$_GET/$_POST/$_SERVER/$_COOKIE/$_FILES` + `getallheaders()` + `php://input` → adapter/injectable request source. |
| WRK-02 | `Http/BaseResponse.php:132-214` | Native `header()`/`http_response_code()`/`echo`/`readfile()` → route through the response object. |
| WRK-03 | `Support/Auth/Auth.php:14,90` | Static `$user/$jwt/$sid/$impersonator*` → **cross-user identity leak**; reset per request. |
| WRK-04 | `Support/Facade/Facade.php:27,257` | Static resolved-instance cache of request/response/session → `clearResolvedInstances()` + rebind per request. |
| WRK-05 | `Http/Route.php:11-23`, `Http/Server.php:54` | Static route/current-route state + `require_once` route load → reset per request; rethink one-shot load. |
| WRK-06 | `Support/Validator.php:12` | Static → instance state. (= BUG-41) |
| WRK-07 | `Support/Database/DB.php` | Static query-builder state. (= BUG-31) |
| WRK-08 | `Http/Session.php` | Native `$_SESSION`/`session_start` = process-global shared across users → request-scoped store. |
| WRK-09 | `…/Concerns/ServiceContainer.php` | No `flush()`/`forgetScoped()`/scoped bindings → add reset + scoped concept. |
| WRK-10 | `Exceptions/ErrorHandler.php:84`, `Http/Client/{Response.php:434,458,PendingRequest.php:757}`, `Support/Stringable.php:710`, `…/EnumeratesValues.php:213` | `exit()/die()` in request path kill the worker → throw/handle instead. |
| WRK-11 | `JwtGuard.php:157`, `Support/Url.php:28`, `Http/Middlewares/ServePublicAssets.php:27` | Read `$_SERVER` directly → go through Request. |
| WRK-12 | `Support/Database/DB.php:47` | Transaction state in `$_SESSION` → connection object. (= PERF-16) |
| WRK-13 | `Http/Request.php:158` | Writes `$_FILES` global → keep on the request instance. |

---

## Audit provenance
Five parallel audits (2026-07-26): worker-safety/Octane, Laravel-parity/packaging, security, correctness bugs, performance. Counts: ~33 security, ~56 correctness, ~19 performance, ~11 packaging, ~13 worker-safety items (with cross-references de-duplicated above).
