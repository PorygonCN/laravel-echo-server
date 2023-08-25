<?php


foreach (config("echo-server.http_subscribers", []) as $subscriber) {
    if (is_file($subscriber)) {
        require $subscriber;
    }
}
