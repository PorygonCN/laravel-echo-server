<?php

namespace Porygon\LaravelEchoServer\PHPSocketIO\Engine;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPSocketIO\Engine\Protocols\Http\Request;
use PHPSocketIO\Engine\Protocols\Http\Response;
use PHPSocketIO\Engine\Engine as EngineEngine;
use PHPSocketIO\Engine\Socket;
use PHPSocketIO\SocketIO;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Engine extends EngineEngine
{
    /**
     * @var SocketIO
     */
    public $io;

    public static $allowTransports = [
        'polling'   => 'polling',
        'websocket' => 'websocket',
        'api'       => 'api'
    ];
    /**
     * @throws Exception
     */
    public function handshake($transport, $req)
    {
        if ($transport == 'websocket') {
            $transport = '\\PHPSocketIO\\Engine\\Transports\\WebSocket';
        } elseif ($transport == 'api') {
            $transport = config("echo-server.polling_api");
        } elseif (isset($req->_query['j'])) {
            $transport = '\\PHPSocketIO\\Engine\\Transports\\PollingJsonp';
        } else {
            $transport = '\\PHPSocketIO\\Engine\\Transports\\PollingXHR';
        }

        $transport = new $transport($req);

        $transport->worker = $this;

        $transport->supportsBinary = !isset($req->_query['b64']);

        $id = bin2hex(pack('d', microtime(true)) . pack('N', function_exists('random_int') ? random_int(1, 100000000) : rand(1, 100000000)));
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
                $this->handleResult($result, $req, $res);
            } else {
                $result = fail("UnAuthorizated", 403, 403);
                $this->handleResult($result, $req, $res);
            }
            return true;
        } catch (NotFoundHttpException $e) {
            $result = fail("Route Not Found", 404, 404);
            $this->handleResult($result, $req, $res);
        } catch (MethodNotAllowedHttpException $e) {
            $result = fail("Method Not Allow For This Route", 404, 404);
            $this->handleResult($result, $req, $res);
        }
    }

    /**
     * 处理api请求返回结果
     */
    protected function handleResult($result, $req, $res)
    {
        $res->setHeader("content-type", "application/json");
        $res->writeHead(200);
        try {
            if ($result instanceof JsonResponse) {
                $headers = $result->headers;
                $result  = $result->getOriginalContent();
                foreach ($headers as $key => $header) {
                    $res->setHeader($key, is_array($header) ? $header[0] : $header);
                }
            }
            $result = json_encode($result);
            $res->end($result);
        } catch (Exception $e) {
            $rid    = Str::ulid();
            $result = error("server error rid:$rid");
            Log::error("[handleApi] fail rid:$rid error:{$e->getMessage()}");
            $this->handleResult($result);
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
