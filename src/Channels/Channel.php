<?php

namespace Porygon\LaravelEchoServer\Channels;

use Porygon\LaravelEchoServer\ConsoleOutput;
use Porygon\LaravelEchoServer\Events\SocketJoinedChannelEvent;
use Porygon\LaravelEchoServer\Events\SocketLeftChannelEvent;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPSocketIO\Socket;
/**
 * channel 控制器
 */
class Channel  extends ConsoleOutput
{
    public $io;
    public $options;
    public $database;
    public $private;
    public $presence;
    public $privateChannels = ['private-*', 'presence-*'];
    public $clientEvents    = ['client-*'];
    public $onJoins         = [];

    public function __construct($io, $options)
    {
        parent::__construct();
        $this->io       = $io;
        $this->options  = optional($options);
        $this->presence = app()->make($this->options["presence_channel"], ["io" => $this->io, "options" => $this->options]);
        $this->private  = app()->make($this->options["private_channel"], ["io" => $this->io, "options" => $this->options]);
        $this->database = app()->make($this->options["database"]);

        $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . 'Channels are ready.');
    }
    public function addOnJoin($onJoin)
    {
        if (is_callable($onJoin)) {
            $this->onJoins[] = $onJoin;
        }
        return $this;
    }

    /**********************************
     *
     * 动作
     *
     **********************************/

    /**
     * 加入频道
     */
    public function join(Socket $socket, $data)
    {
        $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "socket [$socket->id] try to join channel [{$data['channel']}]");

        if (isset($data["channel"])) {
            $channel = $data["channel"];
            if ($this->isPrivate($channel)) {
                $this->joinPrivate($socket, $data);
            } else {
                $socket->join($channel);
                $this->onJoin($socket, $channel);
            }
        } else {
            $this->options['dev_mode'] && $this->error("[" . now()->format("Y-m-d H:i:s") . "] - " . "Unset \$data['channel'] no channel to join!");
        }
    }

    /**
     * 加入私有频道
     */
    public function joinPrivate(Socket $socket, $data)
    {
        [$authenticate, $res] = $this->private->authenticate($socket, $data);
        if ($authenticate) {
            // dump("通过用户认证", $res);
            $socket->join($data['channel']);
            $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "authenticate socket [{$socket->id}] return response data :" . json_encode($res));
            if ($this->isPresence($data['channel'])) {
                $member = $res["channel_data"];
                $this->presence->join($socket, $data['channel'], $member);
            }
            $this->onJoin($socket, $data['channel']);
        } else {
            $this->options['dev_mode'] && $this->error("authorizate fail : " . $res["reason"]);
            $this->io->sockets->to($socket->id)->emit('subscription_error', $data['channel'], $res["status"]);
        };
    }


    /**
     * 客户端事件
     */
    public function clientEvent(Socket $socket, $data)
    {
        if (is_string($data)) {
            try {
                $data = json_decode($data);
            } catch (Exception $e) {
                Log::error("[clientEvent] fail : {$e->getMessage()}");
                $data = $data;
            }
        }

        if (isset($data["event"]) && isset($data['channel'])) {
            if (
                $this->isClientEvent($data["event"]) &&
                $this->isPrivate($data['channel']) &&
                $this->isInChannel($socket, $data['channel'])
            ) {
                $this->io->sockets->connected[$socket->id]
                    ->broadcast->to($data['channel'])
                    ->emit($data["event"], $data['channel'], $data["data"]);
            }
        }
    }

    /**
     * 离开频道
     */
    public function leave(Socket $socket, $channel, $reason)
    {
        if ($channel) {
            if ($this->isPresence($channel)) {
                $this->presence->leave($socket, $channel);
            }
            $socket->leave($channel);
            $this->onLeft($socket, $channel, $reason);
        }
    }


    /******************************
     *
     * 相关判断方法
     *
     ******************************/

    /**
     * 是否是私有频道
     */
    public function isPrivate($channel)
    {
        $isPrivate = false;

        foreach ($this->privateChannels as $privateChannel) {
            $privateChannel = Str::replace('\*', '.*', $privateChannel);
            $isPrivate      = Str::of($channel)->match("/$privateChannel/")->length();
            if ($isPrivate) {
                break;
            }
        }

        return $isPrivate;
    }

    /**
     * 是否是在场频道(群聊)
     */
    public function isPresence($channel)
    {
        return Str::of($channel)->startsWith("presence-");
    }

    /**
     * 是否是客户端自定义事件
     */
    public function isClientEvent($channel)
    {
        $isClientEvent = false;
        foreach ($this->clientEvents as $clientEvent) {
            $isClientEvent = Str::of($channel)->match("/" . Str::replace('\*', '.*', $clientEvent) . "/")->length();
            if ($isClientEvent) {
                break;
            }
        }
        return $isClientEvent;
    }

    /**
     * 是否在频道中
     */
    public function isInChannel(Socket $socket, $channel)
    {
        return isset($socket->rooms[$channel]);
    }


    /***********************************
     *
     * 自定义事件
     *
     ***********************************/

    /**
     * join事件
     */
    public function onJoin(Socket $socket, $channel)
    {
        $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - socket [" . $socket->id . "] joined channel: " . $channel);
        event(new SocketJoinedChannelEvent($socket, $channel, $this));

        foreach ($this->onJoins as $fn) {
            $fn->call($this, $socket, $channel);
        }
    }

    /**
     * left事件
     */
    public function onLeft(Socket $socket, $channel, $reason)
    {
        if ($this->options->devMode) {
            $this->options['dev_mode'] && $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . $socket->id . " left channel:[" . $channel . "] reason: $reason");
        }
        event(new SocketLeftChannelEvent($socket, $channel));
    }

    public static function make(...$args)
    {
        return new static(...$args);
    }
}
