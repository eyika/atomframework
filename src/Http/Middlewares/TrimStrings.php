<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class TrimStrings implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @return mixed
     */
    public function handle(Request $request): bool
    {
        $this->clean($request);

        return false;
    }

    /**
     * Clean the request's data by trimming whitespace.
     *
     * @param Request $request
     * @return void
     */
    protected function clean(Request $request)
    {
        $input = $request->input();
        $query = $request->query();

        $cleanedInput = $this->trimArray($input);
        $cleanedQuery = $this->trimArray($query);

        $request->replaceInput($cleanedInput);
        $request->replaceQuery($cleanedQuery);
    }

    /**
     * Trim all of the values in the array.
     *
     * @param array $data
     * @return array
     */
    protected function trimArray(array $data)
    {
        return array_map(function ($value) {
            return is_array($value) ? $this->trimArray($value) : trim($value);
        }, $data);
    }
}

// Example usage
// $request = Request::createFromGlobals();
// $middleware = new TrimStrings();

// $response = $middleware->handle($request, function ($req) {
//     // Further processing...
//     return new Response('OK', 200);
// });
