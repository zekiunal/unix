<?php

namespace Nexus\Infrastructure\Repository;

use Nexus\Domain\Datasource\RouterDatasourceInterface;
use Nexus\Domain\Repository\RouterRepositoryInterface;

class RouterRepository implements RouterRepositoryInterface
{
    private RouterDatasourceInterface $datasource;
    public function __construct(RouterDatasourceInterface $datasource)
    {
        $this->datasource = $datasource;
    }
    public function handlerMessage(array $message): array
    {
        return $this->datasource->handleMessage($message);
    }
}