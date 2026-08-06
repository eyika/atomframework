# Changelog

All notable changes to **`eyika/atom-framework`** are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/); the project is **pre-1.0**, distributed as the
moving `dev-main` (and `dev`) branch — no semver tags yet. Entries reference the fixing commit.

## [Unreleased]

### Security

- **BREAKING — trusted-proxy header flags are now real, and nothing upstream is trusted by
  default.** Four compounding defects in the same small API.

  `HEADER_X_FORWARDED_*` held `$_SERVER` key **strings** while carrying the names of Symfony's
  **bit flags**, so the usage those names advertise silently did the wrong thing:

  ```php
  Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST   // "HTTP_X_FORWARDED_^__TO"
  ```

  a byte-wise OR over strings, and a `TypeError` the moment it reached
  `setTrustedProxies(array $proxies, int|null $headers = null)`. That exact expression shipped
  **pre-written in the skeleton's `TrustProxies`**, and stayed invisible only because the
  middleware then passed a hardcoded `1` and never read the property it had just computed.

  The `$headers` parameter was in fact dead end to end — stored and never read — so header
  selection did not exist: once a peer was trusted, **every** forwarded header was believed.

  Worse, the scaffold defaulted to `['127.0.0.1', 'localhost']`, and `Kernel` registers
  `TrustProxies::class` with no constructor arguments, so that fallback always won. Behind
  LiteSpeed and similar the PHP process commonly sees `REMOTE_ADDR=127.0.0.1` for ordinary
  traffic, which makes **every request** a trusted proxy — and `host()`, `scheme()` and
  `clientIp()` all gate on that. A client could pick the `Host` the app resolves tenants and
  generated URLs from, and `X-Forwarded-Proto: https` made a plaintext request report as secure.

  Finally `isFromTrustedProxy()` was a bare `in_array()`, so `TRUSTED_PROXIES=10.0.0.0/8` matched
  nothing — safe by accident, and impossible to debug.

  The flags are now real single-bit integers (Symfony's values, so knowledge carried from there
  works), each gating its own header, plus `HEADER_X_FORWARDED_ALL`. `isFromTrustedProxy()`
  understands CIDR for IPv4 and IPv6 and an explicit `'*'`. The skeleton defaults to **no trusted
  proxies**, reading `TRUSTED_PROXIES` via the new `app.trusted_proxies` config key.

  ```php
  // Trust a load balancer for the client IP and scheme, but NOT the Host.
  $request->setTrustedProxies(
      ['10.0.0.0/8'],
      Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO
  );
  ```

  **Upgrade:** if you copied the old scaffold it passes the literal `1`, which is no longer a
  recognised flag, so that app now trusts **no** forwarded headers. That is the fail-safe
  direction — `clientIp()` returns the proxy's address rather than a spoofable one — but if you
  are genuinely behind a proxy you must now declare it: set `TRUSTED_PROXIES` and pass the flags
  you actually want. Apps that were relying on the loopback default were trusting their callers.
  (`1db731d`)

- **`guarded` is now enforced on the JSON encode path.** It promises a column never leaves the
  application and `toArray()` honoured it — but nothing on the **encode** path called `toArray()`,
  so a model reaching `json_encode()` was serialized from its declared public properties with the
  guard bypassed. Two independent defects, either alone leaving the hole:

  1. `Model` implemented no serialization contract — not `JsonSerializable`, not
     `Contracts\Arrayable`.
  2. `Collection` dispatched on `Support\Arrayable` / `Support\Jsonable`, which are concrete
     **classes**, not the same-named **interfaces** under `Support\Contracts`. No model could ever
     be an instance of a class, so every model fell through and was encoded raw.

  `Model` now implements `JsonSerializable` and `Contracts\Arrayable`, serializing through the
  guarded `toArray()`. `JsonSerializable` is what makes this hold for **every** shape — a bare
  model, a plain array of models, a `Traversable`, a `Collection` — since `json_encode()` honours
  it natively.

  **Scope, for anyone auditing their own exposure:** the direct response path was already safe —
  `response()->json(['w' => $model])` goes through `convertObjectsToArray()`, which calls
  `toArray()`. The gap was every other route to `json_encode()`, above all a **Collection**, whose
  own `toArray()` returned its models untouched. And a column assigned through `__set` lives in
  `dynamicProperties`, which is not public, so it could not leak this way; what escaped from a
  plain model was internal plumbing (`table`, `primaryKey`, `softdeletes`). **A data column could
  only leak if your model declares it as a public property** — check that first when assessing
  whether anything actually left your application.

  `toArray(false)` still returns guarded columns for server-side callers that need them.
  (`d226e04`)

