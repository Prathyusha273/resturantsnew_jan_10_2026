<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/debug-store-impersonation',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Illuminate\Session\TokenMismatchException
     */
    public function handle($request, \Closure $next)
    {
        if (
            $this->isReading($request) ||
            $this->runningUnitTests() ||
            $this->inExceptArray($request) ||
            $this->tokensMatch($request)
        ) {
            return tap($next($request), function ($response) use ($request) {
                if ($this->shouldAddXsrfTokenCookie($request)) {
                    $this->addCookieToResponse($request, $response);
                }
            });
        }

        // If token mismatch, try to regenerate session and redirect back
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'CSRF token mismatch. Please refresh the page and try again.',
                'error' => 'token_mismatch'
            ], 419);
        }

        throw new TokenMismatchException('CSRF token mismatch.');
    }
}
