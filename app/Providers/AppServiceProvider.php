<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The admin theme is Bootstrap 5 (data-bs-* attributes throughout),
        // but Laravel's pagination views default to Tailwind markup — without
        // this, {{ $paginator->links() }} renders unstyled links.
        Paginator::useBootstrapFive();
    }
}