- **BREAKING — `Request::cookie()` returns the cookie's VALUE, not the `Cookie` object.** It used
  to hand back the wrapper while `query()` and `input()` return values, so the obvious line

  ```php
  $token = $request->cookie('cart_token') ?? $request->query('cart_token');
  ```

  was string-or-object depending on which branch hit, with nothing signalling it. Anything
  defensive then silently discarded the cookie path — a real app guarded this with
  `is_string($token)`, which is always false for an object, so the cookie never worked and only
  the query string did. Nothing threw and nothing logged.

  The framework was working around its own defect: `SessionGuard::recall()` carried an
  `is_object(…) ? ->getValue() : …` unwrap, now removed.

  Reading and writing are different jobs. A `Cookie` describes a `Set-Cookie` header — path,
  domain, `SameSite`, expiry — and none of those exist on an inbound cookie, where the browser
  sends only `name=value`. This now matches Laravel, where reading yields a string and the cookie
  object is write-side only.

  - `cookie('x')` → the value
  - `cookie()` → `name => value` (previously an array of `Cookie` objects, which made `$default`'s
    type incoherent)
  - `cookieObject('x')` → the wrapper, or `null`
  - `cookies()` → unchanged; it is the object collection and is honestly named

  A `__toString()` on `Cookie` was considered and rejected: it fixes interpolation while leaving
  `is_string()` false, `===` failing and `json_encode()` emitting `{}`. The type was the bug, not
  the formatting.

  **Upgrade:** `->cookie('x')->getValue()` now raises *call to a member function on string* — drop
  the `->getValue()`, or switch to `cookieObject('x')` if you genuinely want the wrapper. That
  failure is loud, unlike the one it replaces. (`81257b2`)

- **BREAKING — request attributes now outrank client-supplied input.** `$request->foo = $obj`
  writes to the attribute bag, but `__get` resolved that bag **last** — after input, route params
  and query — so anything trusted server-side code bound could be shadowed by a request parameter
  of the same name.

  The visible symptom was a route-param collision: a `{business}` route param shadowed a
  middleware-bound `$request->business`, so a handler received the raw URL segment where an object
  was expected. **The security consequence is that client input could shadow bound context too** —
  on an unauthenticated route, `$request->current_customer` returned whatever the caller posted
  under that key. Server-established context must not be overridable by the caller.

  Resolution order is now **attributes → route params → input → query**. Note that reorders a
  second pair as well: input used to beat route params, so a body field could shadow the matched
  path segment (`/users/{id}` with `id` in the body). Declared properties (`auth_user` etc.) never
  reached `__get` and are unaffected.

  **If any code depended on input overriding a bound attribute or a route param, it changes
  behaviour.** That dependency was the bug. Middleware that binds context should move to the
  explicit `setAttribute()`/`attribute()` API (see Added), which no parameter can shadow.
  (`2bafa5c`)

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
- **HTTP — `BaseResponse::getStatusCode()`.** `status()` is a setter and `$statusCode` is
  protected, so middleware wrapping a handler had no way to ask whether that handler succeeded —
  the status was only observable after `send()`, via `sentStatus()`. (`969d4cc`)

- **HTTP — `Request::port()`**, resolving a trusted `X-Forwarded-Port` first (behind a
  terminating proxy `SERVER_PORT` is the internal one), then the port in `Host`, then
  `SERVER_PORT`, then the scheme default. There was previously no `port()` at all, which is why
  `HEADER_X_FORWARDED_PORT` had nothing to govern. Also `Request::trustedHeaderSet()`, returning
  the forwarded-header bitmask in effect. (`1db731d`)

