<?php

namespace Porygon\LaravelEchoServer\Databases;

interface DatabaseInterface
{
    /**
     * 获取指定键
     */
    public function get($key);
    /**
     * 设置键值
     */
    public function set($key, $value = null);
}
