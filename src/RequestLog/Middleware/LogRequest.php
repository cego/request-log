<?php

namespace Cego\RequestLog\Middleware;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Cego\RequestLog\Data\RequestLog;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Cego\RequestLog\Utilities\SecurityUtility;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    /**
     * Holds the start time of the request
     *
     * @var int $startTime
     */
    protected $startTime = null;

    /** Holds the request cookies. Some middleware will change the cookies, thus we need to save it from handle
     *
     * @var array<string, string> $requestCookies
     */
    protected $requestCookies = [];

    /**
     * Holds the received request
     */
    protected ?RequestLog $receivedRequest = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->startTime = hrtime(true);

        $this->requestCookies = $request->cookies->all();

        // Proceed to the next middleware
        return $next($request);
    }

    /**
     * Updates the RequestLog in the database once the request has terminated
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->logRequest($request, $response);
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
        $blacklistedRoutes = Config::get('request-log.blackListedRoutes', []);

        foreach ($blacklistedRoutes as $route) {
            if (fnmatch($route, $request->path(), FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Truncate a string to a given length
     *
     * @param string $string
     * @param int $length
     *
     * @return string
     */
    protected function truncate(string $string, int $length): string
    {
        if($length <= 0) {
            return $string;
        }

        return mb_substr($string, 0, $length);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return void
     */
    private function logRequest(Request $request, Response $response): void
    {
        try {
            if (!config('request-log.enabled') || $this->routeIsBlacklisted($request)) {
                return;
            }

            $executionTimeNs = null
            if (!is_null($this->startTime)) {
                $executionTimeNs = hrtime(true) - $this->startTime;
            }

            $responseHeaders = $response->headers->all();
            unset($responseHeaders['set-cookie']);

            $truncateBodyLength = config('request-log.truncateBodyLength');

            (new RequestLog(
                method: $request->method(),
                url: $request->url(),
                routeUri: Route::getCurrentRoute()?->uri(),
                root: $request->root(),
                path: $request->path(),
                queryString: SecurityUtility::getQueryWithMaskingApplied($request),
                requestHeaders: SecurityUtility::getHeadersWithMaskingApplied($request),
                requestCookies: SecurityUtility::getCookiesWithMaskingApplied($this->requestCookies, $request),
                requestBody: $this->truncate(SecurityUtility::getBodyWithMaskingApplied($request) ?: '{}', $truncateBodyLength),
                status: $response->getStatusCode(),
                responseHeaders: $responseHeaders,
                responseCookies: SecurityUtility::getResponseCookiesWithMaskingApplied($response->headers->getCookies(), $request),
                responseBody: $this->truncate($response->getContent() ?: '{}', $truncateBodyLength),
                responseException: $response->exception ?? null,
                executionTimeNs: $executionTimeNs
            ))->log();

        } catch (Throwable $throwable) {
            Log::error($throwable);
        }
    }
}
