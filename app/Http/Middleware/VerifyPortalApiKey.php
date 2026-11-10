<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPortalApiKey
{
    /**
     * Verify the X-CDP-PORTAL-KEY header for external portal CV submissions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = (string) $request->header('X-CDP-PORTAL-KEY');
        $expectedKey = (string) config('services.portal.key');

        if ($expectedKey === '' || $providedKey === '') {
            return $this->reject();
        }

        if (! hash_equals($expectedKey, $providedKey)) {
            return $this->reject();
        }

        return $next($request);
    }

    /**
     * Reject the request with an unauthorized API key response.
     */
    private function reject(): Response
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'Invalid or missing X-CDP-PORTAL-KEY header',
        ], 401);
    }
}
