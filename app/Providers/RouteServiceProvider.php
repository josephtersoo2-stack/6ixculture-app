<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('support-conversations', function (Request $request) {
            $user = $request->user('sanctum') ?? $request->user();
            return $user
                ? Limit::perMinute(30)->by($user->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('support-messages', function (Request $request) {
            $user = $request->user('sanctum') ?? $request->user();
            $guestKey = $request->header('X-Guest-Token') ?: $request->input('guest_token') ?: $request->ip();
            return $user
                ? Limit::perMinute(60)->by($user->id)
                : Limit::perMinute(30)->by($guestKey);
        });

        RateLimiter::for('support-voice', function (Request $request) {
            $user = $request->user('sanctum') ?? $request->user();
            $guestKey = $request->header('X-Guest-Token') ?: $request->input('guest_token') ?: $request->ip();
            return $user
                ? Limit::perMinute(30)->by($user->id)
                : Limit::perMinute(15)->by($guestKey);
        });

        RateLimiter::for('support-agent', function (Request $request) {
            return Limit::perMinute(120)->by($request->user('sanctum')?->id ?: $request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('support-admin', function (Request $request) {
            return Limit::perMinute(60)->by($request->user('sanctum')?->id ?: $request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function mapWebRoutes()
    {
        if (file_exists(storage_path('installed'))) {

            try {
                $files = scandir(__DIR__ . '/../Http/PaymentGateways/Routes');
                if (count($files) > 2) {
                    foreach ($files as $file) {
                        if ($file != '.' && $file != '..') {
                            Route::middleware('web')
                                ->group(__DIR__ . "/../Http/PaymentGateways/Routes/{$file}");
                        }
                    }
                }
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
        }
    }
}
