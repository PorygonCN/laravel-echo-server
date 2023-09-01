<?php

namespace Porygon\LaravelEchoServer;

use Porygon\LaravelEchoServer\Channels\Channel;
use Porygon\LaravelEchoServer\Databases\DatabaseAdapter;
use Porygon\LaravelEchoServer\Events\SocketClientEvent;
use Porygon\LaravelEchoServer\Events\SocketConnectedEvent;
use Porygon\LaravelEchoServer\Events\SocketDisConnectedEvent;
use PHPSocketIO\Socket;
use Illuminate\Console\Concerns;

class Server extends ConsoleOutput
{
    use Concerns\InteractsWithIO;

    protected $global_uid = 0;
    /**
     * @var SocketIo
     */
    protected $io;
    /**
     * @var Channel
     */
    protected $channel;

    protected $options;
    /**
     * @var DatabaseAdapter
     */
    protected $db;
    protected $subscribers = [];

    public function __construct($options = [])
    {
        parent::__construct();

        // Worker::$pidFile     = app_path("Console/Commands/EchoServer/Server/pid/server.pid");
        $this->options       = collect($options);
        $this->db            = app()->make($this->options["database"]);
        $this->subscribers[] = app()->make($this->options["redis_subscriber"]);
    }

    public function handle()
    {
        return $this->initWorker();
    }

    protected function initWorker()
    {
        $context = [];
        $port    = $this->options["port"];
        $use_ssl = $this->options["use_ssl"];
        if ($use_ssl) {
            $context['ssl'] = $this->options["ssl"];
            $this->options['dev_mode'] && $this->warn("Start with SSL");
        }
        if (!$port) {
            $this->error("Undefined Port!");
            return false;
        }

        /**
         * 初始化socketio
         */
        $this->io = app()->make($this->options["socketIO"], ["port" => $port, "opts" => $context]);

        /**
         * 添加启动监听
         */
        $this->io->on("workerStart", function () {
            $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "Echo Server Started");
            $this->listen();
        });

        /**
         * 添加事件监听
         */
        $this->onConnect();

        /**
         * 配置频道
         */
        $this->channel = app()->make($this->options["channel"], ["io" => $this->io, "options" => $this->options]);
        $this->onChannelJoin();
        return $this;
    }

    public function onChannelJoin()
    {
        $this->channel->addOnJoin(function (Socket $socket, $channel) {
        });
    }

    public function onConnect()
    {
        $this->io->on('connection', function ($socket) {
            $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "new socket [$socket->id] connected");
            $this->onSubscribe($socket);
            $this->onUnsubscribe($socket);
            $this->onDisconnecting($socket);
            $this->onClientEvent($socket);
            event(new SocketConnectedEvent($socket));
        });
    }

    public function onSubscribe(Socket $socket)
    {
        $socket->on(
            'subscribe',
            function ($data) use ($socket) {
                $this->channel->join($socket, $data);
            }
        );
    }

    public function onUnsubscribe($socket)
    {
        $socket->on(
            'unsubscribe',
            function ($data) use ($socket) {
                $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "unsubscribe socket[{$socket->id}]");

                $this->channel->leave($socket, $data["channel"], 'unsubscribed');
            }
        );
    }

    public function onDisconnecting(Socket $socket)
    {
        $socket->on(
            'disconnecting',
            function ($reason) use ($socket) {
                $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "disconnecting socket[{$socket->id}]");

                foreach ($socket->rooms as $room) {
                    $this->channel->leave($socket, $room, $reason);
                }
                event(new SocketDisConnectedEvent($socket, $reason));
            }
        );
    }

    public function onClientEvent($socket)
    {
        $socket->on(
            'client event',
            function ($data) use ($socket) {
                $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "client event socket[{$socket->id}]");

                $this->channel->clientEvent($socket, $data);
                event(new SocketClientEvent($socket, $data));
            }
        );
    }

    /**
     * 开启外部监听
     */
    public function listen()
    {
        foreach ($this->subscribers as $subscriber) {
            $subscriber->subscribe(fn ($channel, $message) => $this->broadcast($channel, $message));
        }
    }

    /**
     * 频道内广播信息
     */
    public function broadcast($channel, $message)
    {
        $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "boardcasting [$message->event] to channel [$channel]");
        if ($message->socket && $this->find($message->socket)) {
            return $this->toOthers($this->find($message->socket), $channel, $message);
        } else {
            return $this->toAll($channel, $message);
        }
    }

    /**
     * 查找socket
     */
    public function find($socket_id)
    {
        return $this->io->sockets->connected[$socket_id] ?? null;
    }

    /**
     * 给频道其他人发送信息
     */
    public function toOthers(Socket $socket, $channel, $message)
    {
        $socket->broadcast->to($channel)->emit($message->event, $channel, $message->data);
        return true;
    }

    /**
     * 全员发送信息
     */
    public function toAll($channel, $message)
    {
        $this->io->to($channel)->emit($message->event, $channel, $message->data);
        return true;
    }
}
