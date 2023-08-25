<?php

namespace Porygon\LaravelEchoServer\Commands;

use Porygon\LaravelEchoServer\EchoServer;
use Illuminate\Console\Command;
use Porygon\LaravelEchoServer\Server;
use Workerman\Worker;

class EchoServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "echo-server
    {action : 动作[help、start、stop、restart、reload、status、connections] 直接传到Workerman}
    {--d|daemon : 以守护进程的方式启动}
    {--g|gracefully : 平滑操作}
    {--p|process=3 : 多进程模式时最大进程数}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'laravel echo server 但是 workerman';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var  Server*/
        $server = app()->make(Server::class, ["options" => config("echo-server")]);
        $this->info("L A R A V E L  E C H O  S E R V E R var.workerman");
        $this->info("Initing server...");

        $res = $server->handle();
        if (!$res) {
            return;
        }
        $this->info("Starting server...");
        Worker::runAll();
    }
}
