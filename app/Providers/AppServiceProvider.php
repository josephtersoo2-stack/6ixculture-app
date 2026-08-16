<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Support\Contracts\AiOrchestratorInterface::class,
            \App\Support\Services\SupportOrchestrator::class
        );
        $this->app->bind(
            \App\Support\Contracts\PolicyEngineInterface::class,
            \App\Support\Policies\SupportActionPolicyEngine::class
        );
        $this->app->bind(
            \App\Support\Contracts\KnowledgeRepositoryInterface::class,
            \App\Support\Services\SupportKnowledgeRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
