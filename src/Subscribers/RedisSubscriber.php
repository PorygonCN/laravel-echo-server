<?php

namespace Porygon\LaravelEchoServer\Subscribers;

use Illuminate\Support\Str;
use Workerman\Redis\Client;

class RedisSubscriber extends SubscriberAdapter
{
    protected $subscribed = false;
    /**
     * @var Client
     */
    public $redis;
    public function subscribe($callback)
    {
        $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "subscribe redis events");
        $keyPrefix        = config("echo-server.key_prefix", "");
        $this->redis      = new Client('redis://127.0.0.1:6379');
        $this->subscribed = true;
        $subscribed       = &$this->subscribed;
        $this->redis->psubscribe([$keyPrefix . '*'], function ($pattern, $channel, $message) use ($callback, $keyPrefix, &$subscribed) {
            if ($subscribed) {
                $this->info("[" . now()->format("Y-m-d H:i:s") . "] - " . "get redis event channel[$channel] message[$message]");
                // $this->options['dev_mode']&&$this->info("pattern $pattern, channel $channel, message $message");
                $callback(Str::substr($channel, Str::length($keyPrefix)), json_decode($message));
            }
        });
    }
    public function unsubscribe()
    {
        $this->warn("[" . now()->format("Y-m-d H:i:s") . "] - " . "unsubscribing redis events");
        $this->subscribed = false;
    }
}
