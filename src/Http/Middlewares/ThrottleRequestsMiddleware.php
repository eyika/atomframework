<?php

namespace Basttyy\FxDataServer\Middlewares;

use Exception;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Request;
use PDOException;

class ThrottleRequestsMiddleware implements MiddlewareInterface
{
    private $requestsPerUser = [];
    private $requestsPerIp = [];

    public function handle(Request $request, ...$params): bool
    {
        try {
            logger()->info('this is from throttleRequest middleware', $params);
            $ipAddress = getIpAddress($request);
            $currentTime = time();
            $user = $request->auth_user ?? null;
            [$limit, $timeFrame] = $params;
    
            // Throttle by User ID
            if ($user) {
                if (!$this->isAllowed($this->requestsPerUser, $user->id, $limit, $currentTime, $timeFrame)) {
                    http_response_code(429); // Too Many Requests
                    logger()->info("Too many requests for user ID: $user->id. Please try again later.");
                    exit;
                }
            } else {
                // Throttle by IP Address
                if (!$this->isAllowed($this->requestsPerIp, $ipAddress, $limit, $currentTime, $timeFrame)) {
                    http_response_code(429); // Too Many Requests
                    logger()->info("Too many requests from IP: $ipAddress. Please try again later.");
                    exit;
                }
            }
    
            // Process the request (placeholder for actual request handling logic)
            logger()->info("Request processed successfully.");
        } catch (PDOException $e) {
            // consoleLog(0, $e->getMessage(). '   '.$e->getTraceAsString());
        } catch (Exception $e) {
            // consoleLog(0, $e->getMessage(). '   '.$e->getTraceAsString());
        }
        return false;
    }

    private function isAllowed(&$storage, $identifier, $limit, $currentTime, $timeFrame)
    {
        logger()->info('the storage content is: ', $storage);
        if (!isset($storage[$identifier])) {
            $storage[$identifier] = ['count' => 0, 'start' => $currentTime];
        }

        if ($currentTime - $storage[$identifier]['start'] > $timeFrame) {
            // Reset count and timeframe
            $storage[$identifier] = ['count' => 1, 'start' => $currentTime];
        } else {
            // Increment count
            $storage[$identifier]['count']++;
        }

        return $storage[$identifier]['count'] <= $limit;
    }
}
