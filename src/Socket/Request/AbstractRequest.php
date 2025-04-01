<?php

namespace Nexus\Socket\Request;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nexus\Socket\Security;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractRequest
{
    protected string $serviceName;
    protected ContainerInterface $container;
    protected LoggerInterface $logger;
    protected Security $security;
    protected array $metrics = [
        'requests' => 0,
        'errors' => 0,
        'startTime' => 0,
        'avgResponseTime' => 0,
        'totalResponseTime' => 0
    ];
    protected bool $running = false;

    public function __construct(string $serviceName, ContainerInterface $container)
    {
        $this->serviceName = $serviceName;
        $this->container = $container;
        $this->logger = new Logger('socket');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
        $this->metrics['startTime'] = time();
        $this->security = Security::getInstance();
    }


}