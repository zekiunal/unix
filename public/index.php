<?php

use DI\ContainerBuilder;
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

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(BASE_DIR . '/app/config/container.php');

try {
    $container = $containerBuilder->build();
} catch (Exception $e) {
    throw new RuntimeException('Unable to load container: ' . $e->getMessage());
}

try {
	$application = new Unix($container);
    $application->setRouters($container->get('routes'));
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