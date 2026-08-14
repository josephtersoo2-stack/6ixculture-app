<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $envKey = env('VITE_API_KEY');
        if (empty($envKey)) {
            return $next($request);
        }
        if ($request->hasHeader('x-api-key')) {
            if ($request->header('x-api-key') == $envKey) {
                return $next($request);
            }
        }
        return response()->json(trans('all.message.invalid_api_key'), 400);
    }
}
