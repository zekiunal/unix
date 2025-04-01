<?php

namespace Nexus\Socket\Request;

use Nexus\Domain\Repository\RouterRepositoryInterface;
use Nexus\Domain\UseCase\Router\HandleMessageUseCase;
use Psr\Container\ContainerInterface;

class HttpRequest extends AbstractRequest
{
    public function __construct(string $serviceName, ContainerInterface $container)
    {
        parent::__construct($serviceName, $container);
    }

    /**
     * @throws \Nexus\Socket\SocketException
     * @throws \Exception
     */
    public function listen($routers): void
    {
        $this->logger->info("[$this->serviceName] Http request is listening: ...");
        $this->running = true;
        $startTime = microtime(true);
        try {
            $message = $this->receiveMessage($_SERVER);
            $this->metrics['requests']++;
            $response = $this->handleMessage($routers, $message);
            $this->sendMessage($response);

            // Update metrics
            $responseTime = microtime(true) - $startTime;
            $this->metrics['totalResponseTime'] += $responseTime;
            $this->metrics['avgResponseTime'] = $this->metrics['totalResponseTime'] / $this->metrics['requests'];

            $this->logger->debug("Request processed", [
                'path' => $message['path'] ?? '/',
                'method' => $message['method'] ?? 'GET',
                'response' => ($response),
                'responseTime' => round($responseTime * 1000, 3) . 'ms',
            ]);
        } catch (\Throwable $e) {
            $this->metrics['errors']++;
            $this->logger->error("Error while processing request: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            $this->sendMessage([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal Server Error'
            ]);
        }
    }

    protected function receiveMessage($message): array
    {
        $request['method'] = $message['REQUEST_METHOD'] ?? 'GET';
        $request['path'] = $message['REQUEST_URI']  ?? '/';
        return $request;
    }

    protected function handleMessage(array $routes, array $message): array
    {
        $handleMessageUseCase = new HandleMessageUseCase($this->container->get(RouterRepositoryInterface::class));
        $response = $handleMessageUseCase->execute($message);
        unset($handleMessageUseCase);
        return $response;
    }

    /**
     * @throws \Exception
     */
    protected function sendMessage(array $data): void
    {
        $message = json_encode($data);
        if ($message === false) {
            $this->logger->error("JSON encoding failed: " . json_last_error_msg());
            throw new \Exception("JSON encoding failed: " . json_last_error_msg());
        }
        echo $message . PHP_EOL;
    }
}