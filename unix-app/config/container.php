<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nexus\Domain\Repository\RouterRepositoryInterface;
use Nexus\Infrastructure\Datasource\Library\RouterDatasource;
use Nexus\Infrastructure\Repository\RouterRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class           => function (ContainerInterface $c) {
        $logger = new Logger('app');

        $processor = new UidProcessor();
        $logger->pushProcessor($processor);

        $handler = new StreamHandler('php://stdout');
        $logger->pushHandler($handler);

        return $logger;
    },
    RouterRepositoryInterface::class => function (ContainerInterface $c) {
        $datasource = new RouterDatasource($c->get('routes'));
        return new RouterRepository($datasource);
    },
    'routes' => function (ContainerInterface $c) {
        return require_once __DIR__ . '/routers.php';
    },
];