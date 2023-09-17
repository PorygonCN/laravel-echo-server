<?php

namespace Porygon\LaravelEchoServer\Databases;

use Illuminate\Support\Facades\Cache as FacadesCache;

class Cache extends DatabaseAdapter
{
    public $db;
    public function __construct()
    {
        /**
         * @var \Illuminate\Cache\TaggedCache
         */
        $this->db = FacadesCache::tags("echo-server");
    }
    public function get($key)
    {
        return $this->db->get($key, collect());
    }
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            $this->db->putMany($key);
        } else {
            $this->db->put($key, $value);
        }
    }
}
