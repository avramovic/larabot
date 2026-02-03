<?php

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Laravel\Mcp\Facades\Mcp;

Route::group(['middleware' => ['api']], function () {
    Mcp::web('/mcp/cli', \App\Mcp\Servers\CliServer::class);
});

//php artisan make:mcp-server CliServer
