<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RedditScraperService;
use App\Services\GoogleDriveService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Reddit Scraper Service
        $this->app->singleton(RedditScraperService::class, function ($app) {
            return new RedditScraperService();
        });

        // Register Google Drive Service
        $this->app->singleton(GoogleDriveService::class, function ($app) {
            return new GoogleDriveService();
        });
    }

    public function boot(): void
    {
        //
    }
}