- **HTTP — `429 Too Many Requests` and `503 Service Unavailable` responses.** There was no way to
  return a 429 at all: no status constant, no helper. Both now exist, and both take an optional
  `Retry-After` in seconds — a rate-limit response without it gives a client nothing to back off
  against.

  ```php
  return json_response()->tooManyRequests('Rate limit exceeded', retryAfter: 60);
  return json_response()->serviceUnavailable('Under maintenance', retryAfter: 300);
  ```

  The 503 constant had existed all along with no helper behind it. (`cf30cfa`)

- **Mail — custom message headers**, via `header(string $name, string $value)` and
  `headers(array)` on the `Mailer` and every driver. This is a deliverability requirement rather
  than a convenience: Gmail and Yahoo have required `List-Unsubscribe` plus
  `List-Unsubscribe-Post: List-Unsubscribe=One-Click` on bulk mail since **February 2024**, and an
  in-body unsubscribe link does not satisfy the automated check. The throttling that follows
  applies to the sending **domain**, so a non-compliant campaign degrades transactional mail too.

  ```php
  Mailer::to($subscriber)
      ->header('List-Unsubscribe', "<{$oneClickUrl}>, <mailto:unsub@example.com>")
      ->header('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click')
      ->send('Our July newsletter');
  ```

  Headers are cleared after each send, so one message's cannot leak onto the next in a
  `queue:work` run. CR/LF is rejected rather than sanitised (header injection), and re-setting a
  name replaces it, since a duplicate `List-Unsubscribe` is itself a compliance failure.

  **The SES driver cannot carry custom headers** — its v1 `SendEmail` API has no field for them —
  so it **fails the send** with an explanatory `MailerResponse` instead of delivering without.
  Use SMTP, Mailgun or Postmark for bulk mail. (`a0b5e69`)
- **Query builder — `groupBy()`, `having()`, and a chainable `select()`/`selectRaw()`.** Per-key
  aggregation previously had to be done in PHP over every matching row, or dropped to raw SQL:

  ```php
  Order::select(['customer_id'])
       ->selectRaw('SUM(total) AS lifetime')
       ->groupBy('customer_id')
       ->having('SUM(total)', '>', 100)
       ->get();
  ```

  `having()` whitelists its left-hand side — a plain column, or a known aggregate over one — and
  **throws** on anything else, because it is the one clause whose left side is naturally an
  expression; the compared value is always bound. `selectRaw()` is separate from `select()` on
  purpose: it is the only place the builder emits caller-supplied SQL verbatim, so **never pass
  user input to it**. The static `DB` builder gains `groupBy()`/`having()` too; it has no
  chainable `select()` because `DB::select()` is already a raw-SELECT executor — projection there
  stays on `get(['a', 'b'])`. (`ccf166d`)
- **HTTP** — an explicit request-attribute API: `setAttribute()`, `attribute()`, `hasAttribute()`
  and `attributes()`. These read and write only the attribute bag, so server-bound context cannot
  be shadowed by a request parameter regardless of how `__get` resolves. Prefer them in middleware
  over `$request->foo = $obj` when the value must be the one you set. (`2bafa5c`)
