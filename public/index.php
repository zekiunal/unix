<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nexus\Application\Unix;

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/microservices/error.log');

const BASE_DIR = __DIR__ . '/../';

if (file_exists(BASE_DIR . '/vendor/autoload.php')) {
    require_once BASE_DIR . '/vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed. Run 'composer install' first." . PHP_EOL;
    exit(1);
}

$routers = require_once BASE_DIR . '/app/config/routers.php';

try {
	$application = new Unix();
    $application->setRouters($routers);
	$application->registerService(\App\Services\UserService::class, 'user');
    $application->run();
} catch (Throwable $e) {
    $logger = new Logger('socket');

    $logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));

    $logger->error("Kritik hata: " . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    echo $e->getMessage();

    exit(1);
}