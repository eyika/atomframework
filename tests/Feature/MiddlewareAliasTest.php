<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Closure;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Exceptions\BaseException;

/**
 * Reported by Claude C (vendra): every `->middleware('auth')` route was a 500 over real HTTP.
 *
 * `Pipeline::resolveMiddleware()` returned the pipe's first segment as the class name, so
 * `carry()` reached `new 'auth'` and threw `Class "auth" not found`. `Route::$middlewareAliases`
 * was populated by `Server` and by the testing `TestCase` and read by nothing — the same shape as
 * the `proxyheader` defect: a map wired up on both ends with nothing in the middle.
 *
 * It survived because the suite drove controllers and middleware objects directly; the harness
 * even populated the alias map, and nothing consumed it. These tests go through
 * `Route::dispatch()` so the pipeline itself is exercised.
 */
class MiddlewareAliasTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        Route::$middlewareAliases = [];
        parent::tearDown();
    }

    /** The reported failure: an aliased route reaches its handler instead of throwing. */
    public function test_a_route_declaring_an_alias_runs_the_aliased_middleware(): void
    {
        Route::$middlewareAliases = ['stamp' => StampMiddleware::class];

        $this->withRoutes(function () {
            Route::get('/aliased', fn($request) => 'handler:' . $request->attribute('stamp'));
            Route::middleware('stamp');
        });

        $this->get('/aliased')->assertBodyContains('handler:stamped');
    }

    /** Aliases carry `:args` exactly as an FQCN does — the splitting was never the broken part. */
    public function test_an_alias_receives_its_parameters(): void
    {
        Route::$middlewareAliases = ['throttle' => RecordingMiddleware::class];

        $this->withRoutes(function () {
            Route::get('/throttled', fn($request) => 'args:' . $request->attribute('args'));
            Route::middleware('throttle:auth-login,10,300');
        });

        $this->get('/throttled')->assertBodyContains('args:auth-login|10|300');
    }

    /** A fully-qualified class name must keep working — aliasing is a lookup, not a replacement. */
    public function test_a_fully_qualified_class_name_still_resolves(): void
    {
        $this->withRoutes(function () {
            Route::get('/fqcn', fn($request) => 'handler:' . $request->attribute('stamp'));
            Route::middleware(StampMiddleware::class);
        });

        $this->get('/fqcn')->assertBodyContains('handler:stamped');
    }

    public function test_a_fully_qualified_class_name_with_arguments_still_resolves(): void
    {
        $this->withRoutes(function () {
            Route::get('/fqcn-args', fn($request) => 'args:' . $request->attribute('args'));
            Route::middleware(RecordingMiddleware::class . ':a,b');
        });

        $this->get('/fqcn-args')->assertBodyContains('args:a|b');
    }

    /** An alias must win over a same-named class, or the map cannot override anything. */
    public function test_the_alias_map_is_consulted_before_treating_the_string_as_a_class(): void
    {
        Route::$middlewareAliases = [StampMiddleware::class => RecordingMiddleware::class];

        $this->withRoutes(function () {
            Route::get('/override', fn($request) => 'stamp:' . ($request->attribute('stamp') ?? 'unset'));
            Route::middleware(StampMiddleware::class);
        });

        // StampMiddleware would have set 'stamp'; RecordingMiddleware ran in its place.
        $this->get('/override')->assertBodyContains('stamp:unset');
    }

    /**
     * An unknown alias must say so. `Class "auth" not found` sends you looking for a missing
     * file; naming the alias points at the actual mistake — a missing Kernel entry.
     */
    public function test_an_unknown_alias_names_itself_in_the_error(): void
    {
        $this->expectException(BaseException::class);
        $this->expectExceptionMessage('Unknown middleware alias [auth]');

        $this->withRoutes(function () {
            Route::get('/unknown', fn($request) => 'never reached');
            Route::middleware('auth');
        });

        $this->get('/unknown');
    }

    /** A string that looks like a class but isn't must not be reported as an alias problem. */
    public function test_an_unresolvable_class_name_is_reported_as_a_class(): void
    {
        $this->expectException(BaseException::class);
        $this->expectExceptionMessage('App\Http\Middlewares\Nope');

        $this->withRoutes(function () {
            Route::get('/missing-class', fn($request) => 'never reached');
            Route::middleware('App\Http\Middlewares\Nope');
        });

        $this->get('/missing-class');
    }
}

/** Marks the request so the handler can prove the middleware actually ran. */
class StampMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): BaseResponse|string
    {
        $request->setAttribute('stamp', 'stamped');

        return $next($request);
    }
}

/** Records the arguments the pipeline passed through, so parameter handling is observable. */
class RecordingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next, ...$args): BaseResponse|string
    {
        $request->setAttribute('args', implode('|', $args));

        return $next($request);
    }
}
