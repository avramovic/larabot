<?php

use Laravel\Mcp\Facades\Mcp;

Route::group(['middleware' => ['api']], function () {
    Mcp::web('/mcp/larabot', \App\Mcp\Servers\LarabotServer::class);
});

//php artisan make:mcp-server CliServer
