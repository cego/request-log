<?php

namespace Cego\RequestLog\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Cego\RequestLog\Models\RequestLog;
use Illuminate\Support\Facades\Config;
use Cego\RequestLog\Models\RequestLogBlacklistedRoute;

class LogRequest
{
    /**
     * Holds the start time of the request
     *
     * @var string|float $startTime
     */
    protected $startTime;

    /**
     * Holds the end time of the request
     *
     * @var string|float $endTime
     */
    protected $endTime;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->startTime = microtime(true);

        // Proceed to the next middleware
        return $next($request);
    }

    /**
     * Writes the RequestLog to the database once the request has terminated
     *
     * @param mixed $request
     * @param mixed $response
     *
     * @return void
     */
    public function terminate($request, $response)
    {
        $this->endTime = microtime(true);

        $executionTime = $this->endTime - $this->startTime;

        if ($executionTime < 0) {
            $executionTime = 0;
        }

        // We need to check if the path of the current request is blacklisted
        // if it is we out-out here and to not write a RequestLog entry
        if ($this->routeIsBlacklisted($request)) {
            return;
        }

        RequestLog::create([
            'client_ip'          => $request->ip(),
            'user_agent'         => $request->userAgent(),
            'method'             => $request->method(),
            'status'             => $response->status(),
            'url'                => $request->url(),
            'root'               => $request->root(),
            'path'               => $request->path(),
            'query_string'       => json_encode($request->query()),
            'request_headers'    => json_encode($request->headers->all()),
            'request_body'       => $request->getContent() ?: '{}',
            'response_headers'   => json_encode($response->headers->all()),
            'response_body'      => $response->getContent() ?: '{}',
            'response_exception' => json_encode($response->exception),
            'execution_time'     => $executionTime,
        ]);
    }

    /**
     * Checks if the path of the request is a blacklisted route
     *
     * @param mixed $request
     *
     * @return bool
     */
    protected function routeIsBlacklisted($request)
    {
        $configBlackListedRoutes = Config::get('request-log.blackListedRoutes', []);

        // We get the list of blacklisted routes from the cache if present
        // or get it from the database and cache it forever
        $blacklistedRoutes = Cache::rememberForever('request-log.blacklistedUrls', function () {
            return RequestLogBlacklistedRoute::all();
        });

        $blacklistedRoutes = $blacklistedRoutes->map(fn (RequestLogBlacklistedRoute $route) => $route->path)->toArray();

        $blacklistedRoutes = array_merge($configBlackListedRoutes, $blacklistedRoutes);

        /** @var RequestLogBlacklistedRoute $route */
        foreach ($blacklistedRoutes as $route) {
            if (fnmatch($route, $request->path(), FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }
}
