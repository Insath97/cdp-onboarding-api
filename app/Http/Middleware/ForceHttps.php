<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     * Redirect HTTP requests to HTTPS in production.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            app()->environment('production') &&
            !$request->secure() &&
            !config('app.debug')
        ) {
            return redirect()->to($request->url(), 301)->secure();
        }

        return $next($request);
    }
}
