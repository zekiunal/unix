<?php

namespace Nexus\Domain\Datasource;

interface RouterDatasourceInterface
{
    public function handleMessage(array $message): array;
}