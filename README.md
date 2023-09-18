# Laravel Echo Server with Workerman

PHP server for Laravel Echo broadcasting with Workerman.

## System Requirements

The following are required to function properly.

- Laravel 5.3
- Redis 3+

Additional information on broadcasting with Laravel can be found on the
official docs: [Laravel-broadcasting](https://laravel.com/docs/broadcasting)

## Getting Started

Install package with the following command:

```shell
$   composer require porygon/laravel-echo-server
```

### Publish service provider

Run command in your project directory:

```shell
$   php artisan vendor:publish --provider=Porygon\\LaravelEchoServer\\EchoServerServiceProvider
```

this command will publish a config file and a http-subscriber route file.

#### Run The Server

in your project root directory, run

```shell
$ php artisan laravel-echo-server start
```

#### Stop The Server

in your project root directory, run

```shell
$ laravel-echo-server stop
```

### Configurable Options

Edit the default configuration of the server by adding options to your **echo-server.php** file.

### Running with SSL

- Your client side implementation must access the socket.io client from https.
- The server configuration must set the server host to use https.
- The server configuration should include paths to both your ssl certificate and key located on your server.

_Note: This library currently only supports serving from either http or https, not both._

#### Alternative SSL implementation

If you are struggling to get SSL implemented with this package, you could look at using a proxy module within Apache or NginX. Essentially, instead of connecting your websocket traffic to https://yourserver.dev:6001/socket.io?..... and trying to secure it, you can connect your websocket traffic to https://yourserver.dev/socket.io. Behind the scenes, the proxy module of Apache or NginX will be configured to intercept requests for /socket.io, and internally redirect those to your echo server over non-ssl on port 6001. This keeps all of the traffic encrypted between browser and web server, as your web server will still do the SSL encryption/decryption. The only thing that is left unsecured is the traffic between your webserver and your Echo server, which might be acceptable in many cases.

##### Sample NginX proxy config

```
#the following would go within the server{} block of your web server config
location /socket.io {
	    proxy_pass http://laravel-echo-server:6001; #could be localhost if Echo and NginX are on the same box
	    proxy_http_version 1.1;
	    proxy_set_header Upgrade $http_upgrade;
	    proxy_set_header Connection "Upgrade";
	}
```

#### Sample Apache proxy config

```
RewriteCond %{REQUEST_URI}  ^/socket.io            [NC]
RewriteCond %{QUERY_STRING} transport=websocket    [NC]
RewriteRule /(.*)           ws://localhost:6001/$1 [P,L]

ProxyPass        /socket.io http://localhost:6001/socket.io
ProxyPassReverse /socket.io http://localhost:6001/socket.io
```

## Subscribers

The Laravel Echo Server subscribes to incoming events with two methods: Redis & Http.

### Redis

Your core application can use Redis to publish events to channels. The Laravel Echo Server will subscribe to those channels and broadcast those messages via socket.io.

### Http [COMING SOON]

Using Http, you can also publish events to the Laravel Echo Server in the same fashion you would with Redis by submitting a `channel` and `message` to the broadcast endpoint. You need to generate an API key as described in the [API Clients](#api-clients) section and provide the correct API key.

**Request Endpoint**

```http
POST http://app.dev:6001/apps/your-app-id/events?auth_key=skti68i...

```

**Request Body**

```json
{
  "channel": "channel-name",
  "name": "event-name",
  "data": {
    "key": "value"
  },
  "socket_id": "h3nAdb134tbvqwrg"
}
```

**channel** - The name of the channel to broadcast an event to. For private or presence channels prepend `private-` or `presence-`.
**channels** - Instead of a single channel, you can broadcast to an array of channels with 1 request.
**name** - A string that represents the event key within your app.
**data** - Data you would like to broadcast to channel.
**socket_id (optional)** - The socket id of the user that initiated the event. When present, the server will only "broadcast to others".

### Pusher

The HTTP subscriber is compatible with the Laravel Pusher subscriber. Just configure the host and port for your Socket.IO server and set the app id and key in config/broadcasting.php. Secret is not required.

```php
 'pusher' => [
    'driver' => 'pusher',
    'key' => env('PUSHER_KEY'),
    'secret' => null,
    'app_id' => env('PUSHER_APP_ID'),
    'options' => [
        'host' => 'localhost',
        'port' => 6001,
        'scheme' => 'http'
    ],
],
```

You can now send events using HTTP, without using Redis. This also allows you to use the Pusher API to list channels/users as described in the [Pusher PHP library](https://github.com/pusher/pusher-http-php)

## HTTP API [COMING SOON]

The HTTP API exposes endpoints that allow you to gather information about your running server and channels.

**Status**
Get total number of clients, uptime of the server, and memory usage.

```http
GET /apps/:APP_ID/status
```

**Channels**
List of all channels.

```http
GET /apps/:APP_ID/channels
```

**Channel**
Get information about a particular channel.

```http
GET /apps/:APP_ID/channels/:CHANNEL_NAME
```

**Channel Users**
List of users on a channel.

```http
GET /apps/:APP_ID/channels/:CHANNEL_NAME/users
```

## Cross Domain Access To API

Cross domain access can be specified in the laravel-echo-server.json file by changing `allowCors` in `apiOriginAllow` to `true`. You can then set the CORS Access-Control-Allow-Origin, Access-Control-Allow-Methods as a comma separated string (GET and POST are enabled by default) and the Access-Control-Allow-Headers that the API can receive.

Example below:

```json
{
  "apiOriginAllow": {
    "allowCors": true,
    "allowOrigin": "http://127.0.0.1",
    "allowMethods": "GET, POST",
    "allowHeaders": "Origin, Content-Type, X-Auth-Token, X-Requested-With, Accept, Authorization, X-CSRF-TOKEN, X-Socket-Id"
  }
}
```

This allows you to send requests to the API via AJAX from an app that may be running on the same domain but a different port or an entirely different domain.

## Database

To persist presence channel data, there is support for use of Redis or SQLite as a key/value store. The key being the channel name, and the value being the list of presence channel members.

Each database driver may be configured in the **laravel-echo-server.json** file under the `databaseConfig` property. The options get passed through to the database provider, so developers are free to set these up as they wish.

### Redis

For example, if you wanted to pass a custom configuration to Redis:

```json
{
  "databaseConfig": {
    "redis": {
      "port": "3001",
      "host": "redis.app.dev",
      "keyPrefix": "my-redis-prefix"
    }
  }
}
```

_Note: No scheme (http/https etc) should be used for the host address_

_A full list of Redis options can be found [here](https://github.com/luin/ioredis/blob/master/API.md#new-redisport-host-options)._

## Presence Channels

When users join a presence channel, their presence channel authentication data is stored using Redis.

While presence channels contain a list of users, there will be instances where a user joins a presence channel multiple times. For example, this would occur when opening multiple browser tabs. In this situation "joining" and "leaving" events are only emitted to the first and last instance of the user.

Optionally, you can configure laravel-echo-server to publish an event on each update to a presence channel, by setting `databaseConfig.publishPresence` to `true`:

```json
{
  "database": "redis",
  "databaseConfig": {
    "redis": {
      "port": "6379",
      "host": "localhost"
    },
    "publishPresence": true
  }
}
```

You can use Laravel's Redis integration, to trigger Application code from there:

```php
Redis::subscribe(['PresenceChannelUpdated'], function ($message) {
    var_dump($message);
});
```

## Client Side Configuration

See the official Laravel documentation for more information. <https://laravel.com/docs/master/broadcasting#introduction>

### Tips

#### Socket.io client library

You can include the socket.io client library from your running server. For example, if your server is running at `app.dev:6001` you should be able to
add a script tag to your html like so:

```
<script src="//app.dev:6001/socket.io/socket.io.js"></script>
```

_Note: When using the socket.io client library from your running server, remember to check that the `io` global variable is defined before subscribing to events._

#### µWebSockets deprecation

µWebSockets has been [officially deprecated](https://www.npmjs.com/package/uws). Currently there is no support for µWebSockets in Socket.IO, but it may have the new [ClusterWS](https://www.npmjs.com/package/@clusterws/cws) support incoming. Meanwhile Laravel Echo Server will use [`ws` engine](https://www.npmjs.com/package/ws) by default until there is another option.
