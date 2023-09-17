<?php

namespace Porygon\LaravelEchoServer\PHPSocketIO;

use Workerman\Worker;
use Porygon\LaravelEchoServer\Engine\Engine;
use PHPSocketIO\Client;
use PHPSocketIO\SocketIO as PHPSocketIOSocketIO;

class SocketIO extends PHPSocketIOSocketIO
{
    public function attach($srv, $opts = [])
    {
        $engine = app()->make(config("echo-server.engine"));
        $this->eio = $engine->attach($srv, $opts);

        // Export http server
        $this->worker = $srv;

        // bind to engine events
        $this->bind($engine);

        return $this;
    }
}
