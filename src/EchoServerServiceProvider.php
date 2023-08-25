<?php

namespace Porygon\LaravelEchoServer;

use Porygon\LaravelEchoServer\Events\SocketJoinedChannelEvent;
use Porygon\LaravelEchoServer\Listeners\UpdateList;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Porygon\LaravelEchoServer\Commands\EchoServerCommand;

class EchoServerServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        $this->init();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }


    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'echo-server');
        $this->mergeConfigFrom(__DIR__ . '/../config/echo-server.php', 'echo-server');
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function init()
    {
        $this->publishes([__DIR__ . '/../config/echo-server.php' => config_path('echo-server.php'),], "laravel-echo-server-config");
        $this->publishes([__DIR__ . '/../routes/echo-server-http-subscriber.php' => base_path('routes/echo-server-http-subscriber.php'),], "laravel-echo-server-http-subscriber");

        $this->commands(EchoServerCommand::class);

        $this->loadRoutes();

        $this->loadMigrations();
    }

    public function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
    public function loadRoutes()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}