- **Hashing** — a first-party password hasher. The framework verified passwords but shipped no way
  to produce a hash, so each app called `password_hash()` itself and chose its own algorithm and
  cost. `Support\Hashing\Hasher` wraps PHP's password API behind `config('hashing')` — bcrypt
  (default), argon2i, argon2id — with a `Hash` facade and `hasher()` / `bcrypt()` helpers:

  ```php
  $user->password = Hash::make($plain);

  if (Hash::check($plain, $user->password) && Hash::needsRehash($user->password)) {
      $user->update(['password' => Hash::make($plain)]);   // cost raised since signup
  }
  ```

  Options you don't configure are left to PHP's defaults rather than pinned by the framework.
  `make()` fails closed instead of returning `false` (which would be stored as an empty password),
  and `check()` rejects an empty or null hash, so a row with no password can't be satisfied by
  empty input. Hashing is one-way and independent of `APP_KEY` — rotating the key never
  invalidates stored passwords. (`3b5330c`)
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
- **Exceptions — uncaught throwables never reached the application log.** `handleException()`
  recorded them with `error_log($exception)`, which writes to **PHP's** error log — stderr under
  the dev server, wherever `php.ini` points under FPM — so `storage/logs` stayed empty for every
  500. That is a dead end exactly when you can least afford one.

  Uncaught throwables now go to the application logger first, always defensively: `logger()`
  resolves `config()`, and an error raised before config is loadable would otherwise fatal *inside*
  the error handler, so any failure there falls back to PHP's log with full detail. A one-line
  summary still goes to PHP's log so `php -S` and `docker logs` stay useful.

  Traces are capped at 20 frames and carry **no arguments** — arguments are what make a trace
  enormous (one entry of the old shape measured 185 KB) and can leak credentials passed to a
  `connect()` call straight into the log. The wrapped-cause chain is named too, since the
  interesting throwable is often the inner one. (`737d627`)

- **Mail — `FailoverDriver` could never report a successful send.** It read `$response['success']`,
  but `MailerResponse` is a plain object implementing no `ArrayAccess` (its `__toArray()` is an
  ordinary method PHP never calls implicitly), so **every** send — successful ones included —
  raised *Cannot use object of type MailerResponse as array*. Being an `Error`, it escaped the
  driver's `catch (Exception)` and killed the send outright instead of failing over. Fixed, and the
  catch widened to `Throwable` so a driver blowing up with an `Error` — a `TypeError` from bad
  config, a missing SDK class — now fails over as intended, which is the case failover exists for.
  (`737d627`)

- **HTTP — `StartSession` and the `Http\Client` retry callbacks caught `Exception` where an `Error`
  was possible.** Container resolution failures surface as `Error` as often as `Exception`;
  `createSession()` also now rethrows anything that isn't the known uninstantiable-class case
  rather than silently returning null. A retry/throw callback with a bad signature no longer leaves
  retry state inconsistent. (`737d627`)

- **Routing — middleware aliases were never resolved, so every `->middleware('auth')` route was a
  500.** `Pipeline::resolveMiddleware()` returned the pipe's first segment as the class name, so
  the pipeline reached `new 'auth'` and threw `Class "auth" not found` — reported from inside the
  `SubstituteBindings` frame, which points away from the cause. `Route::$middlewareAliases` was
  assigned in two places and read in none.

  This was total rather than an edge case: `auth`, `throttle:…` and every app-defined alias, which
  in a typical app is the entire authenticated API plus every rate-limited route. Parameter
  splitting was never affected — a fully-qualified class name with `:args` resolved and received
  its arguments correctly; only the lookup was missing.

  An unknown alias now also fails loudly with `Unknown middleware alias [auth]` rather than a
  missing-class error, since the latter sends you looking for a file that was never meant to
  exist. (`969d4cc`)

- **HTTP — `clientIp()` could be set by the client.** It took the left-most `X-Forwarded-For`
  entry unconditionally, which is correct only when every proxy **overwrites** the header. Proxies
  that append — the common case — leave the left-most entry as whatever the caller sent, so a
  client could simply state its own address. The chain is now walked from the right, discarding
  hops that are themselves trusted proxies, returning the first that is not.

  **Behaviour change:** with only your edge proxy declared, an undeclared internal hop is now
  returned instead of the address beyond it. That is the conservative answer — nothing to the left
  of an undeclared hop can be vouched for — so declare every hop in `TRUSTED_PROXIES` if you want
  the walk to reach the originating client. (`969d4cc`)

