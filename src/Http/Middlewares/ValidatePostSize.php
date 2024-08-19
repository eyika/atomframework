<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class ValidatePostSize implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        // Check if the content length exceeds the post_max_size
        if ($this->isRequestTooLarge($request)) {
            return $this->handleRequestTooLarge($request);
        }

        return true;
    }

    /**
     * Determine if the request size exceeds the post_max_size.
     *
     * @param Request $request
     * @return bool
     */
    protected function isRequestTooLarge(Request $request)
    {
        $postMaxSize = $this->getPostMaxSize();
        $contentLength = $request->server->get('CONTENT_LENGTH', 0);

        return $postMaxSize > 0 && $contentLength > $postMaxSize;
    }

    /**
     * Handle a request that is too large.
     *
     * @param Request $request
     * @return Response
     */
    protected function handleRequestTooLarge(Request $request)
    {
        return Response::plain('Payload Too Large', 413);
    }

    /**
     * Get the PHP post_max_size configuration as an integer value in bytes.
     *
     * @return int
     */
    protected function getPostMaxSize()
    {
        $postMaxSize = ini_get('post_max_size');

        return $this->convertToBytes($postMaxSize);
    }

    /**
     * Convert a PHP size string to bytes.
     *
     * @param string $size
     * @return int
     */
    protected function convertToBytes($size)
    {
        $unit = strtolower(substr($size, -1));
        $size = (int) $size;

        switch ($unit) {
            case 'g':
                return $size * 1024 * 1024 * 1024;
            case 'm':
                return $size * 1024 * 1024;
            case 'k':
                return $size * 1024;
            default:
                return $size;
        }
    }
}

// Example usage
// $request = Request::createFromGlobals();
// $middleware = new ValidatePostSize();

// $response = $middleware->handle($request, function ($req) {
//     // Further processing...
//     return new Response('OK', 200);
// });

// if ($response->getStatusCode() == 413) {
//     echo $response->getContent(); // Output: Payload Too Large
// } else {
//     // Process the request normally
// }
