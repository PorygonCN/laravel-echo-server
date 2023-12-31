<?php

namespace Porygon\LaravelEchoServer\Channels;

use Porygon\LaravelEchoServer\ConsoleOutput;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use PHPSocketIO\Socket;
use PHPSocketIO\SocketIO;

class PrivateChannel  extends ConsoleOutput
{
    function __construct(public SocketIO $io, public $options)
    {
        parent::__construct();
    }

    public function authenticate(Socket $socket, $data)
    {
        $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "authorizating socket [$socket->id]");

        $authenticate = false;
        $request      = Http::withHeaders($this->prepareHeaders($socket, $data));
        $this->options["dev_mode"] && $request->withOptions(["verify" => false]);
        $response = $request->post($this->options["authorizate"]["host"] . $this->options["authorizate"]["api"], ["channel_name" => $data["channel"]]);
        if ($response->status() == Response::HTTP_OK) {
            $authenticate = true;
            $res          = $response->json();
        } else {
            $res = [
                "reason" => 'Client can not be authenticated, got HTTP status ' . $response->status(),
                "status" => $response->status()
            ];
        }
        $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "socket [$socket->id] authenticate [$authenticate],res : " . json_encode($res));

        return [$authenticate, $res];
    }


    public function prepareHeaders(Socket $socket, $data)
    {
        $dataHeaders = $data["auth"]["headers"] ?? [];
        $headers = [
            'Cookie'           => $socket->request->headers["cookie"] ?? "",
            'X-Requested-With' => "XMLHttpRequest",
        ];

        return array_merge($headers, $dataHeaders);
    }

    public static function make(...$arg)
    {
        return new static(...$arg);
    }
}
