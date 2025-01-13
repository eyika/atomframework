<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Response;

class ConvertEmptyStringsToNull implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request, Closure $next): Response|string
    {
        $this->clean($request);

        return $next($request);
    }

    /**
     * Clean the request's data by converting empty strings to null.
     *
     * @param Request $request
     * @return void
     */
    protected function clean(Request $request)
    {
        $input = $request->input();
        $query = $request->query();

        $cleanedInput = $this->convertEmptyStringsToNull($input);
        $cleanedQuery = $this->convertEmptyStringsToNull($query);

        $request->replaceInput($cleanedInput);
        $request->replaceQuery($cleanedQuery);
    }

    /**
     * Recursively convert all empty strings in the array to null.
     *
     * @param array $data
     * @return array
     */
    protected function convertEmptyStringsToNull(array $data)
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->convertEmptyStringsToNull($value);
            }

            return $value === '' ? null : $value;
        }, $data);
    }
}
