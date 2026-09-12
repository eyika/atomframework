<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Middlewares\ServePublicAssets;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Config;

/**
 * Reported by a downstream consumer: assets served by this middleware carried no
 * `X-Content-Type-Options: nosniff`.
 *
 * It matters because a multi-tenant app serves merchant-uploaded images from each shop's OWN
 * origin, so a file a browser decides to treat as HTML is same-origin stored XSS against a live
 * shop. Refusing to store such files is not a workable defence — `GIF89a<script>…` is a genuinely
 * valid GIF header, and rejecting every upload whose bytes contain "<script>" would reject real
 * photographs carrying that string in their EXIF. Declaring the type authoritatively is.
 *
 * Reviewing it turned up something they had not reported and which is worse: the middleware
 * concatenated the raw REQUEST_URI onto public_path() with no normalisation, so `..` escaped the
 * webroot entirely. The extension allowlist does not prevent that — the traversal target only has
 * to END in an allowed extension, and `.json`/`.pdf`/`.md` outside the webroot is exactly where
 * service-account keys and uploaded documents live.
 */
class ServePublicAssetsTest extends IntegrationTestCase
{
    private string $publicDir;
    private string $outsideFile;
    private array $configSnapshot;
    private string $linkTarget;
    private string $publicLink;
    private bool $linked = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSnapshot = Config::snapshot();
        Config::set('app.serve_public_assets', true);

        $this->publicDir = public_path();
        if (!is_dir($this->publicDir)) {
            @mkdir($this->publicDir, 0777, true);
        }

        file_put_contents($this->publicDir . '/probe.json', '{"ok":true}');
        file_put_contents($this->publicDir . '/probe.gif', "GIF89a<script>alert(1)</script>");

        // A file the request must NOT be able to reach: a sibling of public/, with an extension
        // the allowlist accepts.
        $this->outsideFile = dirname($this->publicDir) . '/service-account.json';
        file_put_contents($this->outsideFile, '{"private_key":"leaked"}');

        // The shape `storage:link` creates: a link INSIDE the public root pointing at storage.
        // The file behind it is legitimately servable, even though it resolves outside public/.
        $this->linkTarget = dirname($this->publicDir) . '/storage/app/public';
        @mkdir($this->linkTarget . '/products', 0777, true);
        file_put_contents($this->linkTarget . '/products/shot.jpg', 'IMAGEBYTES');

