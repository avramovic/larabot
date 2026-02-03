<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mcp/cli', function () {
    header('Content-Type: text/event-stream');
    die('{"jsonrpc":"2.0","method":"cli/ready","params":{}}');
});
