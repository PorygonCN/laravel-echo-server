<?php

namespace Porygon\LaravelEchoServer\PHPSocketIO\Engine\Transports;

use PHPSocketIO\Engine\Engine;
use PHPSocketIO\Engine\Transports\PollingXHR;

class PollingApi extends PollingXHR
{
    /**
     * @var Engine
     */
    public $worker;

    public function onData($data)
    {
        return  $data = json_decode($data);
    }

    public function dataRequestOnEnd()
    {
        $data = $this->onData($this->chunks);

        $this->worker->handleApi($this->dataReq, $this->dataRes, $data);

        $this->dataRequestCleanup();
    }
}
