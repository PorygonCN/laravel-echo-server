<?php

namespace Porygon\LaravelEchoServer\Subscribers;

interface SubscriberInterface
{
    public function subscribe($callback);
    public function unsubscribe();
}
