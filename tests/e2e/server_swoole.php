<?php

require_once __DIR__.'/init.php';

use Utopia\Adapter\Swoole\Server;
use Utopia\Http;

$server = new Server('0.0.0.0', '80');
$http = new Http($server, 'UTC');

$server->onWorkerStart(function ($swooleServer, $workerId) {
    \fwrite(STDOUT, "Worker " . ++$workerId . " started successfully\n");
});

$http->start();
