<?php

use Illuminate\Support\Facades\Route;
use PHPSocketIO\Engine\Protocols\Http\Request;
use PHPSocketIO\Engine\Protocols\Http\Response;

/************************
 *
 * echo server apis
 *
 *************************/

/**
 * @param string appid echo server app's id
 * @use Request current request
 * @use Response current response
 */
Route::any("test", function ($appid) use (&$req, &$res) {
    return "this is a [$req->method] request";
});
