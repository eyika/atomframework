<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Support\Facade\Response;

class ServePublicAssets implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (config('app.serve_public_assets')) {
            $customMappings = [
                'js' => 'text/javascript', //'application/javascript',
                'css' => 'text/css',
                'woff2' => 'font/woff2',
                'woff' => 'font/woff'
            ];

            $uri = explode('?', $request->server('REQUEST_URI') ?? '')[0];
            if (preg_match('/\.(?:js|css|svg|ico|woff|woff2|ttf|webp|pdf|png|jpg|json|jpeg|gif|md)$/', $uri)) {
                $request->isAssetRequest(true);

                // Resolve the request target and CONFINE it to the public directory. `$uri` is
                // the raw REQUEST_URI, so `/../../secrets.json` used to be concatenated straight
                // onto public_path() and read with file_get_contents — the extension allowlist
                // does not stop it, because the traversal target only has to END in an allowed
                // extension. Browsers normalise `..` before sending, but a raw client does not,
                // and `.json`/`.pdf`/`.md` outside the webroot is exactly where service-account
                // keys and uploaded documents live. (Same realpath-confinement as
                // Response::download().)
                $real = realpath(public_path() . $uri);
                $publicRoot = realpath(public_path());

                $confined = $real !== false
                    && $publicRoot !== false
                    && is_file($real)
                    && str_starts_with($real, $publicRoot . DIRECTORY_SEPARATOR);

                if ($confined) {
                    $path = $real;
                    $mime = mime_content_type($path);
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    if (array_key_exists($ext, $customMappings)) {
                        $mime = $customMappings[$ext];
                    }
                    $allowedOrigins = config('cors.allowed_origins', ['*']);
                    $origin = $request->headers('Origin');

                    $response = Response::setHeader("Content-Type", $mime, BaseResponse::STATUS_OK);

                    // Never let a browser second-guess the declared type. A public asset is the
                    // one place where sniffing has no upside and a real downside: an app serving
                    // user-uploaded files from its own origin turns a polyglot — a byte sequence
                    // that is a valid image AND parses as HTML, e.g. `GIF89a<script>…` — into
                    // same-origin stored XSS. Refusing to store such files is not a workable
                    // defence (a real photograph can carry "<script>" in its EXIF), so declaring
                    // the type authoritatively is.
                    //
                    // NOTE this does not make SVG safe: `image/svg+xml` is honoured, not sniffed,
                    // and SVG is scriptable. Don't serve untrusted SVG from an origin that matters.
                    $response->setHeader("X-Content-Type-Options", "nosniff");

                    if ($allowedOrigins[0] === '*' || in_array($origin, $allowedOrigins)) {
                        $response->setHeader("Access-Control-Allow-Origin", '*');
                        $response->setHeader('Access-Control-Allow-Methods', "GET, OPTIONS");
                        $response->setHeader('Access-Control-Allow-Headers', "Content-Type");
                    }

                    /*
                     * Cache validators, so an unchanged asset costs a 304 with no body instead of
                     * a full re-download.
                     *
                     * `max-age` defaults to 0 — always revalidate, never serve stale. This
                     * middleware serves EVERY public asset and cannot tell which filenames are
                     * content-hashed, so a long default would land on mutable assets too, and a
                     * stale asset cannot be withdrawn once a client has cached it. An operator who
                     * knows their assets are hashed can raise it via config.
                     */
                    $mtime = filemtime($path);
                    $etag = '"' . dechex((int) $mtime) . '-' . dechex((int) filesize($path)) . '"';
                    $lastModified = gmdate('D, d M Y H:i:s', (int) $mtime) . ' GMT';
                    $maxAge = (int) config('app.asset_cache_max_age', 0);

                    $response->setHeader('ETag', $etag);
                    $response->setHeader('Last-Modified', $lastModified);
                    $response->setHeader(
                        'Cache-Control',
                        $maxAge > 0 ? "public, max-age=$maxAge" : 'public, max-age=0, must-revalidate'
                    );

                    if ($this->isUnchanged($request, $etag, (int) $mtime)) {
                        // Clear the body explicitly rather than relying on the send path to drop
                        // it: the response is a shared instance, so it can still be carrying the
                        // previous asset, and a 304 object that holds content is a trap for
                        // anything that inspects it before sending.
                        return $response->body('')->status(BaseResponse::STATUS_NOT_MODIFIED);
                    }

                    return $response->body(file_get_contents($path));
                }

                return Response::plain("File Not Found", BaseResponse::STATUS_NOT_FOUND);
            }
        }

        return $next($request);
    }

    /**
     * Whether the client's copy is still current, per RFC 9110 §13.
     *
     * `If-None-Match` wins outright when present — an entity tag is an exact identity check, while
     * `If-Modified-Since` has only one-second resolution and so cannot distinguish two edits within
     * the same second. Checking the date as a fallback would let that coarser test override the
     * precise one.
     */
    private function isUnchanged(Request $request, string $etag, int $mtime): bool
    {
        $ifNoneMatch = $request->headers('If-None-Match');

        if (is_string($ifNoneMatch) && $ifNoneMatch !== '') {
            if (trim($ifNoneMatch) === '*') {
                return true;
            }

            foreach (explode(',', $ifNoneMatch) as $candidate) {
                // Weak comparison: a `W/` prefix still matches, which is what GET requires.
                $candidate = trim($candidate);
                $candidate = str_starts_with($candidate, 'W/') ? substr($candidate, 2) : $candidate;

                if ($candidate === $etag) {
                    return true;
                }
            }

            return false;
        }

        $ifModifiedSince = $request->headers('If-Modified-Since');

        if (is_string($ifModifiedSince) && $ifModifiedSince !== '') {
            $since = strtotime($ifModifiedSince);

            return $since !== false && $mtime <= $since;
        }

        return false;
    }
}