- **Schema — `Schema::hasTable()` reported `true` for every table once anything had been written,
  so guarded migrations silently skipped their own `CREATE`.** It read

  ```php
  return $statement->rowCount() > 0 || $statement->fetch() !== false;
  ```

  `rowCount()` is only defined for `INSERT`/`UPDATE`/`DELETE`. For a `SELECT` it is
  driver-dependent, and `pdo_sqlite` answers it from `sqlite3_changes()` — the affected-row count
  of the last **write** on that connection. So after any `INSERT`, the left operand was already
  true, PHP short-circuited, and the real lookup never ran.

  The damage is quiet by construction: `if (!Schema::hasTable($t)) { … }` was told the table
  already existed, so it skipped the create and **threw nothing**. The failure surfaced much later
  as *"no such table"* on the first request that used it. Any migration written in that shape was
  affected. The compiled SQL was always correct — only the truthiness test around it was wrong,
  and `columnExists()` was never affected. Now decided by whether a row came back.

  **If you ran migrations under an affected build, verify the tables actually exist** rather than
  trusting that the run reported success — a skipped create left no trace in the migration log.
  (`e8d7dab`)

- **HTTP — `response()->json()` rejected valid status codes.** It validated the requested status
  against an internal index of the codes that happen to have a named shorthand, so

  ```php
  response()->json($data, 409);   // threw "Invalid HTTP status code: 409"
  ```

  even though `409` is a framework constant **with a working `conflict()` helper**. The same
  applied to `502` (which has `badGateway()`), `503`, and every redirect code. Since `create()` is
  protected, an app needing any status without a shorthand had no public path to it. The check is
  now a plain `100..599` range test — strictly more permissive, so nothing that previously worked
  changes. The index itself was stale too, mapping `304` to a `notModified()` that exists only on
  the HTTP *client* response; it is now documented as an index rather than a whitelist, and a test
  asserts every entry names a real method. (`cf30cfa`)

- **HTTP — `JsonResponse` facade docblock had drifted** from the class, omitting `conflict()` and
  `badGateway()` (so IDEs flagged both as undefined) and declaring `unprocessableEntity()` as
  accepting `string|array $errors` when the parameter is `string`-only — passing an array raised a
  `TypeError` the signature said was fine. A test now fails if any `JsonResponse` helper is missing
  from the facade. (`cf30cfa`)

- **Query builder — aggregates were broken on `snake_case` columns.** `sum('campaign_id')` failed
  with *"no such column: campaign"*. Aggregates dispatch as `{function}_{column}`, and that name
  was split on **every** underscore with only the first two parts kept — so the column was
  truncated at its first underscore, making `sum`/`avg`/`min`/`max` unusable on nearly every column
  in a typical schema. **A second defect in the same expression** meant multi-word aggregates
  (`group_concat`, `var_pop`, `bit_and`, `bit_or`, `bit_xor`) never dispatched at all, since only
  the first segment was tested against the function list. Both replaced by a parser that matches
  the known functions longest-first. `count()` was unaffected — it takes no column — which is why
  it looked healthy. (`c2989b5`)
- **Mail — the Postmark driver sent its reply-to address as `bcc`.** `sendEmail()` was called
  positionally and the reply-to landed in the **tenth** argument, which is `$bcc`, not `$replyTo`.
  So replies were not routed **and** that address silently received a blind copy of every message
  sent through Postmark. Now passed with named arguments. (`a0b5e69`)
- **Query builder — aggregates ran on a *different connection*.** `Connection::__callStatic()`
  opened a new connection from config on every call, and every aggregate except `count()`
  dispatches through it (`sum_total`, `avg_total`, …). So an aggregate **inside a transaction
  could not see that transaction's own uncommitted writes** and silently returned a stale figure;
  a swapped test connection was ignored, so aggregates in tests hit the real configured database;
  and each call opened another connection. They now use the bound connection. (`6b494a8`)
- **Query builder — trailing clauses were emitted in call order, not SQL order.**
  `limit(2)->orderBy('n')` produced `LIMIT 2 ORDER BY n` — a syntax error — while the reverse call
  order worked. `GROUP BY`, `HAVING`, `ORDER BY`, `LIMIT` and `OFFSET` are now emitted in fixed SQL
  order however they were set. (`ccf166d`)
