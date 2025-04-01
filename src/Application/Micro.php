<?php

namespace Nexus\Application;

use DI\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nexus\Application\Exception\ServiceException;
use Nexus\Socket\Request\HttpsRequest;
use Psr\Log\LoggerInterface;

class Micro
{
    public array $routers = [];
    /**
     * @var \DI\Container
     */
    private Container $container;
    private LoggerInterface $logger;
    protected array $services = [];
    protected bool $running = false;
    protected array $serviceInfo = [];
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->logger = new Logger('application');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
    }

    public function run(): void
    {
        $this->logger ->info("Application running...");
        try {
            $service = new HttpsRequest('http', $this->container);
            $service->listen($this->routers);
        } catch (\Throwable $e) {
            $logger = $this->logger;
            $logger->error("Error: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            exit(1);
        }
    }

    public function setRouters(array $routers): void
    {
        $this->routers = $routers;
    }
}