<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class PreventRequestsDuringMaintenance implements MiddlewareInterface
{
    /**
     * The URIs that should be accessible while in maintenance mode.
     *
     * @var array
     */
    protected $except = [];

    /**
     * Create a new middleware instance.
     *
     * @param array $except
     */
    public function __construct(array $except = [])
    {
        $this->except = $except;
    }

    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        // Check if the application is in maintenance mode
        if ($this->isDownForMaintenance()) {
            // If the request is not in the excepted URIs, block it
            if (!$this->inExceptArray($request)) {
                return $this->makeMaintenanceResponse();
            }
        }

        return false;
    }

    /**
     * Determine if the application is in maintenance mode.
     *
     * @return bool
     */
    protected function isDownForMaintenance()
    {
        // In Laravel, this would typically check for a file like 'storage/framework/down'
        // You can replace this with your actual maintenance mode check
        return file_exists(__DIR__ . '/storage/framework/maintainance.php');
    }

    /**
     * Determine if the request has a URI that should pass through maintenance mode.
     *
     * @param Request $request
     * @return bool
     */
    protected function inExceptArray(Request $request)
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a response for requests that are prevented due to maintenance mode.
     *
     * @return Response
     */
    protected function makeMaintenanceResponse()
    {
        return Response::plain('Service Unavailable', 503);
    }

    /**
     * Set the URIs that should be accessible while in maintenance mode.
     *
     * @param array $except
     * @return $this
     */
    public function except(array $except)
    {
        $this->except = $except;

        return $this;
    }
}
