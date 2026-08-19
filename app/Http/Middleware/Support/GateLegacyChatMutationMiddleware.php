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
            $state = SupportCutoverManager::getState();

            return response()->json([
                'status' => false,
                'code' => 'LEGACY_CHAT_LOCKED',
                'cutover_state' => $state,
                'message' => "The legacy chat service is currently locked in '{$state}' cutover mode. All support operations have been transitioned to the modern 6ixCulture Support domain at /api/v1/support/*.",
            ], Response::HTTP_LOCKED);
        }

        return $next($request);
    }
}
