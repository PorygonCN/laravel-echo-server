<?php

namespace Porygon\LaravelEchoServer\Middlewares;

use Porygon\LaravelEchoServer\Models\EchoServerApp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EchoServerApps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appid = $request->route("appid");
        $app = EchoServerApp::where("appid", $appid)->firstOrFail();
        if ($app->key == $request->auth_key) {
            return $next($request);
        } else {
            abort(403, "UnAuthorizated");
        }
    }
}
