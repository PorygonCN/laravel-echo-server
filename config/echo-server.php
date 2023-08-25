<?php


return [
    /*********************************
     *
     * if true, console will print
     * more information
     *
     ********************************/
    "dev_mode" => env("ECHO_SERVER_DEBUG", config("app.debug")),

    /***************************************
     *
     * redis subscriber filter key prefix
     *
     ***************************************/
    "key_prefix" => env("ECHO_SERVER_KEY_PREFIX", config("database.redis.options.prefix")),

    /*****************************************
     *
     * the port echo-server listening to
     *
     ****************************************/
    "port" => env("ECHO_SERVER_PORT", 6001),

    /*****************************************
     *
     * echo-server authorizate path
     *
     ****************************************/
    "authorizate" => [
        "host" => rtrim(config("app.url"), "/"),
        "api"  => "/broadcasting/auth"
    ],


    /*********************************************
     *
     * wss and https
     *
     *********************************************/
    'use_ssl'  => env("ECHO_SERVER_USE_SSL"),
    'ssl'      => [
        'local_cert'        => env("ECHO_SERVER_SSL_CERT"),   // 也可以是crt文件
        'local_pk'          => env("ECHO_SERVER_SSL_PK"),
        'verify_peer'       => false,
        'allow_self_signed' => true,                          //如果是自签名证书需要开启此选项
    ],


    /************************************************
     *
     * api config for application
     *
     ************************************************/
    "api" => [
        "enable"     => true,
        "prefix"     => "api/echo-server",
        "middleware" => [
            "api",
        ],
    ],

    /**************************************************
     *
     * echo-server's api
     *
     *
     *************************************************/
    "http_sunscribers" => [
        base_path("routes/echo-server-http-subscriber.php"),
    ],

    /***********************************************
     *
     * EchoServerApp Model class
     *
     ***********************************************/
    "app_model" => Porygon\LaravelEchoServer\Models\EchoServerApp::class,

    /*************************************************
     *
     * database for echo server to save some things
     *
     */
    "database"         => Porygon\LaravelEchoServer\Databases\Cache::class,

    /**********************************************
     *
     * redis subscriber
     *
     * subscribe redis event
     *
     **********************************************/
    "redis_subscriber" => Porygon\LaravelEchoServer\Subscribers\RedisSubscriber::class,

];
