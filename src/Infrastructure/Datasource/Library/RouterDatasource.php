<?php

namespace Nexus\Infrastructure\Datasource\Library;

use Nexus\Application\Router;
use Nexus\Domain\Datasource\RouterDatasourceInterface;

class RouterDatasource implements RouterDatasourceInterface
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }
    public function handleMessage(array $message): array
    {
        $method = $message['method'] ?? 'GET';
        $uri = $message['path'] ?? '/';
        $data = $message['data'] ?? [];
        $router = new Router($this->routes);
        $response = $router->dispatch($method, $uri, $data);
        unset($router);
        return $response;
    }
}