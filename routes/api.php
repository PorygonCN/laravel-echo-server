<?php

use Illuminate\Support\Facades\Route;


Route::group([
    'prefix'     => config('echo-server.api.prefix'),
    'middleware' => config('echo-server.api.middleware'),
    "as" => "echo-server.api."
], function () {
    
});
