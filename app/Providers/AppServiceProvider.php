<?php

namespace App\Providers;

use App\Channels\ChannelResolver;
use App\Channels\ChatInterface;
use App\Channels\Telegram\Telegram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

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

        $this->app->singleton(ChannelResolver::class, ChannelResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA journal_mode=WAL');
            DB::statement('PRAGMA synchronous=NORMAL');
            DB::statement('PRAGMA busy_timeout=30000');
            DB::statement('PRAGMA cache_size=-64000');
        }
    }
}