        $this->publicLink = $this->publicDir . '/storage';
        $this->linked = $this->makeLink($this->linkTarget, $this->publicLink);
    }

    /**
     * Create a directory link, the way `storage:link` does.
     *
     * Falls back to a Windows junction: `symlink()` needs elevation there, while a junction does
     * not, and `realpath()` follows both identically — which is the behaviour under test. Without
     * the fallback this test would silently skip on Windows, hiding the very regression it exists
     * to catch.
     */
    private function makeLink(string $target, string $link): bool
    {
        if (@symlink($target, $link)) {
            return true;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }

        $command = sprintf(
            'cmd /c mklink /J %s %s 2>&1',
            escapeshellarg(str_replace('/', '\\', $link)),
            escapeshellarg(str_replace('/', '\\', $target))
        );
        @exec($command, $output, $status);

        return $status === 0;
    }

    protected function tearDown(): void
    {
        @unlink($this->publicDir . '/probe.json');
        @unlink($this->publicDir . '/probe.gif');
        @unlink($this->outsideFile);

        if ($this->linked) {
            // A junction is removed as a directory, a symlink as a file — try both.
            @rmdir($this->publicLink);
            @unlink($this->publicLink);
        }
        @unlink($this->linkTarget . '/products/shot.jpg');
        @rmdir($this->linkTarget . '/products');

        Config::restore($this->configSnapshot);
        parent::tearDown();
    }

    private function serve(string $uri, array $headers = []): BaseResponse
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'localhost';
        unset($_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE']);
        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = new Request();

        return (new ServePublicAssets())->handle(
            $request,
            fn ($req) => (new \Eyika\Atom\Framework\Http\Response())->body('passed through')
        );
    }

    /** @return array<string, string> header name (lower) => value */
    private function headersOf(BaseResponse $response): array
    {
        $reflection = new \ReflectionClass(BaseResponse::class);
        $prop = $reflection->getProperty('headers');
        $prop->setAccessible(true);

        $flat = [];
        foreach ($prop->getValue($response) as $header) {
            foreach ($header as $name => $value) {
                $flat[strtolower($name)] = is_array($value) ? (string) $value[0] : (string) $value;
            }
        }

        return $flat;
    }

    private function bodyOf(BaseResponse $response): string
    {
        $prop = (new \ReflectionClass(BaseResponse::class))->getProperty('body');
        $prop->setAccessible(true);

        return (string) $prop->getValue($response);
    }

    // ---------------------------------------------------------------- the reported issue

    public function test_a_served_asset_carries_nosniff(): void
    {
        $headers = $this->headersOf($this->serve('/probe.json'));

        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
    }

    public function test_a_served_asset_still_carries_its_content_type(): void
    {
        $headers = $this->headersOf($this->serve('/probe.json'));

        $this->assertArrayHasKey('content-type', $headers);
        $this->assertNotSame('', $headers['content-type']);
    }

    /** The polyglot case the header exists for: valid GIF bytes that also parse as HTML. */
    public function test_a_polyglot_image_is_served_with_nosniff(): void
    {
        $response = $this->serve('/probe.gif');
        $headers = $this->headersOf($response);

        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
        $this->assertStringContainsString('<script>', $this->bodyOf($response), 'the fixture really is a polyglot');
    }

    public function test_a_query_string_does_not_defeat_the_header(): void
    {
        $headers = $this->headersOf($this->serve('/probe.json?v=abc123'));

        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
    }

    // ---------------------------------------------------------------- path traversal

    public function test_a_traversal_cannot_read_a_file_outside_the_public_directory(): void
    {
        $response = $this->serve('/../service-account.json');

        $this->assertStringNotContainsString(
            'leaked',
            $this->bodyOf($response),
            'a ../ traversal escaped the public directory'
        );
    }

    public function test_a_nested_traversal_is_also_refused(): void
    {
        $response = $this->serve('/assets/../../service-account.json');

        $this->assertStringNotContainsString('leaked', $this->bodyOf($response));
    }

    public function test_a_refused_traversal_answers_404(): void
    {
        $response = $this->serve('/../service-account.json');

        $this->assertSame(BaseResponse::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    // ---------------------------------------------------------------- symlinked directories

    /**
     * The reported regression. `public/storage` is a link this framework's own `storage:link`
     * creates, so a file behind it resolves OUTSIDE public/ — and a `realpath()`-based guard
     * refused it, 404ing every upload under `artisan serve` while Apache and LiteSpeed served
     * them fine.
     */
    public function test_a_file_behind_a_link_inside_the_public_root_is_served(): void
    {
        $this->assertTrue($this->linked, 'could not create a link; the regression cannot be exercised');

        $response = $this->serve('/storage/products/shot.jpg');

        $this->assertSame(BaseResponse::STATUS_OK, $response->getStatusCode());
        $this->assertSame('IMAGEBYTES', $this->bodyOf($response));
    }

    /** A linked asset is an asset: it gets the same headers as any other. */
    public function test_a_file_behind_a_link_still_carries_the_asset_headers(): void
    {
        $headers = $this->headersOf($this->serve('/storage/products/shot.jpg'));

        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
        $this->assertArrayHasKey('etag', $headers);
    }

    /**
     * Following a link must NOT reopen traversal. Climbing out through the link is still refused,
     * because containment is decided on the URI before any link is resolved.
     */
    public function test_a_traversal_through_the_link_is_still_refused(): void
    {
        $response = $this->serve('/storage/../../service-account.json');

        $this->assertStringNotContainsString('leaked', $this->bodyOf($response));
        $this->assertSame(BaseResponse::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    // ---------------------------------------------------------------- cache revalidation

    public function test_a_served_asset_carries_cache_validators(): void
    {
        $headers = $this->headersOf($this->serve('/probe.json'));

        $this->assertArrayHasKey('etag', $headers);
        $this->assertArrayHasKey('last-modified', $headers);
        $this->assertSame('public, max-age=0, must-revalidate', $headers['cache-control'] ?? null);
    }

    /** The point of the validators: an unchanged asset costs a 304, not a re-download. */
    public function test_a_matching_etag_gets_a_304_with_no_body(): void
    {
        $etag = $this->headersOf($this->serve('/probe.json'))['etag'];

        $response = $this->serve('/probe.json', ['If-None-Match' => $etag]);

        $this->assertSame(BaseResponse::STATUS_NOT_MODIFIED, $response->getStatusCode());
        $this->assertSame('', $this->bodyOf($response));
    }

    public function test_a_stale_etag_gets_the_asset(): void
    {
        $response = $this->serve('/probe.json', ['If-None-Match' => '"0-0"']);

        $this->assertSame(BaseResponse::STATUS_OK, $response->getStatusCode());
        $this->assertSame('{"ok":true}', $this->bodyOf($response));
    }

    /** A `W/` prefix must still match — GET uses the weak comparison function. */
    public function test_a_weak_etag_still_matches(): void
    {
        $etag = $this->headersOf($this->serve('/probe.json'))['etag'];

        $response = $this->serve('/probe.json', ['If-None-Match' => 'W/' . $etag]);

        $this->assertSame(BaseResponse::STATUS_NOT_MODIFIED, $response->getStatusCode());
    }

    public function test_an_etag_list_matches_on_any_member(): void
    {
        $etag = $this->headersOf($this->serve('/probe.json'))['etag'];

        $response = $this->serve('/probe.json', ['If-None-Match' => '"nope", ' . $etag . ', "also-nope"']);

        $this->assertSame(BaseResponse::STATUS_NOT_MODIFIED, $response->getStatusCode());
    }

    public function test_if_modified_since_is_honoured_when_no_etag_is_sent(): void
    {
        $response = $this->serve('/probe.json', [
            'If-Modified-Since' => gmdate('D, d M Y H:i:s', time() + 60) . ' GMT',
        ]);

        $this->assertSame(BaseResponse::STATUS_NOT_MODIFIED, $response->getStatusCode());
    }

    public function test_an_older_if_modified_since_gets_the_asset(): void
    {
        $response = $this->serve('/probe.json', [
            'If-Modified-Since' => gmdate('D, d M Y H:i:s', time() - 86400) . ' GMT',
        ]);

        $this->assertSame(BaseResponse::STATUS_OK, $response->getStatusCode());
    }

    /**
     * An entity tag is exact; `If-Modified-Since` has one-second resolution and cannot tell two
     * edits within the same second apart. So a non-matching ETag must win over a date that would
     * otherwise say "unchanged" — otherwise the coarser test overrides the precise one.
     */
    public function test_a_non_matching_etag_wins_over_a_satisfied_if_modified_since(): void
    {
        $response = $this->serve('/probe.json', [
            'If-None-Match'     => '"stale"',
            'If-Modified-Since' => gmdate('D, d M Y H:i:s', time() + 60) . ' GMT',
        ]);

        $this->assertSame(BaseResponse::STATUS_OK, $response->getStatusCode());
    }

    /** An operator who knows their assets are content-hashed can opt into real caching. */
    public function test_max_age_is_configurable_for_hashed_assets(): void
    {
        Config::set('app.asset_cache_max_age', 31536000);

        $headers = $this->headersOf($this->serve('/probe.json'));

        $this->assertSame('public, max-age=31536000', $headers['cache-control'] ?? null);
    }

    // ---------------------------------------------------------------- no collateral damage

    public function test_a_legitimate_asset_is_still_served(): void
    {
        $response = $this->serve('/probe.json');

        $this->assertSame('{"ok":true}', $this->bodyOf($response));
        $this->assertSame(BaseResponse::STATUS_OK, $response->getStatusCode());
    }

    public function test_a_missing_asset_answers_404(): void
    {
        $this->assertSame(
            BaseResponse::STATUS_NOT_FOUND,
            $this->serve('/definitely-not-here.json')->getStatusCode()
        );
    }

    /** A non-asset URI must fall through to the rest of the pipeline untouched. */
    public function test_a_non_asset_request_passes_through(): void
    {
        $this->assertSame('passed through', $this->bodyOf($this->serve('/dashboard')));
    }
}
