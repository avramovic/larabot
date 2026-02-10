<?php

namespace App\Providers;

use App\Channels\ChatInterface;
use App\Channels\Telegram\Telegram;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Laravel\Mcp\Server\Transport\StdioTransport;
use Telegram\Bot\Api;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChatInterface::class, function ($app) {
           return new Telegram();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