- **Query builder — a clause following `whereIn()` or `whereLike()` used the wrong operator.**
  The operator index stopped advancing after those two conditions, so the next clause re-read
  their operator.
  - After `whereIn()`/`whereNotIn()` this produced **invalid SQL** —
    `whereIn('sku', […])->where('locale', 'en')` emitted `` `locale` IN :locale `` and failed with
    a syntax error pointing at the placeholder. Reversing the order appeared to work only because
    the misalignment then fell off the end of the operator list.
  - After `whereLike()`/`whereNotLike()` it was **worse, because it failed silently**: a leaked
    `LIKE` is still valid SQL, so the following clause quietly became a substring match instead of
    the equality requested — `where('locale', 'en')` also matched `'en-GB'`. **If you have queries
    that chain a clause after a `whereLike()`, their results were wrong, not merely broken.**

  `whereNull()` is unaffected and deliberately unchanged — it consumes no operator slot.
  (`d66a2cf`)
- **Models — builder methods were not all callable statically.** `Model::orderBy('name')->get()`
  raised a raw PHP *"Non-static method … cannot be called statically"* rather than the framework's
  own message. Two causes: `DYNAMIC_STATIC_METHODS` had drifted from the builder's public API, and
  `orderBy`/`with`/`raw` were declared as plain public methods — PHP resolves those directly and
  refuses the static call *before* `__callStatic` runs, so listing them could not have helped.
  They are now `_orderBy`/`_with`/`_raw` (unchanged for instance callers, which go through
  `__call`), and `ModelInterface` declares them accordingly. Also added the missing `firstOrNew`,
  `whereGreaterThan`, `orWhereIn`, `orWhereNotIn` and `orWhereLessThanOrEqual`, removed two
  duplicated entries, and declared four builder methods the contract had omitted (`_orWhereIn`,
  `_orWhereNotIn`, `_firstOr`, `_lockForUpdate`). Tests now enforce that the whitelist and the
  contract both match the builder, so the lists cannot drift again. (`587709c`)
- **Helpers — `encrypt()`/`decrypt()` fataled when no application was bound.** Their documented
  `new Encrypter()` fallback was unreachable: `app()` is nullable by design, so `app()->make(...)`
  died on null before the fallback could run. Because `ModelHelpers` uses these helpers, a model
  declaring `const encrypted` was unusable without a booted container. They now resolve through the
  `Encrypter` facade — swapped instance, then container binding, then a fresh `Encrypter` — which
  also means `Encrypter::swap()` reaches model-level encryption in a test with no container.
  (`587709c`)
- **Foundation — facades are now bound lazily.** `Server` and `ConsoleKernel` constructed *every*
  registered facade on each boot, so an app paid for facades it never used, and any constructor
  that legitimately refuses to build failed the whole boot. With the `Encrypter` now failing closed
  on an unset `APP_KEY`, a freshly created project printed *"The application key must be 32
  bytes…"* on **every** artisan command — including `key:generate`, the command that fixes it.
  Facades are now constructed on first resolution and still shared per application. (`4e3c95e`)
- **Routing — string route targets never worked.** A route registered with a plain file path
  (rather than a closure or controller) was rendered with `include_once __DIR__ . "/$callback"`,
  where `__DIR__` is the **framework's own** `src/Http` directory — so the file was looked up
  inside `vendor/`, where an application's file can never live. It now resolves against the
  application base. `include_once` also meant that hitting the same target twice in one process
  returned `true` instead of re-rendering: invisible under PHP-FPM, but under a persistent worker
  the page rendered once and then silently stopped. It is now `include`, and the resolved path is
  confined to the application directory with `realpath()`, so `../` cannot escape it. (`185a377`)
- **Routing** — removed an always-true guard in `Server::handle()`
  (`preg_match('/^.*$/i', $request->requestUri())`, which matches every string) and the unreachable
  `else` branch behind it. It implied some requests fell through to PHP's built-in server; none
  ever did. (`185a377`)
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
