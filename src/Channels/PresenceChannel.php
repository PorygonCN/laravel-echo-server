<?php

namespace Porygon\LaravelEchoServer\Channels;

use Porygon\LaravelEchoServer\ConsoleOutput;
use Porygon\LaravelEchoServer\Databases\DatabaseAdapter;
use Exception;
use PHPSocketIO\Socket;
use PHPSocketIO\SocketIO;

class PresenceChannel  extends ConsoleOutput
{
    /**
     * @var DatabaseAdapter
     */
    public $database;

    public function __construct(public SocketIO $io, public $options)
    {
        parent::__construct();
        $this->database = app()->make($this->options["database"]);
    }

    /**
     * 获取频道成员
     */
    public function getMembers($channel)
    {
        $members = $this->database->get("{$channel}:members");

        return $members;
    }
    /**
     * 判断是否是频道成员
     */
    public function isMember($channel, $member)
    {
        $members = $this->getMembers($channel);
        $members = $this->removeInactive($channel, $members, $member);
        foreach ($members as $channelMember) {
            if ($member["user_id"] == $channelMember["user_id"]) {
                return true;
            }
        }
        return false;
    }
    /**
     * 删除不活动的用户(离线的)
     */
    public function removeInactive($channel, $members, $member)
    {
        $this->io->of("/")->in($channel)->clients(function () use (&$members, $member) {
            $clients = array_keys($this->io->engine->clients);
            $members =  $members->filter(function ($m) use ($member, $clients) {
                return in_array($m["socketId"], $clients);
            });
        });
        return $members;
    }

    /**
     * 加入频道
     */
    public function join($socket, $channel, $member)
    {
        if (!$member) {
            $this->options['dev_mode'] && $this->error("[" . now()->format("Y-m-d H:i:s") . "] - " . "Unable to join channel. Member data for presence channel missing");
            return;
        }
        try {
            $members            = $this->getMembers($channel);
            $member["socketId"] = $socket->id;
            $members->prepend($member);
            // 设置存入数据库
            $members = collect($members->unique("user_id")->values());
            $this->database->set($channel . ":members", $members);
            $this->onSubscribed($socket, $channel, $members);

            if (!$this->isMember($channel, $member)) {
                $this->onJoin($socket, $channel, $member);
            }
        } catch (Exception $e) {
            $this->error("[" . now()->format("Y-m-d H:i:s") . "] error retrieving pressence channel members :" . $e->getMessage());
        }
    }

    /**
     * 离开频道
     */
    public function leave(Socket $socket, $channel)
    {
        $members = $this->getMembers($channel);
        $member  = $members->where("socketId", $socket->id)->first();
        $members = $members->filter(fn ($member) => $member["socketId"] != $socket->id);
        $this->database->set($channel . ":members", $members);
        if ($this->isMember($channel, $member)) {
            $member["socketId"] = null;
            $this->onLeave($channel, $member);
        }
    }


    /**
     * 加入频道事件
     */
    public function onJoin($socket, $channel, $member)
    {
        $this->io->sockets->connected[$socket->id]->broadcast
            ->to($channel)
            ->emit("presence:joining", $channel, $member);
    }

    /**
     * 离开频道事件
     */
    public function onLeave($channel, $member)
    {
        $this->io->to($channel)->emit("presence:leaving", $channel, $member);
    }

    /**
     * 订阅成功事件
     */
    public function onSubscribed($socket, $channel, $members)
    {
        $this->io->to($socket->id)->emit("presence:subscribed", $channel, $members);
    }


    public static function make(...$arg)
    {
        return new static(...$arg);
    }
}
