<?php

declare(strict_types=1);

use App\Realtime\ArticleHub;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/Realtime/ArticleHub.php';
require dirname(__DIR__) . '/Includes/db.php';

$secret = getenv('APP_KEY');
if ($secret === false || strlen($secret) < 32) {
    fwrite(STDERR, "APP_KEY must contain at least 32 characters.\n");
    exit(1);
}
$address = getenv('WS_BIND_ADDRESS') ?: '127.0.0.1';
$port = (int) (getenv('WS_PORT') ?: 8081);
$hub = new ArticleHub($secret, $pdo);
$server = IoServer::factory(new HttpServer(new WsServer($hub)), $port, $address);
$server->loop->addPeriodicTimer(1, static function () use ($hub): void {
    $hub->publishPendingNotifications();
});
$server->run();
