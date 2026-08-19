<?php

namespace App\Http\Middleware\Support;

use App\Support\Cutover\SupportCutoverManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GateLegacyChatMutationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!SupportCutoverManager::canMutateLegacy()) {
            return response()->json([
                'status' => false,
                'code' => 'LEGACY_CHAT_UNAVAILABLE',
                'message' => 'This chat service is no longer available. Please use the current support experience.',
            ], Response::HTTP_LOCKED);
        }

        return $next($request);
    }
}
