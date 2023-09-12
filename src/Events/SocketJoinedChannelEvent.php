<?php

namespace Porygon\LaravelEchoServer\Events;

use Porygon\LaravelEchoServer\Channels\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PHPSocketIO\Socket;

/**
 * 新的socket接入频道的事件
 */
class SocketJoinedChannelEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Socket $echoSocket, public $channel, public Channel $channelManager)
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
