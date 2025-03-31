<?php

namespace Nexus\Domain\Repository;

interface RouterRepositoryInterface
{
    public function handlerMessage(array $message): array;
}