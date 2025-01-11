<?php

use Eyika\Atom\Framework\Http\Request;

class XSSProtection
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @return bool
     */
    public function handle(Request &$request): bool
    {
        // Sanitize the request inputs
        $request = $this->sanitizeInputs($request);

        // Proceed with the next middleware or request handler
        return false;
    }

    /**
     * Sanitize request inputs to prevent XSS attacks.
     *
     * @param Request $request
     * @return Request
     */
    protected function sanitizeInputs(Request $request): Request
    {
        $data = $request->input(); // Get all POST data
        $data = $this->sanitize($data);
        $request->replaceInput($data); // Replace POST data with sanitized data

        $query = $request->query(); // Get all GET data
        $query = $this->sanitize($query);
        $request->replaceQuery($query); // Replace GET data with sanitized data

        return $request;
    }

    /**
     * Sanitize data by escaping HTML special characters.
     *
     * @param array $data
     * @return array
     */
    protected function sanitize(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->sanitize($value);
            } else {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $data;
    }
}

// Example usage
// $request = Request::createFromGlobals();
// $middleware = new XSSProtection();

// $response = $middleware->handle($request, function ($req) {
//     // Simulating a response
//     return new Response('Request sanitized', 200);
// });

// $response->send();
