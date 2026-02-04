<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mcp/larabot', function () {
    header('Content-Type: text/event-stream');
    die('{"jsonrpc":"2.0","method":"larabot/ready","params":{}}');
});
