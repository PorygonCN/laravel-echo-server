<?php

namespace Porygon\LaravelEchoServer\PHPSocketIO\Engine;

use Exception;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPSocketIO\Engine\Protocols\Http\Request;
use PHPSocketIO\Engine\Protocols\Http\Response;
use PHPSocketIO\Engine\Engine as EngineEngine;
use PHPSocketIO\Engine\Socket;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Engine extends EngineEngine
{
    /**
     * @throws Exception
     */
    public function handshake($transport, $req)
    {
        $id = bin2hex(pack('d', microtime(true)) . pack('N', function_exists('random_int') ? random_int(1, 100000000) : rand(1, 100000000)));
        if ($transport == 'websocket') {
            $transport = '\\PHPSocketIO\\Engine\\Transports\\WebSocket';
        } elseif (isset($req->_query['j'])) {
            $transport = '\\PHPSocketIO\\Engine\\Transports\\PollingJsonp';
        } elseif (isset($req->_query['api'])) {
            $transport = config("echo-server.polling_api");
        } else {
            $transport = '\\PHPSocketIO\\Engine\\Transports\\PollingXHR';
        }

        $transport = new $transport($req);

        $transport->worker = $this;

        $transport->supportsBinary = !isset($req->_query['b64']);

        $socket = new Socket($id, $this, $transport, $req);

        /* $transport->on('headers', function(&$headers)use($id)
        {
            $headers['Set-Cookie'] = "io=$id";
        }); */

        $transport->onRequest($req);

        $this->clients[$id] = $socket;
        $socket->once('close', [$this, 'onSocketClose']);

        $this->emit('connection', $socket);
    }

    protected function verify($req, $res, $upgrade, $fn)
    {
        if (!isset($req->_query['transport']) || !isset(self::$allowTransports[$req->_query['transport']])) {
            return call_user_func($fn, self::ERROR_UNKNOWN_TRANSPORT, false, $req, $res);
        }
        $transport = $req->_query['transport'];
        $sid = $req->_query['sid'] ?? '';
        /*if ($transport === 'websocket' && empty($sid)) {
            return call_user_func($fn, self::ERROR_UNKNOWN_TRANSPORT, false, $req, $res);
        }*/
        if ($sid) {
            if (!isset($this->clients[$sid])) {
                return call_user_func($fn, self::ERROR_UNKNOWN_SID, false, $req, $res);
            }
            if (!$upgrade && $this->clients[$sid]->transport->name !== $transport) {
                return call_user_func($fn, self::ERROR_BAD_REQUEST, false, $req, $res);
            }
        } else {
            // POST请求也能处理
            // if ('GET' !== $req->method) {
            //     return call_user_func($fn, self::ERROR_BAD_HANDSHAKE_METHOD, false, $req, $res);
            // }
            return $this->checkRequest($req, $res, $fn);
        }
        call_user_func($fn, null, true, $req, $res);
    }



    /**
     * 处理api请求
     */
    public function handleApi(Request $req, Response $res, $data = null)
    {
        $this->registerRoutes($req, $res, $data);

        $request = HttpRequest::create($req->headers["host"] . $req->url, $req->method);

        try {
            /** @var RoutingRoute */
            $route = Route::getRoutes()->match($request);
            if ($this->checkAuth($req, $route)) {
                $result = $route->run();
                try {
                    $result = json_encode($result);
                } catch (Exception $e) {
                }
                $res->setHeader("content-type", "application/json");
                $res->writeHead(200);
                $res->end($result);
            } else {
                $res->writeHead(403);
                $res->end("UnAuthorizated");
            }
            return true;
        } catch (NotFoundHttpException $e) {
            $res->writeHead(404);
            $res->end("Route Not Found");
        } catch (MethodNotAllowedHttpException $e) {
            $res->writeHead(404);
            $res->end("Method Not Allow For This Route");
        }
    }

    protected function checkAuth($req, RoutingRoute $route)
    {
        $appid     = $route->parameter("appid");
        $app       = (config("echo-server.app_model"))::where("appid", $appid)->first();
        return $app && $app->auth_key == $req->_query["auth_key"];
    }

    /**
     * 注册路由和处理方法
     */
    public function registerRoutes(Request $req, Response $res, $data = null)
    {
        require base_path("routes/echo-server-http-subscriber.php");
    }
}
