<?php

namespace Nexus\Domain\UseCase\Router;

use Nexus\Domain\Repository\RouterRepositoryInterface;

class HandleMessageUseCase
{
    private RouterRepositoryInterface $repository;

    public function __construct(RouterRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $message): array
    {
        return $this->repository->handlerMessage($message);
    }
}